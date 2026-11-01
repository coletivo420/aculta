#!/usr/bin/env python3
"""Read-only Firefox check of the responsive footer across public purposes."""

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
WIDTHS = [int(width) for width in os.environ.get(
    "FOOTER_WIDTHS", "320,360,390,575,576,599,600,768,991,992,1024,1280,1440",
).split(",")]
DOMAIN_ENV = os.environ.get("FOOTER_DOMAIN_ENV", "test")
CSS_OVERRIDE = os.environ.get("FOOTER_CSS_OVERRIDE") == "1"
BASE_CSS_OVERRIDE = os.environ.get("FOOTER_BASE_CSS_OVERRIDE") == "1"
AUTH_CSS_OVERRIDE = os.environ.get("FOOTER_AUTH_CSS_OVERRIDE") == "1"
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
entries_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
entries = json.loads(entries_path.read_text())["domain_transition"]["entries"]
hosts = {entry["domain_id"]: entry[f"{DOMAIN_ENV}_host"] for entry in entries}
missing = set(DOMAIN_IDS.values()) - set(hosts)
if missing:
    raise SystemExit(f"Domínios ausentes em deploy.json: {', '.join(sorted(missing))}")
PURPOSES = [(purpose, f"https://{hosts[domain_id]}/") for purpose, domain_id in DOMAIN_IDS.items()]
if CSS_OVERRIDE:
    candidate_css = (ROOT / "web/themes/custom/aculta420/components/patterns/social-links-page/social-links-page.css").read_text()
if BASE_CSS_OVERRIDE:
    candidate_base_css = (ROOT / "web/themes/custom/aculta420/css/base.css").read_text()
if AUTH_CSS_OVERRIDE:
    candidate_auth_css = (ROOT / "web/themes/custom/aculta420/css/components/auth.css").read_text()
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
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 1000},
        })
        time.sleep(0.12)

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

measure = r"""const siteFooter=document.querySelector('.aculta-footer');
const region=siteFooter?.querySelector('.region-footer');
const navLinks=[...(region?.querySelectorAll('.nav-link')||[])];
const socialFooter=document.querySelector('.aculta-social-links-footer');
const socialInner=socialFooter?.querySelector('.aculta-social-links-footer__inner');
const socialList=socialFooter?.querySelector('.aculta-social-links__list');
const socialLinks=[...(socialFooter?.querySelectorAll('a[href]')||[])];
const socialIcons=[...(socialFooter?.querySelectorAll('[data-portal-ui*="social-links-icon"]')||[])];
const heights=navLinks.map(link=>parseFloat(getComputedStyle(link).minHeight));
const socialHeights=socialLinks.map(link=>parseFloat(getComputedStyle(link).minHeight));
const tracks=region?getComputedStyle(region).gridTemplateColumns.trim().split(/\s+/).length:0;
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
siteFooterCount:document.querySelectorAll('.aculta-footer').length,
siteFooterWidth:siteFooter?.getBoundingClientRect().width||0,
regionWidth:region?.clientWidth||0,regionScrollWidth:region?.scrollWidth||0,
columns:tracks,gridTemplateColumns:region?getComputedStyle(region).gridTemplateColumns:'',
navLinkCount:navLinks.length,minNavLinkHeight:heights.length?Math.min(...heights):0,
socialFooterCount:document.querySelectorAll('.aculta-social-links-footer').length,
socialFooterWidth:socialFooter?.getBoundingClientRect().width||0,
socialInnerWidth:socialInner?.clientWidth||0,
socialListWidth:socialList?.clientWidth||0,socialListScrollWidth:socialList?.scrollWidth||0,
socialLinkCount:socialLinks.length,minSocialLinkHeight:socialHeights.length?Math.min(...socialHeights):0,
socialIconCount:socialIcons.length,socialIconGlyph:socialIcons[0]?getComputedStyle(socialIcons[0],'::before').content:'',
institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
domainHeader:document.querySelectorAll('.aculta-header').length};"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    for purpose, url in PURPOSES:
        print(f"GET {purpose} {url}", flush=True)
        try:
            driver.get(url)
        except Exception as error:  # Continue other purposes, but never count a failed navigation as a pass.
            check(f"{purpose}: navegação da home pública", False, {"url": url, "error": str(error)[:240]})
            continue
        if BASE_CSS_OVERRIDE:
            driver.js("const style=document.createElement('style');style.textContent=arguments[0];document.head.append(style);return true;", [candidate_base_css])
        if AUTH_CSS_OVERRIDE:
            driver.js("const style=document.createElement('style');style.textContent=arguments[0];document.head.append(style);return true;", [candidate_auth_css])
        if CSS_OVERRIDE:
            driver.js("const style=document.createElement('style');style.textContent=arguments[0];document.head.append(style);return true;", [candidate_css])
        time.sleep(0.25)
        for width in WIDTHS:
            viewport.set(width)
            metrics = driver.js(measure)
            expected_columns = 1 if width < 576 else (2 if width < 992 else 4)
            exact_viewport = metrics["width"] == width
            shell_unique = metrics["institutionBar"] == 1 and metrics["domainHeader"] == 1
            if purpose == "links":
                footer_present = (metrics["socialFooterCount"] == 1 and metrics["socialLinkCount"] > 0
                                  and metrics["minSocialLinkHeight"] >= 44 and metrics["socialIconCount"] > 0
                                  and metrics["socialIconGlyph"] not in ("none", "normal", '""'))
                footer_width = metrics["socialFooterWidth"]
                footer_content_width = metrics["socialInnerWidth"]
                footer_content_scroll_width = metrics["socialListScrollWidth"]
                footer_detail = {key: metrics[key] for key in (
                    "width", "socialFooterCount", "socialInnerWidth", "socialLinkCount", "minSocialLinkHeight",
                    "socialIconCount", "socialIconGlyph")}
                footer_selector = ".aculta-social-links-footer"
            else:
                footer_present = (metrics["siteFooterCount"] == 1 and metrics["columns"] == expected_columns
                                  and metrics["navLinkCount"] > 0 and metrics["minNavLinkHeight"] >= 44)
                footer_width = metrics["siteFooterWidth"]
                footer_content_width = metrics["regionWidth"]
                footer_content_scroll_width = metrics["regionScrollWidth"]
                footer_detail = {key: metrics[key] for key in (
                    "width", "siteFooterCount", "columns", "gridTemplateColumns", "navLinkCount", "minNavLinkHeight")}
                footer_selector = ".aculta-footer"
            no_overflow = (metrics["documentWidth"] <= width and footer_width <= width
                           and footer_content_scroll_width <= footer_content_width)
            context = f"{purpose} {width}px"
            check(f"{context}: rodapé responsivo", exact_viewport and footer_present, footer_detail)
            check(f"{context}: shell íntegro e sem overflow", exact_viewport and shell_unique and no_overflow,
                  {"documentWidth": metrics["documentWidth"], "footerWidth": footer_width,
                   "footerContentWidth": footer_content_width, "footerContentScrollWidth": footer_content_scroll_width,
                   "institutionBar": metrics["institutionBar"], "domainHeader": metrics["domainHeader"]})
            if width in (320, 1440) and footer_present:
                driver.js(f"document.documentElement.style.scrollBehavior='auto';document.querySelector('{footer_selector}').scrollIntoView({{block:'start',behavior:'auto'}})")
                time.sleep(0.25)
                png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
                (OUT / f"{purpose}-{width}.png").write_bytes(png)
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "domainEnvironment": DOMAIN_ENV,
    "cssOverride": CSS_OVERRIDE,
    "baseCssOverride": BASE_CSS_OVERRIDE,
    "authCssOverride": AUTH_CSS_OVERRIDE,
    "purposes": [purpose for purpose, _ in PURPOSES],
    "widths": WIDTHS,
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
