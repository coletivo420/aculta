#!/usr/bin/env python3
"""Read-only Firefox audit for Events agenda, month grid and interactive calendar."""

import base64
from datetime import date
import json
import os
from pathlib import Path
import sys
import time
from urllib.parse import urljoin

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
DEPLOY = json.loads((ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json").read_text())
DOMAIN_ENV = os.environ.get("CALENDAR_DOMAIN_ENV", "test")
if DOMAIN_ENV != "test":
    raise SystemExit("Este harness é somente para o host de teste; CALENDAR_DOMAIN_ENV deve ser test.")
domain = next(item for item in DEPLOY["domain_transition"]["entries"] if item["domain_id"] == "eventos_aculta_org")
BASE = os.environ.get("CALENDAR_BASE_URL", f"https://{domain['test_host']}").rstrip("/")
WIDTHS = [int(value) for value in os.environ.get(
    "CALENDAR_WIDTHS", "320,360,390,599,600,768,1024,1280,1440",
).split(",")]
MODES = [value.strip() for value in os.environ.get("CALENDAR_MODES", "light,dark").split(",")]
ROUTES = [value.strip() for value in os.environ.get(
    "CALENDAR_ROUTES",
    f"/calendario,/calendario/mes/{date.today().year}/{date.today().month},/calendario/interativo",
).split(",")]
if not all(route.startswith("/") and "?" not in route and "#" not in route for route in ROUTES):
    raise SystemExit("CALENDAR_ROUTES deve conter paths absolutos sem query ou fragmento.")
if not set(MODES) <= {"light", "dark"}:
    raise SystemExit("CALENDAR_MODES aceita somente light,dark.")

if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

MEASURE = r"""const root=document.documentElement, body=document.body;
const rect=(selector)=>{const e=document.querySelector(selector);if(!e)return null;
  const r=e.getBoundingClientRect();return {present:true,hidden:e.hidden,
    display:getComputedStyle(e).display,left:Math.round(r.left),right:Math.round(r.right),
    width:Math.round(r.width),height:Math.round(r.height),scrollWidth:e.scrollWidth,
    clientWidth:e.clientWidth};};
const month=document.querySelector('.aculta-mes');
const agenda=document.querySelector('#aculta-calendar-agenda');
const interactive=document.querySelector('#aculta-calendar-interactive');
return {title:document.title,url:location.pathname,width:innerWidth,
  documentWidth:root.scrollWidth,bodyWidth:body.scrollWidth,
  institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
  domainHeader:document.querySelectorAll('.aculta-header').length,
  main:document.querySelectorAll('main#main-content').length,
  monthGrid:!!month,monthColumns:month?.querySelectorAll('thead th').length||0,
  agendaDays:document.querySelectorAll('main#main-content h2').length,
  agendaItems:document.querySelectorAll('main#main-content h2 + ul li').length,
  agenda:rect('#aculta-calendar-agenda'),interactive:rect('#aculta-calendar-interactive'),
  interactiveInitialized:interactive?.dataset.acultaInit==='1',
  schedulerTheme:root.getAttribute('data-scheduler-theme'),
  schedulerBackgroundToken:getComputedStyle(root).getPropertyValue('--dhx-scheduler-base-colors-background').trim(),
  contrastButtonPressed:document.querySelector('#aculta-calendar-contrast')?.getAttribute('aria-pressed')||null,
  interactiveText:interactive?.innerText?.trim().length||0,
  agendaLink:[...document.querySelectorAll('a')].some(a=>a.textContent.trim()==='Ver a agenda em lista'),
  focusables:[...document.querySelectorAll('main#main-content a,main#main-content button,main#main-content input,main#main-content select')]
    .filter(e=>getComputedStyle(e).display!=='none'&&getComputedStyle(e).visibility!=='hidden').length};"""

records = []


class Viewport:
    def __init__(self, driver):
        from websockets.sync.client import connect
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


def record(name, passed, detail):
    records.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)


def run_mode(javascript_enabled):
    driver = Driver(prefs={"javascript.enabled": javascript_enabled}, bidi=True)
    viewport = Viewport(driver)
    try:
        for route in ROUTES:
            target = urljoin(BASE + "/", route.lstrip("/"))
            driver.get(target)
            initial = driver.js(MEASURE)
            if initial["url"] != route:
                record(f"{route}: rota de calendário carregada", False, {"actualPath": initial["url"], "expectedPath": route})
                continue
            if initial["main"] != 1 or initial["institutionBar"] != 1 or initial["domainHeader"] != 1:
                record(f"{route}: shell institucional único", False, {
                    "main": initial["main"], "institutionBar": initial["institutionBar"],
                    "domainHeader": initial["domainHeader"],
                })
            for mode in MODES:
                driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0]); return true", [mode])
                if javascript_enabled and route.endswith("/interativo") and mode == "dark":
                    driver.js("document.querySelector('#aculta-calendar-contrast')?.click(); return true")
                for width in WIDTHS:
                    viewport.set(width)
                    time.sleep(0.2)
                    data = driver.js(MEASURE)
                    is_interactive = route.endswith("/interativo")
                    if not is_interactive:
                        interactive_mode_ok = True
                    elif javascript_enabled and width < 768:
                        interactive_mode_ok = (
                            data["agenda"] is not None and not data["agenda"]["hidden"]
                            and data["interactive"] is not None and data["interactive"]["hidden"]
                        )
                    elif javascript_enabled:
                        interactive_mode_ok = (
                            data["agenda"] is not None and data["agenda"]["hidden"]
                            and data["interactive"] is not None and not data["interactive"]["hidden"]
                            and data["interactiveInitialized"]
                        )
                    else:
                        interactive_mode_ok = (
                            data["agenda"] is not None and not data["agenda"]["hidden"]
                            and data["agendaLink"] and data["interactive"] is not None
                            and data["interactive"]["height"] <= 1
                        )
                    structure_ok = (
                        data["main"] == 1 and data["institutionBar"] == 1 and data["domainHeader"] == 1
                        and (data["monthGrid"] is False or data["monthColumns"] == 7)
                        and (not is_interactive or data["agenda"] is not None)
                    )
                    dark_scheduler_ok = (
                        not (javascript_enabled and is_interactive and mode == "dark" and width >= 768)
                        or (data["schedulerTheme"] == "contrast-black"
                            and data["contrastButtonPressed"] == "true"
                            and data["schedulerBackgroundToken"].lower() == "#141414")
                    )
                    no_overflow = data["width"] == width and data["documentWidth"] <= width and data["bodyWidth"] <= width
                    passed = structure_ok and no_overflow and interactive_mode_ok and dark_scheduler_ok
                    record(f"{route} {mode} {width}px {'JS' if javascript_enabled else 'no-JS'}", passed, {
                        key: data[key] for key in (
                            "width", "documentWidth", "bodyWidth", "monthGrid", "monthColumns",
                            "agendaDays", "agendaItems", "agenda", "interactive",
                            "interactiveInitialized", "schedulerTheme", "schedulerBackgroundToken",
                            "contrastButtonPressed", "interactiveText", "agendaLink", "focusables",
                            "institutionBar", "domainHeader", "main",
                        )
                    })
                    if width in (320, 1440):
                        image = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
                        (OUT / f"{'js' if javascript_enabled else 'nojs'}-{route.strip('/').replace('/', '-') or 'home'}-{mode}-{width}.png").write_bytes(image)
    finally:
        viewport.close()
        driver.close()


run_mode(True)
run_mode(False)
(OUT / "summary.json").write_text(json.dumps({
    "base": BASE,
    "routes": ROUTES,
    "widths": WIDTHS,
    "modes": MODES,
    "checks": records,
}, ensure_ascii=False, indent=2) + "\n")
failed = [item for item in records if not item["passed"]]
print(f"\n{len(records) - len(failed)}/{len(records)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
