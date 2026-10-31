#!/usr/bin/env python3
"""Probe visible keyboard focus on the ten public purpose roots."""

import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
CODE_ROOT = HERE.parents[2]
ROOT = Path(os.environ.get("ACULTA_PROJECT_ROOT", CODE_ROOT)).resolve()
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

WIDTHS = [int(value) for value in os.environ.get("PURPOSE_KEYBOARD_WIDTHS", "320,390,768,1280").split(",")]
TABS = int(os.environ.get("PURPOSE_KEYBOARD_TABS", "8"))
DOMAIN_ENV = os.environ.get("PURPOSE_KEYBOARD_DOMAIN_ENV", "test")
SELECTED_PURPOSES = {value.strip() for value in os.environ.get("PURPOSE_KEYBOARD_PURPOSES", "").split(",") if value.strip()}
AUTH_CANDIDATE = os.environ.get("PURPOSE_KEYBOARD_AUTH_CANDIDATE") == "1"
DIAGNOSTICS = os.environ.get("PURPOSE_KEYBOARD_DIAGNOSTICS") == "1"
AUTH_CSS = CODE_ROOT / "web/themes/custom/aculta420/css/components/auth.css"
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
TAB = "\ue004"
SHIFT = "\ue008"
records = []


def drush(*args):
    return subprocess.check_output(
        ["php", "vendor/drush/drush/drush.php", *args], cwd=ROOT, text=True,
    ).strip()


def verify_runtime():
    host = subprocess.check_output(["hostname"], text=True).strip()
    status = drush("status", "--fields=uri,root,db-name")
    if host != "toca" or "aculta_runtime" not in status or str(ROOT / "web") not in status:
        raise RuntimeError("Teste permitido somente em toca/aculta_runtime.")


def purpose_urls():
    if DOMAIN_ENV not in ("test", "production"):
        raise RuntimeError("PURPOSE_KEYBOARD_DOMAIN_ENV deve ser test ou production.")
    config = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
    entries = json.loads(config.read_text())["domain_transition"]["entries"]
    hosts = {item["domain_id"]: item[f"{DOMAIN_ENV}_host"] for item in entries}
    missing = set(DOMAIN_IDS.values()) - set(hosts)
    if missing:
        raise RuntimeError(f"Domínios ausentes em deploy.json: {', '.join(sorted(missing))}")
    purposes = [(purpose, f"https://{hosts[domain_id]}/") for purpose, domain_id in DOMAIN_IDS.items()]
    unknown = SELECTED_PURPOSES - set(DOMAIN_IDS)
    if unknown:
        raise RuntimeError(f"Purposes desconhecidos: {', '.join(sorted(unknown))}")
    if SELECTED_PURPOSES:
        purposes = [(purpose, url) for purpose, url in purposes if purpose in SELECTED_PURPOSES]
    return purposes


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
            "viewport": {"width": width, "height": 900 if width < 768 else 1000},
        })

    def close(self):
        self.socket.close()


def press_tab(driver, reverse=False):
    actions = []
    if reverse:
        actions.append({"type": "keyDown", "value": SHIFT})
    actions.extend([{"type": "keyDown", "value": TAB}, {"type": "keyUp", "value": TAB}])
    if reverse:
        actions.append({"type": "keyUp", "value": SHIFT})
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{"type": "key", "id": "purpose-shell-keyboard", "actions": actions}],
    })


def inspect(driver):
    return json.loads(driver.js("""const el=document.activeElement, style=getComputedStyle(el), r=el.getBoundingClientRect();
const ring=el.closest('.lms-course-card')||el, ringStyle=getComputedStyle(ring);
const captcha=el.closest('.captcha'),captchaStyle=captcha?getComputedStyle(captcha):null,
  captchaRect=captcha?.getBoundingClientRect();
return JSON.stringify({
  tag:el.tagName, id:el.id, className:typeof el.className==='string'?el.className:'',
  focusTarget:el!==document.body&&el!==document.documentElement,
  tabIndex:el.tabIndex, role:el.getAttribute('role'), ariaLabel:el.getAttribute('aria-label'),
  parentClass:el.parentElement?.className?.toString?.()||'',
  captchaAncestor:el.closest('.captcha, .captcha-type-challenge--turnstile')?.className||'',
  captchaContainsTarget:!!el.closest('.captcha')?.contains(el),
  focusPseudoMatches:el.matches(':focus'),
  captchaFocusWithin:captcha?.matches(':focus-within')||false,
  captchaOutlineStyle:captchaStyle?.outlineStyle||'none',
  captchaOutlineWidth:captchaStyle?.outlineWidth||'0px',
  captchaOutlineColor:captchaStyle?.outlineColor||'transparent',
  captchaLeft:captchaRect?Math.round(captchaRect.left):null,
  captchaRight:captchaRect?Math.round(captchaRect.right):null,
  captchaTop:captchaRect?Math.round(captchaRect.top):null,
  captchaBottom:captchaRect?Math.round(captchaRect.bottom):null,
  focusVisible:el.matches(':focus-visible'), outlineStyle:style.outlineStyle,
  outlineWidth:style.outlineWidth, outlineColor:style.outlineColor, boxShadow:style.boxShadow,
  indicatorClass:typeof ring.className==='string'?ring.className:'',
  indicatorOutlineStyle:ringStyle.outlineStyle, indicatorOutlineWidth:ringStyle.outlineWidth,
  indicatorBoxShadow:ringStyle.boxShadow,
  left:Math.round(r.left), right:Math.round(r.right), top:Math.round(r.top), bottom:Math.round(r.bottom),
  width:Math.round(r.width), height:Math.round(r.height), viewportWidth:innerWidth,
  viewportHeight:innerHeight, documentWidth:document.documentElement.scrollWidth,
  documentClientWidth:document.documentElement.clientWidth,
  bodyScrollWidth:document.body.scrollWidth, visualViewportWidth:visualViewport?.width,
  htmlOverflowX:getComputedStyle(document.documentElement).overflowX,
  bodyOverflowX:getComputedStyle(document.body).overflowX,
  scrollY,
  scrollingElements:arguments[0]&&document.documentElement.scrollWidth>innerWidth
    ?Array.from(document.querySelectorAll('body *')).map(node=>{
      const r=node.getBoundingClientRect(),s=getComputedStyle(node);
      return {tag:node.tagName,id:node.id,className:typeof node.className==='string'?node.className:'',
        left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),
        scrollWidth:node.scrollWidth,clientWidth:node.clientWidth,overflowX:s.overflowX,
        position:s.position,insideHiddenOffcanvas:!!node.closest('.offcanvas:not(.show)')};
    }).filter(x=>x.scrollWidth>x.clientWidth+1).sort((a,b)=>b.scrollWidth-a.scrollWidth).slice(0,20)
    :[],
  overflowElements:arguments[0]&&document.documentElement.scrollWidth>innerWidth
    ?Array.from(document.querySelectorAll('body *')).map(node=>{
      const r=node.getBoundingClientRect(),s=getComputedStyle(node);
      return {tag:node.tagName,id:node.id,className:typeof node.className==='string'?node.className:'',
        left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),
        scrollWidth:node.scrollWidth,clientWidth:node.clientWidth,overflowX:s.overflowX,
        position:s.position,display:s.display,
        insideHiddenOffcanvas:!!node.closest('.offcanvas:not(.show)')};
    }).filter(x=>x.width>0&&(x.left < -1||x.right > innerWidth+1)
      &&!x.insideHiddenOffcanvas)
      .sort((a,b)=>b.right-a.right).slice(0,20)
    :[]
});""", [DIAGNOSTICS]))


def apply_auth_candidate(driver, purpose):
    """Inject candidate account CSS only in this browser session for the login route."""
    if not AUTH_CANDIDATE or purpose != "account":
        return
    if "path-entrar" not in driver.js("return document.body.className"):
        return
    marker_count = driver.js("""const style=document.createElement('style');
style.dataset.purposeAuthCandidate='true';style.textContent=arguments[0];document.head.append(style);
let count=0;document.querySelectorAll('.captcha').forEach((frame)=>{
  const tokens=(frame.getAttribute('data-portal-ui')||'').split(/\\s+/).filter(Boolean);
  if(!tokens.includes('challenge-frame'))tokens.push('challenge-frame');
  frame.setAttribute('data-portal-ui',tokens.join(' '));count++;
});return count;""", [AUTH_CSS.read_text(encoding="utf-8")])
    record("account /entrar: CSS e marcador candidatos aplicados na sessão",
           marker_count > 0, {"challengeMarkers": marker_count})


def record(name, passed, detail):
    item = {"name": name, "status": "pass" if passed else "fail", "passed": bool(passed), "detail": detail}
    records.append(item)
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use Firefox ESR visível com DISPLAY=:0.")
    if not 1 <= TABS <= 20:
        raise RuntimeError("PURPOSE_KEYBOARD_TABS deve estar entre 1 e 20.")
    verify_runtime()
    driver = Driver(bidi=True)
    viewport = Viewport(driver)
    try:
        for purpose, url in purpose_urls():
            for width in WIDTHS:
                viewport.set(width)
                driver.get(url)
                time.sleep(0.35)
                apply_auth_candidate(driver, purpose)
                driver.js("window.scrollTo(0,0);document.activeElement?.blur();")
                page_state = inspect(driver)
                no_horizontal_overflow = page_state["documentWidth"] <= width
                record(f"{purpose} {width}px: sem overflow horizontal",
                       no_horizontal_overflow,
                       {"documentWidth": page_state["documentWidth"],
                        "documentClientWidth": page_state["documentClientWidth"],
                        "bodyScrollWidth": page_state["bodyScrollWidth"],
                        "visualViewportWidth": page_state["visualViewportWidth"],
                        "htmlOverflowX": page_state["htmlOverflowX"],
                        "bodyOverflowX": page_state["bodyOverflowX"],
                        "scrollingElements": page_state["scrollingElements"],
                        "viewportWidth": width,
                        "overflowElements": page_state["overflowElements"] if not no_horizontal_overflow else []})
                for reverse in (False, True):
                    direction = "shift-tab" if reverse else "tab"
                    if reverse:
                        driver.get(url)
                        time.sleep(0.35)
                        apply_auth_candidate(driver, purpose)
                        driver.js("window.scrollTo({top:0,behavior:'instant'});document.activeElement?.blur();")
                        time.sleep(0.25)
                    for step in range(1, TABS + 1):
                        press_tab(driver, reverse=reverse)
                        time.sleep(0.15)
                        info = inspect(driver)
                        if not info["focusTarget"]:
                            records.append({"name": f"{purpose} {width}px {direction}: limite da sequência de foco",
                                            "status": "skip", "passed": None, "detail": info})
                            print(f"SKIP {purpose} {width}px {direction}: limite da sequência de foco", flush=True)
                            break
                        if info["captchaAncestor"]:
                            records.append({"name": f"{purpose} {width}px {direction} {step}: foco delegado ao desafio externo",
                                            "status": "skip", "passed": None, "detail": info})
                            print(f"SKIP {purpose} {width}px {direction} {step}: foco delegado ao desafio CAPTCHA externo",
                                  flush=True)
                            continue
                        visible_indicator = (
                            info["indicatorOutlineStyle"] != "none"
                            and info["indicatorOutlineWidth"] not in ("0px", "0px 0px")
                        ) or info["indicatorBoxShadow"] != "none"
                        in_viewport = (
                            info["width"] > 0 and info["height"] > 0
                            and info["left"] < width + 1 and info["right"] > -1
                            and info["top"] < info["viewportHeight"] and info["bottom"] > 0
                        )
                        passed = info["focusVisible"] and visible_indicator and in_viewport
                        name = f"{purpose} {width}px {direction} {step}: foco visível no viewport"
                        record(name, passed, info)
                        if not reverse and step == 1 and width == WIDTHS[0]:
                            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                            (OUT / f"{purpose}-focus-{width}.png").write_bytes(base64.b64decode(screenshot))
    finally:
        viewport.close()
        driver.close()

    (OUT / "results.json").write_text(json.dumps({
        "runtime": "aculta_runtime", "domainEnvironment": DOMAIN_ENV,
        "widths": WIDTHS, "tabsPerDirection": TABS,
        "purposes": [purpose for purpose, _ in purpose_urls()], "checks": records,
    }, ensure_ascii=False, indent=2) + "\n")
    failed = [item for item in records if item["status"] != "skip" and not item["passed"]]
    passed = sum(item["passed"] is True for item in records)
    skipped = sum(item["status"] == "skip" for item in records)
    print(f"\n{passed}/{len(records) - skipped} checks passed; {skipped} sequence limits observed; evidence: {OUT}")
    raise SystemExit(1 if failed else 0)


if __name__ == "__main__":
    main()
