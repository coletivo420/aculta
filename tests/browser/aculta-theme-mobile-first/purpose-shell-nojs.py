#!/usr/bin/env python3
"""Check the shared responsive shell without JavaScript across public purposes."""

import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import urlparse
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
WIDTHS = [int(value) for value in os.environ.get("PURPOSE_NOJS_WIDTHS", "320,390,599,600,768,1024,1440").split(",")]
DOMAIN_ENV = os.environ.get("PURPOSE_NOJS_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("PURPOSE_NOJS_DOMAIN_ENV deve ser test ou production.")

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
            "viewport": {"width": width, "height": 1000},
        })

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(prefs={"javascript.enabled": False}, bidi=True)
viewport = Viewport(driver)
try:
    for purpose, url in PURPOSES:
        for width in WIDTHS:
            viewport.set(width)
            driver.get(url)
            time.sleep(0.35)
            metrics = json.loads(driver.js("""return JSON.stringify((() => {
              const navbar=document.querySelector('.aculta-navbar');
              const toggle=navbar?.querySelector('.aculta-menu-toggle');
              const menu=navbar?.querySelector('#aculta-primary-menu');
              const wikiSearch=document.querySelector('.aculta-wiki-header-search');
              const wikiSearchInput=wikiSearch?.querySelector('input[type="search"]');
              const links=menu?.querySelectorAll('.nav-link').length ?? 0;
              return {
                title:document.title,
                width:innerWidth,
                documentWidth:document.documentElement.scrollWidth,
                institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
                domainHeader:document.querySelectorAll('.aculta-header').length,
                navbar:!!navbar,
                navigationReady:navbar?.classList.contains('aculta-navigation-ready') ?? false,
                toggleDisplay:toggle ? getComputedStyle(toggle).display : null,
                menuDisplay:menu ? getComputedStyle(menu).display : null,
                menuLinks:links,
                wikiSearchInputVisible:!!wikiSearchInput && !!(wikiSearchInput.offsetWidth || wikiSearchInput.offsetHeight)
              };
            })());"""))
            context = f"{purpose} {width}px"
            check(f"{context}: Institution Bar e Domain Header únicos",
                  metrics["institutionBar"] == 1 and metrics["domainHeader"] == 1, metrics)
            check(f"{context}: sem overflow horizontal",
                  metrics["documentWidth"] <= width and metrics["width"] == width, metrics)
            if metrics["navbar"]:
                if purpose == "wiki" and metrics["menuLinks"] == 0:
                    menu_visible = metrics["wikiSearchInputVisible"]
                else:
                    menu_visible = (not metrics["navigationReady"]
                                    and metrics["toggleDisplay"] == "none"
                                    and metrics["menuDisplay"] != "none"
                                    and metrics["menuLinks"] > 0)
                check(f"{context}: navegação ou busca da Wiki visível sem JavaScript",
                      menu_visible, metrics)
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "domainEnvironment": DOMAIN_ENV,
    "purposes": [purpose for purpose, _ in PURPOSES],
    "widths": WIDTHS,
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
