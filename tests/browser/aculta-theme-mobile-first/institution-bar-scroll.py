#!/usr/bin/env python3
"""Visible Firefox regression for the mobile Institution Bar while scrolling."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit
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
ACCOUNT = next(entry for entry in DEPLOY["domain_transition"]["entries"] if entry["domain_id"] == "conta_aculta_org")
BASE_OVERRIDE = os.environ.get("INSTITUTION_BAR_SCROLL_BASE_URL")
BASE = (BASE_OVERRIDE or f"https://{MAIN[f'{DOMAIN_ENV}_host']}").rstrip("/")
LOGIN_URI = os.environ.get("INSTITUTION_BAR_LOGIN_URI", f"https://{ACCOUNT[f'{DOMAIN_ENV}_host']}").rstrip("/")
DRUSH_URI = os.environ.get("INSTITUTION_BAR_DRUSH_URI", "http://default").rstrip("/")
ADMIN_UID = int(os.environ.get("INSTITUTION_BAR_ADMIN_UID", "1"))
ADMIN_LOGIN_USER = os.environ.get("INSTITUTION_BAR_ADMIN_LOGIN_USER", "")
ADMIN_LOGIN_PASSWORD = os.environ.get("INSTITUTION_BAR_ADMIN_LOGIN_PASSWORD", "")
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
SIMULATE_STALE_DISPLACEMENT = os.environ.get("INSTITUTION_BAR_SCROLL_STALE_DISPLACEMENT_TEST") == "1"
results = []
stale_displacement_skips = []
first_scroll_skips = []


def record(name, passed, detail):
    name = f"{PURPOSE_CONTEXT}: {name}"
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    keys = ('width', 'scrollY', 'barTop', 'barPosition', 'displacementTop',
            'toolbarCount', 'loggedIn', 'readyState', 'routePrefix',
            'loginForms', 'messageCount', 'maxTopChange', 'settledBarTop',
            'aboveToolbar', 'expectedTop', 'documentWidth', 'displacementElements',
            'topBarCount', 'topBarPosition', 'topBarDisplay', 'topBarVisibility', 'topBarBottom',
            'topBarInlineStyle', 'mobileBreakpointMatches',
            'candidateCssPresent',
            'barHeight', 'placeholderCount', 'placeholderHeight', 'topBarZIndex', 'shellScrolled', 'sampleCount',
            'maxDeviation', 'failures')
    summary = {key: detail[key] for key in keys if isinstance(detail, dict) and key in detail}
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(summary, ensure_ascii=False)}", flush=True)


def admin_login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    process = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", "/", "--no-browser", f"--uid={ADMIN_UID}", f"--uri={DRUSH_URI}"],
        cwd=PROJECT,
        check=True,
        capture_output=True,
        text=True,
        timeout=30,
        env=env,
    )
    generated = urlsplit(process.stdout.strip().splitlines()[-1])
    browser = urlsplit(LOGIN_URI)
    if not browser.scheme or not browser.netloc:
        raise RuntimeError("INSTITUTION_BAR_LOGIN_URI deve conter URL HTTPS completa.")
    return urlunsplit((browser.scheme, browser.netloc, generated.path, generated.query, generated.fragment))


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
        driver.js("""const existing=document.querySelector('style[data-institution-bar-scroll-candidate]');
          const style=existing||document.createElement('style');
          style.dataset.institutionBarScrollCandidate='true';
          style.textContent=arguments[0];if(!existing)document.head.append(style);return true;""", [css])
    if os.environ.get("INSTITUTION_BAR_SCROLL_JS_OVERRIDE") == "1" and not DISABLE_JS:
        if not driver.js("return !!(window.Drupal && window.once)"):
            return False
        js = (ROOT / "web/themes/custom/aculta420/js/navigation-top-bar-displace.js").read_text()
        driver.js("""const script=document.createElement('script');
          script.dataset.institutionBarScrollCandidate='true';
          script.textContent=arguments[0];document.head.append(script);
          once.remove('aculta420NavigationTopBarDisplaceListener', 'html');
          Drupal.behaviors.aculta420NavigationTopBarDisplace.attach(document, drupalSettings);
          return true;""", [js])
    return True


def metrics():
    return driver.js("""return (() => {
      const bar=document.querySelector('.aculta-institution-bar');
      const rect=bar?.getBoundingClientRect();
      const toolbar=document.querySelector('#toolbar-administration');
      const toolbarRect=toolbar?.getBoundingClientRect();
      const topBar=document.querySelector('.top-bar[data-drupal-admin-styles]');
      const candidateCss=document.querySelector('style[data-institution-bar-scroll-candidate]');
      const topBarRect=topBar?.getBoundingClientRect();
      const placeholder=document.querySelector('.aculta-institution-bar-placeholder');
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
        topBarZIndex:topBar ? getComputedStyle(topBar).zIndex : null,
        topBarDisplay:topBar ? getComputedStyle(topBar).display : null,
        topBarVisibility:topBar ? getComputedStyle(topBar).visibility : null,
        topBarInlineStyle:topBar?.getAttribute('style')||null,
        mobileBreakpointMatches:matchMedia('(max-width: 63.999rem)').matches,
        candidateCssPresent:Boolean(candidateCss),
        topBarTop:topBarRect ? Math.round(topBarRect.top*100)/100 : null,
        topBarBottom:topBarRect ? Math.round(topBarRect.bottom*100)/100 : null,
        barHeight:rect ? Math.round(rect.height*100)/100 : null,
        placeholderCount:document.querySelectorAll('.aculta-institution-bar-placeholder').length,
        placeholderHeight:placeholder ? Math.round(placeholder.getBoundingClientRect().height*100)/100 : 0,
        shellScrolled:document.documentElement.classList.contains('aculta-institution-bar-scrolled'),
        topBarFocusWithin:Boolean(topBar && topBar.contains(document.activeElement)),
        activeElement:document.activeElement ? document.activeElement.tagName + (document.activeElement.className ? '.' + String(document.activeElement.className).replace(/\\s+/g, '.') : '') : null,
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
            if auth_state["loggedIn"] or auth_state["routePrefix"][:1] == ["recuperar-acesso"]:
                break
            time.sleep(0.2)
        if not (auth_state and auth_state["loggedIn"]) and ADMIN_LOGIN_USER and ADMIN_LOGIN_PASSWORD:
            # Some development accounts are routed through account recovery
            # instead of consuming a one-time ULI link. Fall back to the
            # dedicated synthetic administrator credentials supplied by the
            # caller; never include credentials in test output or artifacts.
            driver.get(f"{LOGIN_URI}/entrar")
            form_found = driver.exists("#user-login-form input[name='name']") and driver.exists(
                "#user-login-form input[name='pass']"
            )
            if form_found:
                driver.type("#user-login-form input[name='name']", ADMIN_LOGIN_USER)
                driver.type("#user-login-form input[name='pass']", ADMIN_LOGIN_PASSWORD)
                driver.click("#user-login-form input[type='submit']")
                deadline = time.monotonic() + 12
                while time.monotonic() < deadline:
                    auth_state = driver.js("""return {
                      loggedIn:document.body.classList.contains('user-logged-in'),
                      toolbarCount:document.querySelectorAll('#toolbar-administration').length,
                      host:location.host,
                      routePrefix:location.pathname.split('/').filter(Boolean).slice(0,2),
                      routeSegmentCount:location.pathname.split('/').filter(Boolean).length,
                      loginForms:document.querySelectorAll('#user-login-form, #user-pass').length,
                      messageCount:document.querySelectorAll('[role=alert], .messages').length,
                      readyState:document.readyState,
                      authMethod:'password fallback'
                    };""")
                    if auth_state["loggedIn"]:
                        break
                    time.sleep(0.2)
            if auth_state is None:
                auth_state = {"loggedIn": False, "authMethod": "password fallback", "loginFormFound": form_found}
            else:
                auth_state["authMethod"] = "password fallback"
                auth_state["loginFormFound"] = form_found
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
        if scenario == "admin" and width in (320, 390):
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"institution-bar-scroll-admin-initial-{width}.png").write_bytes(
                base64.b64decode(screenshot)
            )
        if initial["barCount"] != 1 or initial["barTop"] is None:
            # A missing/error page is already a failed shell check. Keep
            # collecting evidence from the remaining purposes and widths.
            continue
        if scenario == "admin":
            admin_bar_present = initial["toolbarCount"] == 1 or initial["topBarCount"] == 1
            record(f"{scenario} {width}px: barra administrativa presente",
                   admin_bar_present and "user-logged-in" in initial["bodyClasses"], initial)
            expected_displacement = initial["topBarBottom"] if initial["topBarPosition"] == "fixed" else 0
            displacement_correct = True
            if not DISABLE_JS:
                displacement_correct = abs(initial["displacementTop"] - expected_displacement) <= 2
            initial_flow_top = (
                initial["topBarBottom"]
                if initial["topBarCount"] and initial["topBarDisplay"] != "none"
                else initial["barTop"]
            )
            displacement_correct = displacement_correct and abs(initial["barTop"] - initial_flow_top) <= 2
            record(f"{scenario} {width}px: deslocamento compatível com a barra",
                   displacement_correct, initial)
            if scenario == "admin" and width <= 1023:
                record(f"{scenario} {width}px: ações administrativas superiores desativadas",
                       initial["topBarCount"] == 0 or initial["topBarDisplay"] == "none",
                       initial)
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
                if os.environ.get("INSTITUTION_BAR_SCROLL_CSS_OVERRIDE") == "1":
                    apply_candidate()
                time.sleep(0.3)
                mobile_metrics = metrics()
                mobile_correct = (
                    mobile_width == 320
                    and mobile_metrics["topBarPosition"] != "fixed"
                    and mobile_metrics["topBarDisplay"] == "none"
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
        if width <= 390 and not DISABLE_JS and max_scroll > 0:
            driver.js("window.scrollTo({top: 1, behavior: 'instant'}); return true;")
            time.sleep(0.08)
            immediate = metrics()
            admin_actions_hidden = (
                immediate["topBarDisplay"] == "none"
                if immediate["topBarCount"] else immediate["topBarDisplay"] is None
            )
            expected_placeholder_count = 1 if initial["barTop"] <= 2 else 0
            expected_placeholder_height = initial["barHeight"] if expected_placeholder_count else 0
            record(f"{scenario} {width}px: Institution Bar fixa e ações administrativas móveis desativadas",
                   immediate["scrollY"] > 0 and immediate["barPosition"] == "fixed"
                   and abs(immediate["barTop"]) <= 2 and immediate["shellScrolled"]
                   and admin_actions_hidden
                   and immediate["placeholderCount"] == expected_placeholder_count
                   and abs(immediate["placeholderHeight"] - expected_placeholder_height) <= 2,
                   {**immediate, "expectedPlaceholderHeight": expected_placeholder_height})
            if scenario == "admin" and width in (320, 390):
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"institution-bar-scroll-admin-first-scroll-{width}.png").write_bytes(
                    base64.b64decode(screenshot)
                )
            driver.js("window.scrollTo({top: 0, behavior: 'instant'}); return true;")
            time.sleep(0.08)
            restored = metrics()
            record(f"{scenario} {width}px: barra e ações restauradas no topo",
                   restored["scrollY"] == 0
                   and restored["barPosition"] == "sticky"
                   and abs(restored["barTop"] - initial["barTop"]) <= 2
                   and not restored["shellScrolled"]
                   and restored["placeholderCount"] == 0
                   and restored["topBarDisplay"] == initial["topBarDisplay"]
                   and (restored["topBarDisplay"] in ("none", None)),
                   restored)
        elif width <= 390 and not DISABLE_JS:
            first_scroll_skips.append({
                "context": PURPOSE_CONTEXT,
                "width": width,
                "reason": "document has no vertical scroll range",
            })
            print(f"SKIP {PURPOSE_CONTEXT} {width}px: primeiro scroll (documento sem rolagem vertical)", flush=True)

        simulated_target = min(max_scroll, max(320, initial["barTop"] + 24))
        if SIMULATE_STALE_DISPLACEMENT and width == 320:
            if simulated_target >= initial["barTop"] + 20:
                stale_metrics = driver.js("""return (() => {
                  const root=document.documentElement;
                  const previous=root.style.getPropertyValue('--drupal-displace-offset-top');
                  const priority=root.style.getPropertyPriority('--drupal-displace-offset-top');
                  const start=window.scrollY;
                  root.style.setProperty('--drupal-displace-offset-top','64px');
                  const target=Math.min(arguments[0],Math.max(320,arguments[1]+24));
                  window.scrollTo({top:target,behavior:'instant'});
                  const bar=document.querySelector('.aculta-institution-bar');
                  const rect=bar?.getBoundingClientRect();
                  const result={target,scrollY:window.scrollY,
                    barTop:rect ? Math.round(rect.top*100)/100 : null,
                    computedTop:bar ? getComputedStyle(bar).top : null,
                    simulatedDisplacement:getComputedStyle(root).getPropertyValue('--drupal-displace-offset-top').trim()};
                  if (previous) root.style.setProperty('--drupal-displace-offset-top',previous,priority);
                  else root.style.removeProperty('--drupal-displace-offset-top');
                  window.scrollTo({top:start,behavior:'instant'});
                  return result;
                })();""", [max_scroll, initial["barTop"]])
                record(f"{scenario} 320px: top mobile ignora deslocamento Drupal stale simulado",
                       stale_metrics["computedTop"] == "0px" and stale_metrics["barTop"] is not None
                       and abs(stale_metrics["barTop"]) <= 2,
                       stale_metrics)
            else:
                stale_displacement_skips.append({
                    "context": PURPOSE_CONTEXT,
                    "width": 320,
                    "reason": "document height does not provide enough scroll to reach sticky threshold",
                })
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
            if sample["barPosition"] == "fixed":
                expected_y = 0
            stuck = sample["barPosition"] in ("sticky", "fixed") and abs(sample["barTop"] - expected_y) <= 2
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
            elif target == 240 and scenario == "admin" and width in (320, 390):
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"institution-bar-scroll-admin-scrolled-{width}.png").write_bytes(
                    base64.b64decode(screenshot)
                )

        # Probe the sticky threshold in both directions at mobile widths. The
        # coarse checkpoints above can miss a transient mid-screen position.
        if width <= 390 and max_scroll > 0:
            sequence_end = min(320, int(max_scroll))
            targets = list(range(0, sequence_end + 1, 12))
            if not targets or targets[-1] != sequence_end:
                targets.append(sequence_end)
            targets.extend(reversed(targets[:-1]))
            transition = driver.js("""
              return arguments[0].map((target) => {
                window.scrollTo({top: target, behavior: 'instant'});
                const bar = document.querySelector('.aculta-institution-bar');
                const topBar = document.querySelector('.top-bar[data-drupal-admin-styles]');
                const toolbar = document.querySelector('#toolbar-administration');
                const rect = bar?.getBoundingClientRect();
                const topRect = topBar?.getBoundingClientRect();
                const toolbarRect = toolbar?.getBoundingClientRect();
                return {
                  requestedScrollY: target,
                  scrollY: window.scrollY,
                  barTop: rect ? rect.top : null,
                  barPosition: bar ? getComputedStyle(bar).position : null,
                  topBarPosition: topBar ? getComputedStyle(topBar).position : null,
                  topBarBottom: topRect ? topRect.bottom : null,
                  toolbarBottom: toolbarRect ? toolbarRect.bottom : null
                };
              });
            """, [targets])
            deviations = []
            max_deviation = 0
            for sample in transition:
                offset = 0
                if sample["topBarPosition"] == "fixed" and sample["topBarBottom"] is not None:
                    offset = sample["topBarBottom"]
                elif sample["toolbarBottom"] is not None:
                    offset = sample["toolbarBottom"]
                expected_top = 0 if sample["barPosition"] == "fixed" else max(offset, initial["barTop"] - sample["scrollY"])
                deviation = abs(sample["barTop"] - expected_top) if sample["barTop"] is not None else float("inf")
                max_deviation = max(max_deviation, deviation)
                if sample["barPosition"] not in ("sticky", "fixed") or deviation > 2:
                    deviations.append({**sample, "expectedTop": expected_top, "deviation": deviation})
            record(f"{scenario} {width}px: transição sticky contínua para baixo e para cima",
                   not deviations,
                   {"sampleCount": len(transition), "maxDeviation": max_deviation,
                    "failures": deviations[:5]})


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
        "staleDisplacementSimulation": SIMULATE_STALE_DISPLACEMENT,
        "staleDisplacementSkips": stale_displacement_skips,
        "firstScrollSkips": first_scroll_skips,
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
