#!/usr/bin/env python3
"""Read-only responsive smoke for a real public Forum topic in Firefox ESR."""

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
os.umask(0o077)
OUT.mkdir(parents=True, exist_ok=True)
OUT.chmod(0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("FORUM_TOPIC_BASE_URL", "https://growroom420.aculta.toca.net.br/")
WIDTHS = tuple(map(int, os.environ.get(
    "FORUM_TOPIC_WIDTHS", "320,360,390,599,600,768,1024,1280,1440",
).split(",")))
CSS_ROOT = Path(os.environ.get("FORUM_TOPIC_CSS_ROOT", ROOT)).resolve()
THEME = CSS_ROOT / "web/themes/custom/aculta420"
CSS_PATHS = (
    THEME / "css/components/forum-index.css",
    THEME / "css/components/discussion.css",
    THEME / "components/content/author-card/author-card.css",
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
        self.socket.send(json.dumps({"id": command_id, "method": method,
                                     "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })

    def close(self):
        self.socket.close()


def inject_candidate(driver):
    css = "\n".join(path.read_text(encoding="utf-8") for path in CSS_PATHS)
    return driver.js("""const style=document.createElement('style');
style.id='forum-topic-layout-candidate';style.textContent=arguments[0];
document.head.append(style);return !!style.sheet&&style.sheet.cssRules.length>0;""", [css])


def check(name, passed, detail):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {detail}", flush=True)


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com Firefox ESR visível em DISPLAY=:0.")
    base = urlsplit(BASE)
    if base.scheme != "https" or base.path not in ("", "/"):
        raise SystemExit("FORUM_TOPIC_BASE_URL precisa ser a raiz HTTPS do purpose Fórum.")

    driver = Driver(bidi=True)
    viewport = Viewport(driver)
    candidate = os.environ.get("FORUM_TOPIC_CSS_CANDIDATE") == "1"
    try:
        driver.get(BASE)
        time.sleep(0.8)
        topic_paths = driver.js("""return [...new Set([...document.querySelectorAll(
  '.view-aculta-forum-public-topics a[href^="/topico/"], main a[href^="/topico/"]'
)])].map(link=>link.getAttribute('href'));""")
        topic_url = None
        for topic_path in topic_paths:
            if not topic_path.startswith("/topico/"):
                continue
            driver.get(base.scheme + "://" + base.netloc + topic_path)
            time.sleep(0.5)
            reply_state = driver.js("""return {
  body:document.querySelector('.aculta-forum-topic--full .field--name-body')?.innerText.trim().length||0,
  posts:document.querySelectorAll('article[id^="forum-post-"]').length
};""")
            if reply_state["body"] > 0 and reply_state["posts"] > 0:
                topic_url = driver.url()
                break
        parsed = urlsplit(topic_url or "")
        if (parsed.scheme != "https" or parsed.hostname != base.hostname
                or not parsed.path.startswith("/topico/")):
            raise RuntimeError("Home pública não forneceu tópico com corpo e resposta no mesmo host.")
        if candidate:
            check("CSS candidata carregada", inject_candidate(driver), str(CSS_PATHS))

        for width in WIDTHS:
            viewport.set(width)
            metrics = json.loads(driver.js("""return JSON.stringify((()=>{
 const d=document.documentElement,b=document.body;
 const topic=document.querySelector('main article.aculta-forum-topic--full, main article.aculta-forum-topic');
 const body=topic?.querySelector('.field--name-body');
 const discussion=document.querySelector('.field--name-field-aculta-discussion');
 const aside=document.querySelector('.aculta-forum-topic__meta');
 const overflowing=[...document.querySelectorAll('body *')].map(e=>{
   const r=e.getBoundingClientRect(),s=getComputedStyle(e);
   return {tag:e.tagName,className:String(e.className).slice(0,90),right:Math.round(r.right),
     display:s.display,position:s.position,
     hiddenOverlay:!!e.closest('.offcanvas:not(.show),[aria-hidden="true"]')};
 }).filter(e=>e.display!=='none'&&e.position!=='fixed'&&!e.hiddenOverlay
   &&e.right>innerWidth+1).slice(0,4);
 return {width:innerWidth,documentWidth:d.scrollWidth,bodyWidth:b.scrollWidth,
   topic:!!topic,h1:[...document.querySelectorAll('main h1')].filter(e=>e.getClientRects().length).length,
   bodyText:body?.innerText.trim().length||0,
   authorCards:topic?.querySelectorAll('.aculta-author-card').length||0,
   topicMeta:!!aside,
   postCount:document.querySelectorAll('article[id^="forum-post-"]').length,
   discussion:!!discussion,discussionComments:discussion?.querySelectorAll('article.js-comment').length||0,
   institutionBarCount:document.querySelectorAll('.aculta-institution-bar').length,
   domainHeaderCount:document.querySelectorAll('.aculta-header').length,
   errors:[...document.querySelectorAll('.messages--error,[role="alert"]')]
     .filter(e=>e.getClientRects().length).length,overflowing};
})());"""))
            passed = (
                metrics["width"] == width and metrics["documentWidth"] <= width
                and metrics["bodyWidth"] <= width and metrics["topic"]
                and metrics["h1"] == 1 and metrics["bodyText"] > 0
                and metrics["authorCards"] > 0 and metrics["postCount"] > 0
                and metrics["discussion"] and metrics["discussionComments"] >= 0
                and metrics["institutionBarCount"] == 1
                and metrics["domainHeaderCount"] == 1 and metrics["errors"] == 0
                and not metrics["overflowing"]
            )
            check(f"Tópico real e discussão sem overflow a {width}px", passed,
                  json.dumps(metrics, ensure_ascii=False))
            if width in (320, 768, 1440):
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"forum-topic-{width}.png").write_bytes(base64.b64decode(screenshot))
    finally:
        viewport.close()
        driver.close()

    result = {"base": BASE, "topic": topic_url, "widths": WIDTHS,
              "candidateCssInjected": candidate, "checks": checks}
    (OUT / "results.json").write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
    failed = [item for item in checks if not item["passed"]]
    print(f"\n{len(checks)-len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
