#!/usr/bin/env python3
"""Check the real Courses rail reflow and native keyboard scrolling."""

import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlparse
from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

DOMAIN_ENV = os.environ.get("RAIL_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("RAIL_DOMAIN_ENV deve ser test ou production.")
deploy_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
entries = json.loads(deploy_path.read_text())["domain_transition"]["entries"]
entry = next((item for item in entries if item["domain_id"] == "cursos_aculta_org"), None)
if entry is None:
    raise SystemExit("Domain courses_aculta_org não existe no manifesto.")
PAGE = os.environ.get("RAIL_PAGE", f"https://{entry[f'{DOMAIN_ENV}_host']}/")
if urlparse(PAGE).scheme != "https" or urlparse(PAGE).hostname != entry[f"{DOMAIN_ENV}_host"]:
    raise SystemExit("RAIL_PAGE deve usar o host de Cursos configurado no deploy.json.")
WIDTHS = [320, 390, 768, 1440]
RAIL_CSS_OVERRIDE = os.environ.get("RAIL_CSS_OVERRIDE") == "1"
RAIL_DISABLE_JS = os.environ.get("RAIL_DISABLE_JS") == "1"
checks = []


def check(name, passed, detail):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {detail}", flush=True)


def browser_key(keys):
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True,
    ).splitlines()
    if not windows:
        raise RuntimeError("Firefox ESR visível não encontrado pelo xdotool.")
    subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", keys], check=True)


def rail_focus_metrics(driver):
    return driver.js("""const rail=document.querySelector('.aculta-rail');
      const style=rail?getComputedStyle(rail):null;
      return {focused:!!rail&&document.activeElement===rail,
        focusVisible:!!rail&&rail.matches(':focus-visible'),
        outlineStyle:style?.outlineStyle||'none',
        outlineWidth:Number.parseFloat(style?.outlineWidth||'0'),
        outlineOffset:style?.outlineOffset||'0px'};""")


def test_zoom_400(driver, viewport):
    browser_key("ctrl+0")
    viewport.set(1280)
    driver.js("const rail=document.querySelector('.aculta-rail');if(rail)rail.scrollLeft=0;")
    zoom = None
    for _ in range(32):
        zoom = driver.js("return {innerWidth, ratio:1280/innerWidth};")
        if zoom["ratio"] >= 3.92:
            break
        browser_key("ctrl+plus")
        time.sleep(0.12)
    rail_metrics = driver.js("""const rail=document.querySelector('.aculta-rail');
      const root=document.documentElement;return {innerWidth,ratio:1280/innerWidth,
      documentWidth:root.clientWidth,documentScrollWidth:root.scrollWidth,
      railCount:document.querySelectorAll('.aculta-rail').length,
      role:rail?.getAttribute('role'),label:rail?.getAttribute('aria-label'),
      tabindex:rail?.getAttribute('tabindex'),cards:rail?.querySelectorAll('.views-row').length||0,
      railClientWidth:rail?.clientWidth||0,railScrollWidth:rail?.scrollWidth||0,
      railScrollLeft:rail?.scrollLeft||0};""")
    reached = rail_metrics["ratio"] >= 3.92
    check("400% zoom: catálogo conserva largura e trilha rolável",
          reached and rail_metrics["innerWidth"] <= 327
          and rail_metrics["documentScrollWidth"] <= rail_metrics["documentWidth"]
          and rail_metrics["railCount"] == 1 and rail_metrics["role"] == "region"
          and bool(rail_metrics["label"]) and rail_metrics["tabindex"] == "0"
          and rail_metrics["cards"] >= 2
          and rail_metrics["railScrollWidth"] > rail_metrics["railClientWidth"],
          json.dumps(rail_metrics, ensure_ascii=False))
    if reached and rail_metrics["railCount"] == 1:
        driver.js("document.querySelector('.aculta-rail').focus();")
        focused = driver.js("return document.activeElement === document.querySelector('.aculta-rail');")
        check("400% zoom: região de Cursos recebe foco", focused, str(focused))
        driver.req("POST", f"/session/{driver.session}/actions", {
            "actions": [{"type": "key", "id": "rail-zoom-keyboard", "actions": [
                {"type": "keyDown", "value": "\ue014"},
                {"type": "keyUp", "value": "\ue014"},
            ]}],
        })
        time.sleep(0.2)
        after = driver.js("return document.querySelector('.aculta-rail').scrollLeft;")
        check("400% zoom: ArrowRight percorre a trilha", after > rail_metrics["railScrollLeft"],
              f"scrollLeft {rail_metrics['railScrollLeft']} → {after}")
        focus_metrics = rail_focus_metrics(driver)
        check("400% zoom: foco de teclado tem indicador visível",
              focus_metrics["focused"] and focus_metrics["focusVisible"]
              and focus_metrics["outlineStyle"] != "none"
              and focus_metrics["outlineWidth"] >= 3, json.dumps(focus_metrics, ensure_ascii=False))
        image = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / "courses-rail-zoom-400.png").write_bytes(base64.b64decode(image))
        for _ in range(12):
            driver.req("POST", f"/session/{driver.session}/actions", {
                "actions": [{"type": "key", "id": "rail-zoom-keyboard-end", "actions": [
                    {"type": "keyDown", "value": "\ue014"},
                    {"type": "keyUp", "value": "\ue014"},
                ]}],
            })
            time.sleep(0.05)
        end_metrics = driver.js("""const rail=document.querySelector('.aculta-rail');
          const last=rail?.querySelector('.views-row:last-child');
          const railRect=rail?.getBoundingClientRect();
          const lastRect=last?.getBoundingClientRect();
          return {scrollLeft:rail?.scrollLeft||0,maxScroll:(rail?.scrollWidth||0)-(rail?.clientWidth||0),
            lastCardVisible:!!(railRect&&lastRect&&lastRect.right<=railRect.right+1)};""")
        check("400% zoom: sequência ArrowRight alcança último cartão",
              end_metrics["scrollLeft"] >= end_metrics["maxScroll"] - 2
              and end_metrics["lastCardVisible"], json.dumps(end_metrics, ensure_ascii=False))
        image = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / "courses-rail-zoom-400-end.png").write_bytes(base64.b64decode(image))
    browser_key("ctrl+0")


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

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


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if subprocess.run(["which", "xdotool"], capture_output=True).returncode:
    raise SystemExit("xdotool é necessário para testar o zoom real do Firefox.")

driver = Driver(prefs={"javascript.enabled": False} if RAIL_DISABLE_JS else {}, bidi=True)
viewport = Viewport(driver)
try:
    for width in WIDTHS:
        browser_key("ctrl+0")
        viewport.set(width)
        driver.get(PAGE)
        time.sleep(0.8)
        if RAIL_CSS_OVERRIDE:
            rail_css = (ROOT / "web/themes/custom/aculta420/components/patterns/rail/rail.css").read_text()
            driver.js("""const style=document.createElement('style');
              style.dataset.railCssCandidate='true';style.textContent=arguments[0];
              document.head.append(style);return true;""", [rail_css])
        metrics = driver.js("""return (() => {
          const rail=document.querySelector('.aculta-rail');
          const rect=rail?.getBoundingClientRect();
          return {
            width:innerWidth,
            title:document.title,
            heading:Array.from(document.querySelectorAll('h1,h2'),x=>x.innerText.trim()).slice(0,5),
            railCount:document.querySelectorAll('.aculta-rail').length,
            role:rail?.getAttribute('role'),
            label:rail?.getAttribute('aria-label'),
            tabindex:rail?.getAttribute('tabindex'),
            cards:rail?.querySelectorAll('.views-row').length ?? 0,
            railClientWidth:rail?.clientWidth ?? 0,
            railScrollWidth:rail?.scrollWidth ?? 0,
            railScrollLeft:rail?.scrollLeft ?? 0,
            railHeight:rect?.height ?? 0,
            documentWidth:document.documentElement.scrollWidth
          };
        })();""")
        check(f"{width}px: página sem overflow", metrics["documentWidth"] <= width, json.dumps(metrics, ensure_ascii=False))
        check(f"{width}px: região rotulada e focável", metrics["railCount"] == 1 and metrics["role"] == "region" and bool(metrics["label"]) and metrics["tabindex"] == "0", json.dumps(metrics, ensure_ascii=False))
        if width in (320, 1440):
            image = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"courses-rail-{width}.png").write_bytes(base64.b64decode(image))
        if width == 320:
            check("320px: trilha realmente rolável", metrics["cards"] >= 2 and metrics["railScrollWidth"] > metrics["railClientWidth"], json.dumps(metrics, ensure_ascii=False))
            driver.js("document.querySelector('.aculta-rail').focus();")
            focused = driver.js("return document.activeElement === document.querySelector('.aculta-rail');")
            check("320px: região recebe foco", focused, str(focused))
            driver.req("POST", f"/session/{driver.session}/actions", {
                "actions": [{"type": "key", "id": "rail-keyboard", "actions": [
                    {"type": "keyDown", "value": "\ue014"},
                    {"type": "keyUp", "value": "\ue014"},
                ]}],
            })
            time.sleep(0.2)
            after = driver.js("return document.querySelector('.aculta-rail').scrollLeft;")
            check("320px: ArrowRight percorre a trilha", after > metrics["railScrollLeft"], f"scrollLeft {metrics['railScrollLeft']} → {after}")
            focus_metrics = rail_focus_metrics(driver)
            check("320px: foco de teclado tem indicador visível",
                  focus_metrics["focused"] and focus_metrics["focusVisible"]
                  and focus_metrics["outlineStyle"] != "none"
                  and focus_metrics["outlineWidth"] >= 3, json.dumps(focus_metrics, ensure_ascii=False))
            image = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / "courses-rail-320-after-arrow.png").write_bytes(base64.b64decode(image))
            test_zoom_400(driver, viewport)
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "page": PAGE,
    "widths": WIDTHS,
    "cssOverride": RAIL_CSS_OVERRIDE,
    "javascriptDisabled": RAIL_DISABLE_JS,
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
