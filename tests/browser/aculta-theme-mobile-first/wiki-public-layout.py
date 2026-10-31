#!/usr/bin/env python3
"""Check public Wiki home and article reflow/order in visible Firefox ESR."""

import base64
import json
import os
from pathlib import Path
import sys
import time
from urllib.parse import urlsplit

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

PAGE = os.environ.get("WIKI_BASE_URL", "https://wiki420.aculta.toca.net.br/")
WIDTHS = tuple(map(int, os.environ.get(
    "WIKI_WIDTHS", "320,360,390,599,600,768,1024,1280,1440",
).split(",")))
CSS_ROOT = Path(os.environ.get("WIKI_CSS_ROOT", ROOT)).resolve()
THEME = CSS_ROOT / "web/themes/custom/aculta420"
CSS_PATHS = (
    THEME / "css/components/wiki.css",
    THEME / "components/patterns/wiki-entry/wiki-entry.css",
)
checks = []


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({
            "id": command_id, "method": method, "params": params or {},
        }))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 1000},
        })

    def close(self):
        self.socket.close()


def check(name, passed, detail):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {detail}", flush=True)


def inject_candidate(driver):
    css = "\n".join(path.read_text(encoding="utf-8") for path in CSS_PATHS)
    driver.js("""const style=document.createElement('style');
style.id='wiki-layout-candidate';style.textContent=arguments[0];
document.head.append(style);return true;""", [css])


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com Firefox ESR visível em DISPLAY=:0.")
    parsed_page = urlsplit(PAGE)
    if parsed_page.scheme != "https" or parsed_page.path not in ("", "/"):
        raise SystemExit("WIKI_BASE_URL precisa ser a raiz HTTPS do purpose Wiki.")

    driver = Driver(bidi=True)
    viewport = Viewport(driver)
    candidate = os.environ.get("WIKI_CSS_CANDIDATE") == "1"
    try:
        for width in WIDTHS:
            viewport.set(width)
            driver.get(PAGE)
            time.sleep(0.6)
            if candidate:
                inject_candidate(driver)
            home = json.loads(driver.js("""return JSON.stringify({
  width:innerWidth, documentWidth:document.documentElement.scrollWidth,
  root:!!document.querySelector('.aculta-wiki-home'),
  cards:document.querySelectorAll('.aculta-wiki-home .aculta-wiki-card').length,
  h1:[...document.querySelectorAll('main#main-content h1')]
    .filter(node=>node.getClientRects().length).length,
  institutionBarCount:document.querySelectorAll('.aculta-institution-bar').length,
  domainHeaderCount:document.querySelectorAll('.aculta-header').length,
  articleUrl:document.querySelector('.aculta-wiki-home a[href^="/verbete/"]')?.href||null
});"""))
            check(f"{width}px home renders and shell fits", home["width"] == width
                  and home["root"] and home["cards"] > 0 and home["h1"] == 1
                  and home["institutionBarCount"] == 1
                  and home["domainHeaderCount"] == 1
                  and home["documentWidth"] <= width, json.dumps(home, ensure_ascii=False))
            article_url = home["articleUrl"]
            parsed_article = urlsplit(article_url or "")
            if (parsed_article.scheme != "https"
                    or parsed_article.hostname != parsed_page.hostname
                    or not parsed_article.path.startswith("/verbete/")):
                raise RuntimeError("A home não forneceu link de artigo público no mesmo host.")

            driver.get(article_url)
            time.sleep(0.6)
            if candidate:
                inject_candidate(driver)
            article = json.loads(driver.js("""return JSON.stringify((()=>{
 const root=document.documentElement, layout=document.querySelector('.aculta-wiki-article__layout');
 const main=layout?.querySelector('.aculta-wiki-article__main');
 const aside=layout?.querySelector('.aculta-wiki-article__aside');
 const rect=e=>{if(!e)return null;const r=e.getBoundingClientRect();return {top:r.top,left:r.left,right:r.right,width:r.width,height:r.height}};
 const columns=layout?getComputedStyle(layout).gridTemplateColumns.trim().split(/\\s+/).length:0;
 return {width:innerWidth,documentWidth:root.scrollWidth,
 article:!!document.querySelector('.aculta-wiki-article'),layout:!!layout,
 h1:[...document.querySelectorAll('main#main-content h1')].filter(e=>e.getClientRects().length).length,
 bodyText:document.querySelector('.aculta-wiki-article__body')?.innerText.trim().length||0,
 columns,main:rect(main),aside:rect(aside),
 domMainBeforeAside:!!main&&!!aside&&!!(main.compareDocumentPosition(aside)&Node.DOCUMENT_POSITION_FOLLOWING),
 institutionBarCount:document.querySelectorAll('.aculta-institution-bar').length,
 domainHeaderCount:document.querySelectorAll('.aculta-header').length};
})());"""))
            mobile = width < 768
            order_ok = bool(article["main"] and article["aside"] and
                            article["main"]["top"] <= article["aside"]["top"] + 1)
            columns_ok = article["columns"] == (1 if mobile else 2)
            desktop_order_ok = mobile or bool(
                article["main"] and article["aside"]
                and article["aside"]["left"] > article["main"]["left"]
            )
            check(f"{width}px article order, grid, shell and overflow",
                  article["width"] == width and article["article"] and article["layout"]
                  and article["h1"] == 1 and article["bodyText"] > 0
                  and article["domMainBeforeAside"] and order_ok and columns_ok
                  and desktop_order_ok and article["institutionBarCount"] == 1
                  and article["domainHeaderCount"] == 1
                  and article["documentWidth"] <= width,
                  json.dumps(article, ensure_ascii=False))
            if width in (320, 768, 1440):
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"wiki-article-{width}.png").write_bytes(base64.b64decode(shot))
    finally:
        viewport.close()
        driver.close()

    result = {"page": PAGE, "widths": WIDTHS, "candidateCssInjected": candidate,
              "checks": checks}
    (OUT / "results.json").write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
    failed = [item for item in checks if not item["passed"]]
    print(f"\n{len(checks)-len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
