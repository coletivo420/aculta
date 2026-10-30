#!/usr/bin/env python3
"""Check public-purpose shell tokens in light/dark and reduced-motion modes."""

import base64
import json
import os
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
WIDTHS = [int(value) for value in os.environ.get("PURPOSE_MODE_WIDTHS", "320,360,390,599,600,768,1024,1280,1440").split(",")]
DOMAIN_ENV = os.environ.get("PURPOSE_MODE_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("PURPOSE_MODE_DOMAIN_ENV deve ser test ou production.")

DOMAIN_IDS = {
    "main": "aculta_org",
    "account": "conta_aculta_org",
    "support": "apoio_aculta_org",
    "magazine": "revista420_aculta_org",
    "wiki": "wiki420_aculta_org",
    "shop": "loja_aculta_org",
    "courses": "cursos_aculta_org",
    "forum": "forum_aculta_org",
    "links": "links_aculta_org",
    "eventos": "eventos_aculta_org",
}
deploy_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
entries = json.loads(deploy_path.read_text())["domain_transition"]["entries"]
hosts = {item["domain_id"]: item[f"{DOMAIN_ENV}_host"] for item in entries}
missing = set(DOMAIN_IDS.values()) - set(hosts)
if missing:
    raise SystemExit(f"Domínios ausentes em deploy.json: {', '.join(sorted(missing))}")
PURPOSES = [(purpose, f"https://{hosts[domain_id]}/") for purpose, domain_id in DOMAIN_IDS.items()]
MODES = {"light": ("#f2f7f0", "#0c3c29"), "dark": ("#171513", "#f4efe8")}
checks = []


def check(name, passed, detail):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)


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
            "viewport": {"width": width, "height": 1000 if width >= 1000 else 844},
        })

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(prefs={"ui.prefersReducedMotion": 1}, bidi=True)
viewport = Viewport(driver)
try:
    for purpose, url in PURPOSES:
        for width in WIDTHS:
            viewport.set(width)
            driver.get(url)
            time.sleep(0.3)
            for mode, (expected_surface, expected_text) in MODES.items():
                driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0]); return true", [mode])
                time.sleep(0.05)
                metrics = json.loads(driver.js("""return JSON.stringify((() => {
                  const root=getComputedStyle(document.documentElement);
                  const bar=document.querySelector('.aculta-institution-bar');
                  const header=document.querySelector('.aculta-header');
                  return {
                    title:document.title,
                    mode:document.documentElement.getAttribute('data-bs-theme'),
                    width:innerWidth,
                    documentWidth:document.documentElement.scrollWidth,
                    surface:root.getPropertyValue('--aculta-surface-page').trim(),
                    primaryText:root.getPropertyValue('--aculta-text-primary').trim(),
                    institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
                    domainHeader:document.querySelectorAll('.aculta-header').length,
                    reducedMotion:matchMedia('(prefers-reduced-motion: reduce)').matches,
                    barBackground:bar ? getComputedStyle(bar).backgroundColor : null,
                    headerBackground:header ? getComputedStyle(header).backgroundColor : null
                  };
                })());"""))
                context = f"{purpose} {mode} {width}px"
                check(f"{context}: tokens e reduced motion",
                      metrics["mode"] == mode
                      and metrics["surface"] == expected_surface
                      and metrics["primaryText"] == expected_text
                      and metrics["reducedMotion"], metrics)
                check(f"{context}: shell único e sem overflow",
                      metrics["width"] == width
                      and metrics["documentWidth"] <= width
                      and metrics["institutionBar"] == 1
                      and metrics["domainHeader"] == 1, metrics)
                if width in (320, 1440):
                    shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    (OUT / f"{purpose}-{mode}-{width}.png").write_bytes(base64.b64decode(shot))
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "domainEnvironment": DOMAIN_ENV,
    "purposes": [purpose for purpose, _ in PURPOSES],
    "widths": WIDTHS,
    "modes": list(MODES),
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
