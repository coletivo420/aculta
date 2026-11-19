#!/usr/bin/env python3
"""Check V2 footer composition and contrast in visible Firefox ESR."""

import base64
import json
import os
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com Firefox ESR visível em DISPLAY=:0.")

DOMAIN_ENV = os.environ.get("FOOTER_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("FOOTER_DOMAIN_ENV deve ser test ou production.")
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
WIDTHS = (320, 360, 390, 575, 576, 599, 600, 768, 991, 992, 1024, 1280, 1440)


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.sequence = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.sequence += 1
        command_id = self.sequence
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
        time.sleep(0.18)

    def close(self):
        self.socket.close()


driver = Driver(bidi=True, browser="firefox")
viewport = Viewport(driver)
checks = []
try:
    for purpose, url in PURPOSES:
        for mode in ("light", "dark"):
            driver.get(url)
            time.sleep(0.3)
            driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
            for width in WIDTHS:
                viewport.set(width)
                metrics = driver.js(r"""return (() => {
                  const footer=document.querySelector('.page__footer');
                  const region=footer?.querySelector('.page__footer-region');
                  const tracks=region?getComputedStyle(region).gridTemplateColumns.trim().split(/\s+/).length:0;
                  const style=footer?getComputedStyle(footer):null;
                  const rgb=value=>value.match(/[\d.]+/g)?.slice(0,3).map(Number)??[];
                  const luminance=value=>rgb(value).map(channel=>channel/255).map(channel=>
                    channel<=.04045?channel/12.92:((channel+.055)/1.055)**2.4
                  ).reduce((sum,channel,index)=>sum+channel*[.2126,.7152,.0722][index],0);
                  const background=style?.backgroundColor??'';
                  const foreground=style?.color??'';
                  const l1=luminance(background),l2=luminance(foreground);
                  const contrast=(Math.max(l1,l2)+.05)/(Math.min(l1,l2)+.05);
                  const links=[...(region?.querySelectorAll('nav a')??[])];
                  const minimumLinkHeight=links.length?Math.min(...links.map(link=>link.getBoundingClientRect().height)):0;
                  return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
                    footerCount:document.querySelectorAll('.page__footer').length,
                    footerWidth:footer?.getBoundingClientRect().width??0,
                    regionWidth:region?.clientWidth??0,regionScrollWidth:region?.scrollWidth??0,
                    columns:tracks,background,foreground,contrast:Number(contrast.toFixed(2)),
                    menuLinkCount:links.length,minimumLinkHeight:Math.ceil(minimumLinkHeight)};
                })();""")
                expected_columns = 1 if width < 576 else 2 if width < 992 else 4
                passed = (
                    metrics["width"] == width
                    and metrics["footerCount"] == 1
                    and metrics["columns"] == expected_columns
                    and metrics["footerWidth"] <= width
                    and metrics["documentWidth"] <= width
                    and metrics["regionScrollWidth"] <= metrics["regionWidth"]
                    and metrics["contrast"] >= 4.5
                    and metrics["menuLinkCount"] > 0
                    and metrics["minimumLinkHeight"] >= 44
                )
                result = {"purpose": purpose, "url": url, "mode": mode, "authState": "anonymous",
                          "width": width, "expectedColumns": expected_columns, "passed": passed, **metrics}
                checks.append(result)
                print(("PASS" if passed else "FAIL") + " " + json.dumps(result, ensure_ascii=False), flush=True)
                if purpose == "main" and width in (320, 1280):
                    driver.js("document.querySelector('.page__footer')?.scrollIntoView({block:'start',behavior:'instant'})")
                    time.sleep(0.15)
                    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    (OUT / f"footer-{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "environment": DOMAIN_ENV,
    "authState": "anonymous",
    "purposes": {purpose: url for purpose, url in PURPOSES},
    "widths": WIDTHS,
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [item for item in checks if not item["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
