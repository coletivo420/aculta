#!/usr/bin/env python3
"""Check the real Courses rail reflow and native keyboard scrolling."""

import base64
import json
import os
from pathlib import Path
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
checks = []


def check(name, passed, detail):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {detail}", flush=True)


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

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    for width in WIDTHS:
        viewport.set(width)
        driver.get(PAGE)
        time.sleep(0.8)
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
            image = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / "courses-rail-320-after-arrow.png").write_bytes(base64.b64decode(image))
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({"page": PAGE, "widths": WIDTHS, "checks": checks}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
