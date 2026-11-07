#!/usr/bin/env python3
"""Read-only Firefox ESR visual smoke for V2 Forum listing, categories and topic."""

import base64
import json
import os
from pathlib import Path
import sys
import subprocess
import time
from urllib.parse import urlsplit, urlunsplit

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
BASE = os.environ.get("FORUM_BASE_URL", "")
WIDTHS = tuple(map(int, os.environ.get("FORUM_WIDTHS", "320,360,390,599,600,768,1024,1280,1440").split(",")))
os.umask(0o077)
OUT.mkdir(parents=True, exist_ok=True)
OUT.chmod(0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

records = []


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        tree = self.command("browsingContext.getTree")
        self.context = tree["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
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


def check(name, passed, detail):
    record = {"name": name, "passed": bool(passed), "detail": detail}
    records.append(record)
    print(f"{'PASS' if passed else 'FAIL'} {name}: {detail}", flush=True)


def screenshot(driver, filename):
    image = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / filename).write_bytes(base64.b64decode(image))


def path_url(base, path):
    if path.startswith(("http://", "https://")):
        return path
    return urlunsplit((base.scheme, base.netloc, path, "", ""))


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Use Firefox ESR visível em DISPLAY=:0.")
    parsed = urlsplit(BASE)
    if parsed.scheme != "https" or not parsed.hostname or parsed.path not in ("", "/"):
        raise SystemExit("Defina FORUM_BASE_URL com a raiz HTTPS do purpose Fórum.")

    driver = Driver(bidi=True, browser="firefox")
    viewport = Viewport(driver)
    try:
        windows = subprocess.check_output(["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True).splitlines()
        if not windows:
            raise RuntimeError("Firefox ESR visível não foi encontrado para os testes de zoom.")
        firefox_window = windows[-1]
        driver.get(BASE)
        time.sleep(0.8)
        topic_path = driver.js("""return document.querySelector(
  'main .forum-topic-row__title a[href*="/topico/"], main .aculta-forum-topic--teaser .aculta-forum-topic__title a[href*="/topico/"]'
)?.getAttribute('href') || '';""")
        categories_path = driver.js("""return document.querySelector('main a[href="/categorias"]')?.getAttribute('href') || '';""")
        if not topic_path or not categories_path:
            raise RuntimeError("Fórum não expôs um link público de tópico e categorias na landing.")

        pages = [
            ("lista", BASE, ".forum-topic-row", ".aculta-forum-list__panel"),
            ("categorias-da-landing", BASE,
             "#block-aculta420-v2-aculta-forum-categories-home .aculta-forum-category",
             "#block-aculta420-v2-aculta-forum-categories-home .aculta-forum-category"),
            ("categorias", path_url(parsed, categories_path), ".aculta-forum-category-list", ".aculta-forum-category"),
            ("topico", path_url(parsed, topic_path), ".aculta-forum-topic--full", ".aculta-forum-topic__content"),
        ]
        for page_name, url, required, component in pages:
            driver.get(url)
            time.sleep(0.35)
            for mode in ("light", "dark"):
                driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
                time.sleep(0.15)
                for width in WIDTHS:
                    viewport.set(width)
                    time.sleep(0.15)
                    metrics = driver.js("""return (() => {
  const doc=document.documentElement, body=document.body;
  const component=document.querySelector(arguments[0]);
  const required=document.querySelector(arguments[1]);
  const box=component?.getBoundingClientRect();
  const style=component?getComputedStyle(component):null;
  const overflowing=[...document.querySelectorAll('main *')].filter(el=>!el.closest('.forum-tabs__bar')).map(el=>{
    const r=el.getBoundingClientRect(), s=getComputedStyle(el);
    return {tag:el.tagName,cls:String(el.className).slice(0,80),right:Math.round(r.right),display:s.display};
  }).filter(x=>x.display!=='none'&&x.right>innerWidth+1).slice(0,5);
  return {viewport:innerWidth,documentWidth:doc.scrollWidth,bodyWidth:body.scrollWidth,
    h1:[...document.querySelectorAll('main h1')].filter(el=>el.getClientRects().length).length,
    required:!!required,component:!!component,componentWidth:box?Math.round(box.width):0,
    componentDisplay:style?.display||'',componentPadding:style?.padding||'',mode:doc.dataset.theme,
    title:document.querySelector('main h1')?.innerText.trim()||'',overflowing};
})()""", [component, required])
                    ok = (metrics["viewport"] == width and metrics["documentWidth"] <= width
                          and metrics["bodyWidth"] <= width and metrics["h1"] == 1
                          and metrics["required"] and metrics["component"]
                          and metrics["componentWidth"] <= width and not metrics["overflowing"])
                    check(f"{page_name} {mode} {width}px sem overflow", ok,
                          json.dumps(metrics, ensure_ascii=False))
                    if width in (320, 390, 1440):
                        screenshot(driver, f"forum-{page_name}-{mode}-{width}.png")

        driver.get(BASE)
        viewport.set(320)
        driver.js("document.documentElement.setAttribute('data-theme','light'); document.body.focus();")
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "forum-tab", "actions": [
            {"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"},
        ]}]})
        focus = driver.js("""const e=document.activeElement,s=e&&getComputedStyle(e);return {tag:e?.tagName||'',text:e?.innerText?.trim()||'',outline:s?.outlineStyle||'',outlineWidth:s?.outlineWidth||''};""")
        driver.js("window.__forumFirstFocus=document.activeElement")
        check("Foco de teclado visível na landing", focus["tag"] != "BODY" and focus["outline"] != "none", json.dumps(focus, ensure_ascii=False))
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "forum-tab-next", "actions": [
            {"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"},
        ]}]})
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "forum-shift-tab", "actions": [
            {"type": "keyDown", "value": "\ue008"}, {"type": "keyDown", "value": "\ue004"},
            {"type": "keyUp", "value": "\ue004"}, {"type": "keyUp", "value": "\ue008"},
        ]}]})
        reverse_focus = driver.js("return document.activeElement === window.__forumFirstFocus")
        check("Shift+Tab retorna ao controle focado anteriormente", reverse_focus, str(reverse_focus))

        driver.get(path_url(parsed, topic_path))
        subprocess.run(["xdotool", "windowactivate", "--sync", firefox_window], check=True)
        subprocess.run(["xdotool", "key", "ctrl+0"], check=True)
        viewport.set(640)
        baseline_dpr = driver.js("return devicePixelRatio")
        for _ in range(16):
            subprocess.run(["xdotool", "key", "ctrl+plus"], check=True)
            time.sleep(0.15)
            if driver.js("return devicePixelRatio") >= baseline_dpr * 2:
                break
        for factor in (2,):
            zoom = driver.js("""return {ratio:devicePixelRatio/arguments[0],viewport:innerWidth,
              documentWidth:document.documentElement.scrollWidth,bodyWidth:document.body.scrollWidth,
              overflow:document.documentElement.scrollWidth>innerWidth+1||document.body.scrollWidth>innerWidth+1,
              title:document.querySelector('main h1')?.innerText||''};""", [baseline_dpr])
            check(f"Tópico sem overflow com zoom real de {factor * 100}%", abs(zoom["ratio"] - factor) < 0.08 and not zoom["overflow"], json.dumps(zoom, ensure_ascii=False))
            screenshot(driver, f"forum-topic-zoom-{factor * 100}.png")
        subprocess.run(["xdotool", "key", "ctrl+0"], check=True)
        viewport.set(1280)
        for _ in range(32):
            subprocess.run(["xdotool", "key", "ctrl+plus"], check=True)
            time.sleep(0.15)
            if driver.js("return devicePixelRatio") >= baseline_dpr * 4:
                break
        zoom = driver.js("""return {ratio:devicePixelRatio/arguments[0],viewport:innerWidth,
          documentWidth:document.documentElement.scrollWidth,bodyWidth:document.body.scrollWidth,
          overflow:document.documentElement.scrollWidth>innerWidth+1||document.body.scrollWidth>innerWidth+1,
          title:document.querySelector('main h1')?.innerText||''};""", [baseline_dpr])
        check("Tópico sem overflow com zoom real de 400%", abs(zoom["ratio"] - 4) < 0.12 and not zoom["overflow"], json.dumps(zoom, ensure_ascii=False))
        screenshot(driver, "forum-topic-zoom-400.png")
    finally:
        viewport.close()
        driver.close()

    (OUT / "results.json").write_text(json.dumps({"base": BASE, "widths": WIDTHS, "records": records}, ensure_ascii=False, indent=2) + "\n")
    failed = [record for record in records if not record["passed"]]
    print(f"\n{len(records)-len(failed)}/{len(records)} checks passed; evidence: {OUT}")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
