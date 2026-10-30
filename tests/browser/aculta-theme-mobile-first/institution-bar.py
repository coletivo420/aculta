#!/usr/bin/env python3
"""Responsive and cross-purpose checks for the global Institution Bar."""

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

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
WIDTHS = [320, 374, 375, 600, 767, 768, 1440]
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
DOMAIN_ENV = os.environ.get("INSTITUTION_BAR_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("INSTITUTION_BAR_DOMAIN_ENV deve ser test ou production.")
DEPLOY_CONFIG = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
domain_entries = json.loads(DEPLOY_CONFIG.read_text())["domain_transition"]["entries"]
domain_hosts = {
    entry["domain_id"]: entry[f"{DOMAIN_ENV}_host"] for entry in domain_entries
}
missing_domains = set(DOMAIN_IDS.values()) - set(domain_hosts)
if missing_domains:
    raise SystemExit(f"Faltam domínios no deploy.json: {', '.join(sorted(missing_domains))}")
PURPOSES = [
    (purpose, f"https://{domain_hosts[domain_id]}/")
    for purpose, domain_id in DOMAIN_IDS.items()
]
EXPECT = os.environ.get("INSTITUTION_BAR_EXPECT", "candidate")
CSS_OVERRIDE = os.environ.get("INSTITUTION_BAR_CSS_OVERRIDE") == "1"
checks = []
candidate = None
if CSS_OVERRIDE:
    candidate = [
        (ROOT / "web/themes/custom/aculta420/css/components/institution-bar.css").read_text(),
        (ROOT / "web/themes/custom/aculta420/css/components/header.css").read_text(),
    ]


def check(name, ok, detail=""):
    checks.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'} {name}" + (f" — {detail}" if detail else ""), flush=True)


class Viewport:
    def __init__(self, driver):
        self.driver = driver
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
        time.sleep(0.15)
        return int(self.driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


def apply_candidate(driver):
    if candidate is not None:
        driver.js("""for (const css of arguments[0]) {
          const style=document.createElement('style');
          style.dataset.institutionBarCandidate='true';
          style.textContent=css;document.head.append(style);
        }return true;""", [candidate])


def expected_geometry(width, purpose):
    mobile = width < 768
    narrow = width < 375
    if EXPECT == "baseline":
        expected = {
            "innerWrap": "nowrap" if mobile else "wrap",
            "innerGap": "2px" if mobile else "4px 16px",
            "logoWidth": "56px" if mobile else "78px",
            "logoHeight": "19px" if mobile else "26px",
            "utilityGap": "2px" if mobile else "8px",
            "utilityMinWidth": "0px" if mobile else "auto",
            "navWrap": "wrap",
            "navGap": "normal",
            "linkPadding": "16px",
            "fontSize": "13px",
            "whiteSpace": "normal",
            "brandPosition": "absolute" if narrow else "static",
            "brandVisible": not narrow,
        }
    else:
        expected = {
            "innerWrap": "wrap",
            "innerGap": "4px 8px" if mobile else "4px 16px",
            "logoWidth": "56px" if mobile else "78px",
            "logoHeight": "19px" if mobile else "26px",
            "utilityGap": "2px" if mobile else "8px",
            "utilityMinWidth": "0px" if mobile else "auto",
            "navWrap": "wrap",
            "navGap": "0px 4px" if mobile else "normal",
            "linkPadding": "2px" if mobile else "16px",
            "fontSize": "12px" if mobile else "13px",
            "whiteSpace": "nowrap" if mobile else "normal",
            "brandPosition": "static",
            "brandVisible": True,
        }
    # The social links landing intentionally hides the utility menu in Portal's
    # domain presentation; test the shell dimensions without assuming nav links.
    if purpose == "links":
        for key in ("utilityGap", "utilityMinWidth", "navWrap", "navGap",
                    "linkPadding", "fontSize", "whiteSpace"):
            expected.pop(key)
    return expected


def expected_domain_header(width):
    desktop = width >= 1100
    return {
        "brandingGrow": "0" if desktop else "1",
        "brandingShrink": "1",
        "brandingBasis": "22%" if desktop else "0px",
        "sloganWhiteSpace": "nowrap" if desktop else "normal",
    }


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    for purpose, url in PURPOSES:
        for width in WIDTHS:
            actual = viewport.set(width)
            driver.get(url)
            time.sleep(0.35)
            apply_candidate(driver)
            time.sleep(0.08)
            metrics = driver.js("""return (() => {
              const bar=document.querySelector('.aculta-institution-bar');
              const inner=bar?.querySelector('.aculta-institution-bar__inner');
              const brand=bar?.querySelector('.aculta-institution-bar__brand');
              const logo=bar?.querySelector('.aculta-institution-bar__logo');
              const utility=bar?.querySelector('.aculta-utility');
              const list=utility?.querySelector('ul.nav');
              const link=list?.querySelector('.nav-link');
              const header=document.querySelector('.aculta-header');
              const branding=header?.querySelector('.aculta-branding');
              const slogan=header?.querySelector('.site-slogan');
              const style=(element, property)=>element ? getComputedStyle(element).getPropertyValue(property) : null;
              return {
                width:innerWidth,
                bars:document.querySelectorAll('.aculta-institution-bar').length,
                innerWrap:style(inner,'flex-wrap'),
                innerGap:style(inner,'gap'),
                brandPosition:style(brand,'position'),
                brandVisible:!!brand && brand.getBoundingClientRect().width > 1 && brand.getBoundingClientRect().height > 1,
                logoWidth:style(logo,'width'),
                logoHeight:style(logo,'height'),
                utilityGap:style(utility,'gap'),
                utilityMinWidth:style(utility,'min-width'),
                navWrap:style(list,'flex-wrap'),
                navGap:style(list,'gap'),
                linkPadding:style(link,'padding-inline'),
                fontSize:style(link,'font-size'),
                whiteSpace:style(link,'white-space'),
                navLinks:list?.querySelectorAll('.nav-link').length ?? 0,
                headerCount:document.querySelectorAll('.aculta-header').length,
                brandingGrow:style(branding,'flex-grow'),
                brandingShrink:style(branding,'flex-shrink'),
                brandingBasis:style(branding,'flex-basis'),
                sloganWhiteSpace:style(slogan,'white-space'),
                documentWidth:document.documentElement.scrollWidth,
                domainHeaders:document.querySelectorAll('.aculta-header').length
              };
            })();""")
            present = actual == width and metrics["bars"] == 1 and metrics["documentWidth"] <= width
            check(f"{purpose} {width}px: Institution Bar e sem overflow", present,
                  json.dumps(metrics, ensure_ascii=False))

            if EXPECT in ("baseline", "candidate"):
                expected = expected_geometry(width, purpose)
                geometry = all(metrics.get(key) == value for key, value in expected.items())
                geometry = geometry and (purpose == "links" or metrics["navLinks"] > 0)
                check(f"{purpose} {width}px: geometria responsiva", geometry,
                      json.dumps(metrics, ensure_ascii=False))

            if purpose == "main" and metrics["headerCount"] > 0 and metrics["brandingGrow"] is not None:
                header_expected = expected_domain_header(width)
                header_geometry = all(
                    metrics.get(key) == value for key, value in header_expected.items()
                    if key != "sloganWhiteSpace" or metrics["sloganWhiteSpace"] is not None
                )
                check(f"{purpose} {width}px: geometria Domain Header", header_geometry,
                      json.dumps(metrics, ensure_ascii=False))

            if purpose in ("main", "support", "magazine") and width in (320, 768, 1440):
                image = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"institution-bar-{purpose}-{width}.png").write_bytes(base64.b64decode(image))

    (OUT / "results.json").write_text(json.dumps({
        "expected": EXPECT, "cssOverride": CSS_OVERRIDE, "domainEnv": DOMAIN_ENV,
        "purposes": PURPOSES,
        "widths": WIDTHS, "checks": checks,
    }, ensure_ascii=False, indent=2) + "\n")
finally:
    viewport.close()
    driver.close()

failed = [result for result in checks if not result["ok"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
