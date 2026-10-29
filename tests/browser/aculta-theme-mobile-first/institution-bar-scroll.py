#!/usr/bin/env python3
"""Visible Firefox regression for the mobile Institution Bar while scrolling."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", ROOT))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
DOMAIN_ENV = os.environ.get("INSTITUTION_BAR_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("INSTITUTION_BAR_DOMAIN_ENV deve ser test ou production.")
DEPLOY = json.loads((ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json").read_text())
MAIN = next(entry for entry in DEPLOY["domain_transition"]["entries"] if entry["domain_id"] == "aculta_org")
BASE_OVERRIDE = os.environ.get("INSTITUTION_BAR_SCROLL_BASE_URL")
BASE = (BASE_OVERRIDE or f"https://{MAIN[f'{DOMAIN_ENV}_host']}").rstrip("/")
LOGIN_URI = os.environ.get("INSTITUTION_BAR_LOGIN_URI", BASE).rstrip("/")
ADMIN_UID = int(os.environ.get("INSTITUTION_BAR_ADMIN_UID", "1"))
WIDTHS = [320, 390, 1023, 1024, 1280]
ANONYMOUS_WIDTHS = [320, 390]
ANONYMOUS_TARGETS = [
    (entry["domain_id"], f"https://{entry[f'{DOMAIN_ENV}_host']}")
    for entry in DEPLOY["domain_transition"]["entries"]
]
if BASE_OVERRIDE:
    ANONYMOUS_TARGETS = [("custom", BASE)]
domain_filter = {item.strip() for item in os.environ.get("INSTITUTION_BAR_SCROLL_DOMAINS", "").split(",") if item.strip()}
if domain_filter:
    ANONYMOUS_TARGETS = [target for target in ANONYMOUS_TARGETS if target[0] in domain_filter]
PURPOSE_CONTEXT = "main"
SCENARIOS = {item.strip() for item in os.environ.get("INSTITUTION_BAR_SCROLL_SCENARIOS", "anonymous,admin").split(",") if item.strip()}
DISABLE_JS = os.environ.get("INSTITUTION_BAR_SCROLL_DISABLE_JS") == "1"
results = []


def record(name, passed, detail):
    name = f"{PURPOSE_CONTEXT}: {name}"
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    keys = ('width', 'scrollY', 'barTop', 'barPosition', 'displacementTop',
            'toolbarCount', 'loggedIn', 'readyState', 'routePrefix',
            'loginForms', 'messageCount', 'maxTopChange', 'settledBarTop',
            'aboveToolbar', 'expectedTop', 'documentWidth', 'displacementElements',
            'topBarCount', 'topBarPosition', 'topBarBottom')
    summary = {key: detail[key] for key in keys if isinstance(detail, dict) and key in detail}
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(summary, ensure_ascii=False)}", flush=True)


def admin_login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    process = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", "/", "--no-browser", f"--uid={ADMIN_UID}", f"--uri={LOGIN_URI}"],
        cwd=PROJECT,
        check=True,
        capture_output=True,
        text=True,
        timeout=30,
        env=env,
    )
    return process.stdout.strip().splitlines()[-1]


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(prefs={"javascript.enabled": False} if DISABLE_JS else {}, bidi=True)
socket = connect(driver.websocket_url)
sequence = 0

def set_viewport(width):
    global sequence
    sequence += 1
    request_id = sequence
    socket.send(json.dumps({
        "id": request_id,
        "method": "browsingContext.getTree",
        "params": {},
    }))
    while True:
        message = json.loads(socket.recv())
        if message.get("id") == request_id:
            context = message["result"]["contexts"][0]["context"]
            break
    sequence += 1
    request_id = sequence
    socket.send(json.dumps({
        "id": request_id,
        "method": "browsingContext.setViewport",
        "params": {"context": context, "viewport": {"width": width, "height": 800}},
    }))
    while True:
        message = json.loads(socket.recv())
        if message.get("id") == request_id:
            break
    time.sleep(0.2)
    return int(driver.js("return innerWidth"))


def apply_candidate():
    if os.environ.get("INSTITUTION_BAR_SCROLL_CSS_OVERRIDE") == "1":
        css = (ROOT / "web/themes/custom/aculta420/css/components/institution-bar.css").read_text()
        driver.js("""const style=document.createElement('style');
          style.dataset.institutionBarScrollCandidate='true';
          style.textContent=arguments[0];document.head.append(style);return true;""", [css])
    if os.environ.get("INSTITUTION_BAR_SCROLL_JS_OVERRIDE") == "1" and not DISABLE_JS:
        js = (ROOT / "web/themes/custom/aculta420/js/navigation-top-bar-displace.js").read_text()
        driver.js("""const script=document.createElement('script');
          script.dataset.institutionBarScrollCandidate='true';
          script.textContent=arguments[0];document.head.append(script);
          Drupal.behaviors.aculta420NavigationTopBarDisplace.attach(document, drupalSettings);
          return true;""", [js])


def metrics():
    return driver.js("""return (() => {
      const bar=document.querySelector('.aculta-institution-bar');
      const rect=bar?.getBoundingClientRect();
      const toolbar=document.querySelector('#toolbar-administration');
      const toolbarRect=toolbar?.getBoundingClientRect();
      const topBar=document.querySelector('.top-bar[data-drupal-admin-styles]');
      const topBarRect=topBar?.getBoundingClientRect();
      const ancestors=[];
      for (let node=bar?.parentElement; node && ancestors.length<8; node=node.parentElement) {
        const css=getComputedStyle(node);
        const box=node.getBoundingClientRect();
        ancestors.push({tag:node.tagName.toLowerCase(),className:String(node.className||''),
          position:css.position,overflowX:css.overflowX,overflowY:css.overflowY,
          top:Math.round(box.top*100)/100,bottom:Math.round(box.bottom*100)/100,
          height:Math.round(box.height*100)/100});
      }
      const number=(value)=>Number.parseFloat(value)||0;
      const style=bar ? getComputedStyle(bar) : null;
      const displacementElements=[...document.querySelectorAll('[data-offset-top]')].map(el=>{
        const css=getComputedStyle(el), box=el.getBoundingClientRect();
        return {tag:el.tagName.toLowerCase(),id:el.id,className:String(el.className||''),
          value:el.getAttribute('data-offset-top'),position:css.position,display:css.display,
          top:Math.round(box.top*100)/100,height:Math.round(box.height*100)/100};
      });
      return {
        width:innerWidth,scrollY:scrollY,viewportHeight:innerHeight,
        documentWidth:document.documentElement.scrollWidth,
        documentHeight:document.documentElement.scrollHeight,
        barCount:document.querySelectorAll('.aculta-institution-bar').length,
        barTop:rect ? Math.round(rect.top*100)/100 : null,
        barBottom:rect ? Math.round(rect.bottom*100)/100 : null,
        barPosition:style?.position||null,barTopStyle:style?.top||null,
        displacementTop:number(getComputedStyle(document.documentElement).getPropertyValue('--drupal-displace-offset-top')),
        drupalTop:window.Drupal?.displace?.offsets?.top ?? null,
        toolbarCount:document.querySelectorAll('#toolbar-administration').length,
        toolbarTop:toolbarRect ? Math.round(toolbarRect.top*100)/100 : null,
        toolbarBottom:toolbarRect ? Math.round(toolbarRect.bottom*100)/100 : null,
        topBarCount:document.querySelectorAll('.top-bar[data-drupal-admin-styles]').length,
        topBarPosition:topBar ? getComputedStyle(topBar).position : null,
        topBarTop:topBarRect ? Math.round(topBarRect.top*100)/100 : null,
        topBarBottom:topBarRect ? Math.round(topBarRect.bottom*100)/100 : null,
        displacementElements:displacementElements,
        bodyClasses:document.body.className,
        ancestors:ancestors
      };
    })();""")


def run_scenario(scenario, login_url=None):
    if login_url:
        driver.get(login_url)
        deadline = time.monotonic() + 12
        auth_state = None
        while time.monotonic() < deadline:
            auth_state = driver.js("""return {
              loggedIn:document.body.classList.contains('user-logged-in'),
              toolbarCount:document.querySelectorAll('#toolbar-administration').length,
              host:location.host,
              routePrefix:location.pathname.split('/').filter(Boolean).slice(0,2),
              routeSegmentCount:location.pathname.split('/').filter(Boolean).length,
              loginForms:document.querySelectorAll('#user-login-form, #user-pass').length,
              messageCount:document.querySelectorAll('[role="alert"], .messages').length,
              readyState:document.readyState
            };""")
            if auth_state["loggedIn"]:
                break
            time.sleep(0.2)
        authenticated = bool(auth_state and auth_state["loggedIn"])
        record(f"{scenario}: autenticação confirmada", authenticated, auth_state)
        if not authenticated:
            return
    for width in WIDTHS:
        actual_width = set_viewport(width)
        driver.get(f"{BASE}/")
        time.sleep(0.8)
        apply_candidate()
        time.sleep(0.1)
        initial = metrics()
        record(f"{scenario} {width}px: shell inicial", actual_width == width and initial["barCount"] == 1,
               initial)
        if scenario == "admin":
            admin_bar_present = initial["toolbarCount"] == 1 or initial["topBarCount"] == 1
            record(f"{scenario} {width}px: barra administrativa presente",
                   admin_bar_present and "user-logged-in" in initial["bodyClasses"], initial)
            expected_displacement = initial["topBarBottom"] if initial["topBarPosition"] == "fixed" else 0
            displacement_correct = True
            if not DISABLE_JS:
                displacement_correct = abs(initial["displacementTop"] - expected_displacement) <= 2
            initial_flow_top = initial["topBarBottom"] if initial["topBarCount"] else 0
            displacement_correct = displacement_correct and abs(initial["barTop"] - initial_flow_top) <= 2
            record(f"{scenario} {width}px: deslocamento compatível com a barra",
                   displacement_correct, initial)
            if width == 320:
                desktop_width = set_viewport(1024)
                time.sleep(0.3)
                desktop_metrics = metrics()
                desktop_correct = (
                    desktop_width == 1024
                    and desktop_metrics["topBarPosition"] == "fixed"
                    and desktop_metrics["topBarBottom"] > 0
                )
                if not DISABLE_JS:
                    desktop_correct = desktop_correct and abs(desktop_metrics["displacementTop"] - desktop_metrics["topBarBottom"]) <= 2
                record(f"{scenario}: deslocamento ao cruzar para desktop",
                       desktop_correct, desktop_metrics)
                mobile_width = set_viewport(320)
                time.sleep(0.3)
                mobile_metrics = metrics()
                mobile_correct = (
                    mobile_width == 320
                    and mobile_metrics["topBarPosition"] != "fixed"
                )
                if not DISABLE_JS:
                    mobile_correct = mobile_correct and mobile_metrics["displacementTop"] <= 2
                record(f"{scenario}: deslocamento ao voltar para mobile",
                       mobile_correct, mobile_metrics)
        else:
            record(f"{scenario} {width}px: sem toolbar", initial["toolbarCount"] == 0, initial)

        timeline = []
        for delay in (0.05, 0.2, 0.5, 1.0):
            time.sleep(delay)
            sample = metrics()
            timeline.append(sample)
        settled = timeline[-1]
        final_offset = settled["displacementTop"]
        jump = max(abs(sample["barTop"] - settled["barTop"]) for sample in timeline)
        record(f"{scenario} {width}px: barra estabiliza no carregamento",
               jump <= 2,
               {"maxTopChange": jump, "settledBarTop": settled["barTop"],
                "displacementTop": final_offset, "timeline": timeline})

        max_scroll = max(0, settled["documentHeight"] - settled["viewportHeight"] - 2)
        for target in (80, 240, 480):
            if target > max_scroll:
                continue
            driver.js("window.scrollTo(0, arguments[0]); return true;", [target])
            time.sleep(0.25)
            sample = metrics()
            intended_top = 0
            if sample["topBarPosition"] == "fixed" and sample["topBarBottom"] is not None:
                intended_top = sample["topBarBottom"]
            elif sample["toolbarBottom"] is not None:
                intended_top = sample["toolbarBottom"]
            expected_y = max(intended_top, initial["barTop"] - sample["scrollY"])
            stuck = sample["barPosition"] == "sticky" and abs(sample["barTop"] - expected_y) <= 2
            toolbar_bottom = sample["toolbarBottom"] or 0
            if sample["topBarPosition"] == "fixed" and sample["topBarBottom"] is not None:
                toolbar_bottom = max(toolbar_bottom, sample["topBarBottom"])
            top_bar_clear = sample["topBarPosition"] != "fixed" or sample["barTop"] >= toolbar_bottom - 2
            legacy_toolbar_clear = sample["toolbarBottom"] is None or sample["barTop"] >= sample["toolbarBottom"] - 2
            above_toolbar = top_bar_clear and legacy_toolbar_clear
            record(f"{scenario} {width}px: fixa após scroll {target}px", stuck and above_toolbar,
                   {**sample, "expectedTop": expected_y, "aboveToolbar": above_toolbar})
            if target == 240 and scenario == "anonymous":
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"institution-bar-scroll-{scenario}-{PURPOSE_CONTEXT}-{width}.png").write_bytes(
                    base64.b64decode(screenshot)
                )


try:
    if "anonymous" in SCENARIOS:
        original_base, original_widths = BASE, WIDTHS
        for purpose, host in ANONYMOUS_TARGETS:
            BASE, WIDTHS, PURPOSE_CONTEXT = host.rstrip("/"), ANONYMOUS_WIDTHS, purpose
            run_scenario("anonymous")
        BASE, WIDTHS = original_base, original_widths
    if "admin" in SCENARIOS:
        BASE = (BASE_OVERRIDE or f"https://{MAIN[f'{DOMAIN_ENV}_host']}").rstrip("/")
        PURPOSE_CONTEXT = "main-admin"
        run_scenario("admin", admin_login_url())
    (OUT / "results.json").write_text(json.dumps({
        "base": BASE,
        "anonymousTargets": [purpose for purpose, _ in ANONYMOUS_TARGETS],
        "loginHost": LOGIN_URI.split("//", 1)[-1].split("/", 1)[0],
        "domainEnvironment": DOMAIN_ENV,
        "adminUid": ADMIN_UID,
        "cssOverride": os.environ.get("INSTITUTION_BAR_SCROLL_CSS_OVERRIDE") == "1",
        "jsOverride": os.environ.get("INSTITUTION_BAR_SCROLL_JS_OVERRIDE") == "1",
        "javascriptDisabled": DISABLE_JS,
        "widths": WIDTHS,
        "results": results,
    }, ensure_ascii=False, indent=2) + "\n")
finally:
    socket.close()
    driver.close()

failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)}/{len(results)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
