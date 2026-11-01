#!/usr/bin/env python3
"""Verify the real public Events cards and mobile grid with a candidate stylesheet."""

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

DOMAIN_ENV = os.environ.get("EVENTS_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("EVENTS_DOMAIN_ENV deve ser test ou production.")
deploy_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
entries = json.loads(deploy_path.read_text())["domain_transition"]["entries"]
entry = next((item for item in entries if item["domain_id"] == "eventos_aculta_org"), None)
if entry is None:
    raise SystemExit("Domain eventos_aculta_org não existe no manifesto.")
PAGE = os.environ.get("EVENTS_PAGE", f"https://{entry[f'{DOMAIN_ENV}_host']}/")
if urlparse(PAGE).scheme != "https" or urlparse(PAGE).hostname != entry[f"{DOMAIN_ENV}_host"]:
    raise SystemExit("EVENTS_PAGE deve usar o host de Eventos configurado no deploy.json.")
CSS = (ROOT / "web/themes/custom/aculta420/components/content/event_card/event_card.css").read_text()
WIDTHS = [320, 360, 390, 575, 576, 600, 768, 1024, 1280, 1440]
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
            "viewport": {"width": width, "height": 1000},
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
        time.sleep(0.7)
        driver.js("""const style=document.createElement('style');
          style.dataset.eventCardCandidate='true';
          style.textContent=arguments[0];document.head.append(style);return true;""", [CSS])
        time.sleep(0.1)
        metrics = driver.js("""return (() => {
          const list=document.querySelector('.event-catalogue__list > ul');
          const items=Array.from(list?.querySelectorAll(':scope > li.list-group-item') ?? []);
          const first=items.find(item=>item.querySelector(':scope > article.event-card'));
          const wrapper=first;
          const card=wrapper?.querySelector(':scope > article.event-card');
          const style=(el,name)=>el ? getComputedStyle(el).getPropertyValue(name) : null;
          return {
            width:innerWidth,
            cards:document.querySelectorAll('.event-catalogue__list article.event-card').length,
            grid:style(list,'grid-template-columns'),
            wrapperBorder:style(wrapper,'border-width'),
            wrapperPadding:style(wrapper,'padding'),
            wrapperBackground:style(wrapper,'background-color'),
            cardBorder:style(card,'border-width'),
            cardWidth:card?.getBoundingClientRect().width ?? 0,
            titleTargets:[...document.querySelectorAll('.event-catalogue__list .event-card__titulo a')]
              .map(link=>{const rect=link.getBoundingClientRect();return {
                width:Math.round(rect.width),height:Math.round(rect.height),
                text:link.textContent.trim()};}),
            documentWidth:document.documentElement.scrollWidth
          };
        })();""")
        check(f"{width}px: cards reais e página sem overflow", metrics["cards"] > 0 and metrics["documentWidth"] <= width, json.dumps(metrics, ensure_ascii=False))
        check(f"{width}px: moldura somente no SDC", metrics["wrapperBorder"] == "0px" and metrics["wrapperPadding"] == "0px" and metrics["cardBorder"] == "1px", json.dumps(metrics, ensure_ascii=False))
        check(f"{width}px: títulos-link têm alvo mínimo de 44px",
              bool(metrics["titleTargets"]) and all(target["height"] >= 44 for target in metrics["titleTargets"]),
              json.dumps(metrics["titleTargets"], ensure_ascii=False))
        if width in (320, 768, 1440):
            image = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"events-catalogue-{width}.png").write_bytes(base64.b64decode(image))
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "page": PAGE, "widths": WIDTHS, "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
