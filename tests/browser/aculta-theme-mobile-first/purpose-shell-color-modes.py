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
SELECTED_PURPOSES = {
    value.strip() for value in os.environ.get("PURPOSE_MODE_PURPOSES", "").split(",")
    if value.strip()
}
unknown_purposes = SELECTED_PURPOSES - set(DOMAIN_IDS)
if unknown_purposes:
    raise SystemExit(f"PURPOSE_MODE_PURPOSES contém purpose(s) desconhecido(s): {', '.join(sorted(unknown_purposes))}")
if SELECTED_PURPOSES:
    PURPOSES = [(purpose, url) for purpose, url in PURPOSES if purpose in SELECTED_PURPOSES]
MODES = {"light": ("#f2f7f0", "#0c3c29"), "dark": ("#171513", "#f4efe8")}
AUTH_CANDIDATE = os.environ.get("PURPOSE_MODE_AUTH_CANDIDATE") == "1"
AUTH_CSS = ROOT / "web/themes/custom/aculta420/css/components/auth.css"
checks = []


def check(name, passed, detail):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)


def auth_google_metrics(driver):
    return driver.js(r"""const element=document.querySelector('a[data-portal-ui="auth-google"]');
if(!element)return null;
const style=getComputedStyle(element), rect=element.getBoundingClientRect();
const luminance=color=>{
  const channels=color.match(/[\d.]+/g)?.slice(0,3).map(value=>Number(value)/255);
  if(!channels||channels.length!==3)return null;
  const linear=channels.map(value=>value<=.04045?value/12.92:((value+.055)/1.055)**2.4);
  return .2126*linear[0]+.7152*linear[1]+.0722*linear[2];
};
const foreground=luminance(style.color), background=luminance(style.backgroundColor);
return {visible:element.getClientRects().length>0&&style.display!=='none'&&style.visibility!=='hidden',
  hovered:element.matches(':hover'),foreground:style.color,background:style.backgroundColor,
  x:rect.x+rect.width/2,y:rect.y+rect.height/2,
  contrast:foreground===null||background===null?null:
    Math.round((Math.max(foreground,background)+.05)/(Math.min(foreground,background)+.05)*100)/100};""")


def move_pointer(driver, x, y):
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "pointer", "id": "auth-google-contrast", "parameters": {"pointerType": "mouse"},
        "actions": [{"type": "pointerMove", "duration": 0, "x": round(x), "y": round(y)}],
    }]})


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
            auth_marker_count = 0
            auth_css_injected = False
            if AUTH_CANDIDATE and purpose == "account" and "path-entrar" in driver.js("return document.body.className"):
                driver.js("""const style=document.createElement('style');
                  style.dataset.purposeAuthCandidate='true';style.textContent=arguments[0];
                  document.head.append(style);return true;""", [AUTH_CSS.read_text(encoding="utf-8")])
                auth_css_injected = True
                auth_marker_count = driver.js("""let count=0;
                  document.querySelectorAll('.captcha').forEach((frame)=>{
                    const tokens=(frame.getAttribute('data-portal-ui')||'').split(/\\s+/).filter(Boolean);
                    if(!tokens.includes('challenge-frame'))tokens.push('challenge-frame');
                    frame.setAttribute('data-portal-ui',tokens.join(' '));count++;
                  });return count;""")
            for mode, (expected_surface, expected_text) in MODES.items():
                driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0]); return true", [mode])
                time.sleep(0.05)
                metrics = json.loads(driver.js("""return JSON.stringify((() => {
                  const root=getComputedStyle(document.documentElement);
                  const bar=document.querySelector('.aculta-institution-bar');
                  const header=document.querySelector('.aculta-header');
                  const colorChannels=(color)=>{
                    const parts=color.match(/[\\d.]+/g)?.slice(0,3).map(Number);
                    if(!parts||parts.length!==3)return null;
                    return parts;
                  };
                  const luminance=(channels)=>{
                    if(!channels||channels.length!==3)return null;
                    const linear=channels.map(value=>{
                      const channel=value/255;
                      return channel<=0.04045?channel/12.92:((channel+0.055)/1.055)**2.4;
                    });
                    return 0.2126*linear[0]+0.7152*linear[1]+0.0722*linear[2];
                  };
                  const shellTextLinks=[...document.querySelectorAll(
                    '.aculta-institution-bar a,.aculta-header a'
                  )].filter(link=>link.innerText.trim()).map(link=>{
                    const shell=link.closest('.aculta-institution-bar,.aculta-header');
                    const foreground=getComputedStyle(link).color;
                    const shellBackground=getComputedStyle(shell).backgroundColor;
                    const linkBackground=getComputedStyle(link).backgroundColor;
                    const shellChannels=colorChannels(shellBackground);
                    const linkParts=linkBackground.match(/[\\d.]+/g)?.map(Number);
                    const alpha=linkParts?.[3]??1;
                    const backgroundChannels=shellChannels&&linkParts
                      ? shellChannels.map((value,index)=>(linkParts[index]??0)*alpha+value*(1-alpha))
                      : null;
                    const background=backgroundChannels
                      ? `rgb(${backgroundChannels.map(value=>Math.round(value)).join(', ')})`
                      : shellBackground;
                    const fg=luminance(colorChannels(foreground)),bg=luminance(backgroundChannels);
                    return {label:link.innerText.trim().replace(/\\s+/g,' '),foreground,background,
                      contrast:fg===null||bg===null?null:
                        Math.round((Math.max(fg,bg)+0.05)/(Math.min(fg,bg)+0.05)*100)/100};
                  });
                  return {
                    title:document.title,
                    mode:document.documentElement.getAttribute('data-bs-theme'),
                    width:innerWidth,
                    documentWidth:document.documentElement.scrollWidth,
                    surface:root.getPropertyValue('--aculta-surface-page').trim(),
                    primaryText:root.getPropertyValue('--aculta-text-primary').trim(),
                    authCssInjected:arguments[0],authMarkerCount:arguments[1],
                    institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
                    domainHeader:document.querySelectorAll('.aculta-header').length,
                    reducedMotion:matchMedia('(prefers-reduced-motion: reduce)').matches,
                    barBackground:bar ? getComputedStyle(bar).backgroundColor : null,
                    headerBackground:header ? getComputedStyle(header).backgroundColor : null,
                    shellTextLinks,
                    overflowElements:innerWidth === 320 && location.host.startsWith('conta.')
                      ? [...document.querySelectorAll('body *')].map(el=>{
                          const rect=el.getBoundingClientRect(), css=getComputedStyle(el);
                          return {tag:el.tagName.toLowerCase(),id:el.id,className:String(el.className||''),
                            left:Math.round(rect.left*100)/100,right:Math.round(rect.right*100)/100,
                            contentRight:Math.round((rect.left+el.scrollWidth)*100)/100,
                            width:Math.round(rect.width*100)/100,clientWidth:el.clientWidth,
                            scrollWidth:el.scrollWidth,position:css.position,display:css.display,
                            overflowX:css.overflowX,whiteSpace:css.whiteSpace,
                            insideOffcanvas:!!el.closest('#aculta-cart-offcanvas'),
                            visuallyHidden:!!el.closest('.visually-hidden,.visually-hidden-focusable')};
                        }).filter(item=>item.display!=='none'
                          && item.position!=='fixed' && !item.insideOffcanvas && !item.visuallyHidden
                          && item.contentRight>innerWidth+1)
                          .slice(0,20)
                      : []
                  };
                })());""", [auth_css_injected, auth_marker_count]))
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
                check(f"{context}: links textuais do shell com contraste AA",
                      bool(metrics["shellTextLinks"])
                      and all(link["contrast"] is not None and link["contrast"] >= 4.5
                              for link in metrics["shellTextLinks"]), metrics["shellTextLinks"])
                if purpose == "account" and driver.js("return document.body.classList.contains('path-entrar')"):
                    google = auth_google_metrics(driver)
                    check(f"{context}: botão Google com contraste AA",
                          bool(google and google["visible"] and google["contrast"] is not None
                               and google["contrast"] >= 4.5), google)
                    if google and google["visible"]:
                        move_pointer(driver, google["x"], google["y"])
                        hovered = auth_google_metrics(driver)
                        check(f"{context}: botão Google em hover com contraste AA",
                              bool(hovered and hovered["hovered"] and hovered["contrast"] is not None
                                   and hovered["contrast"] >= 4.5), hovered)
                        move_pointer(driver, 1, 1)
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
