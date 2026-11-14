#!/usr/bin/env python3
"""Exercise V2's Drupal SDC navigation in visible Firefox ESR."""

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
DEPLOY_CONFIG = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
domain_ids = {
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
environment = os.environ.get("NAVIGATION_DOMAIN_ENV", "test")
if environment not in ("test", "production"):
    raise SystemExit("NAVIGATION_DOMAIN_ENV deve ser test ou production.")
entries = json.loads(DEPLOY_CONFIG.read_text())["domain_transition"]["entries"]
hosts = {entry["domain_id"]: entry[f"{environment}_host"] for entry in entries}
purposes = [(purpose, f"https://{hosts[domain_id]}/") for purpose, domain_id in domain_ids.items()]
focal = os.environ.get("NAVIGATION_PURPOSE")
if focal:
    purposes = [(purpose, url) for purpose, url in purposes if purpose == focal]
    if not purposes:
        raise SystemExit(f"Purpose desconhecido: {focal}")
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")


class Viewport:
    def __init__(self, driver):
        self.driver = driver
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
        time.sleep(0.15)
        return int(self.driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


checks = []


def check(label, passed, detail=None):
    checks.append({"label": label, "passed": bool(passed), "detail": detail})
    suffix = f" — {detail}" if detail is not None else ""
    print(f"{'PASS' if passed else 'FAIL'} {label}{suffix}", flush=True)


def wait_for(driver, predicate, timeout=2):
    deadline = time.time() + timeout
    while time.time() < deadline:
        if predicate():
            return True
        time.sleep(0.05)
    return bool(predicate())


def inspect(driver, viewport, url, width):
    actual_width = viewport.set(width)
    driver.get(url)
    time.sleep(0.35)
    return driver.js("""return (() => {
      const root=document.querySelector('.navigation-bar');
      const panel=root?.querySelector('[data-aculta-navigation-panel]');
      const toggle=root?.querySelector('[data-aculta-navigation-toggle]');
      const link=panel?.querySelector('a[href]');
      const style=(element,property)=>element?getComputedStyle(element).getPropertyValue(property):null;
      return {
        root:!!root,
        panel:!!panel,
        toggle:!!toggle,
        toggleDisplay:style(toggle,'display'),
        panelVisibility:style(panel,'visibility'),
        panelDisplay:style(panel,'display'),
        expanded:toggle?.getAttribute('aria-expanded')??null,
        links:panel?.querySelectorAll('a[href]').length??0,
        linkHeight:link?Math.ceil(link.getBoundingClientRect().height):0,
        brandCount:root?.querySelectorAll('.navigation-bar__brand').length??0,
        brandImage:!!root?.querySelector('.navigation-bar__brand img'),
        brandImageLoaded:(root?.querySelector('.navigation-bar__brand img')?.naturalWidth??0)>0,
        navigationInDomainHeader:!!document.querySelector('.page__header')?.contains(root),
        institutionBars:document.querySelectorAll('.aculta-institution-bar').length,
        domainHeaders:document.querySelectorAll('.page__header').length,
        navigationLandmarks:Array.from(root?.querySelectorAll('nav,[role="navigation"]')??[]).map((landmark)=>{
          const labelledBy=landmark.getAttribute('aria-labelledby');
          return landmark.getAttribute('aria-label')?.trim()
            || (labelledBy?document.getElementById(labelledBy)?.textContent?.trim():null)
            || '';
        }),
        documentWidth:document.documentElement.scrollWidth,
        width:innerWidth,
        requestedWidth:arguments[0]
      };
    })();""", [actual_width])


driver = Driver(bidi=True, browser="firefox")
viewport = Viewport(driver)
try:
    for purpose, url in purposes:
        for width in (320, 767, 768, 1024, 1099, 1100, 1440):
            metrics = inspect(driver, viewport, url, width)
            mobile = width < 1100
            expected = (
                metrics["root"] and metrics["panel"] and metrics["toggle"]
                and metrics["links"] > 0
                and metrics["toggleDisplay"] == ("flex" if mobile else "none")
                and metrics["expanded"] == "false"
                and metrics["brandCount"] == 1
                and metrics["brandImage"] is True
                and metrics["brandImageLoaded"] is True
                and metrics["navigationInDomainHeader"] is True
                and metrics["institutionBars"] == 1
                and len(metrics["navigationLandmarks"]) == 2
                and len({label.casefold() for label in metrics["navigationLandmarks"] if label}) == 2
                and metrics["requestedWidth"] == width
                and metrics["documentWidth"] <= width
                and (metrics["linkHeight"] >= 44 if mobile else metrics["panelVisibility"] == "visible")
            )
            check(f"{purpose} {width}px: SDC, landmarks e reflow", expected, metrics)

        mobile = inspect(driver, viewport, url, 320)
        if mobile["toggleDisplay"] != "none":
            driver.js("document.querySelector('[data-aculta-navigation-toggle]')?.focus()")
            driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
                "type": "key",
                "id": "navigation-keyboard",
                "actions": [
                    {"type": "keyDown", "value": "\ue007"},
                    {"type": "keyUp", "value": "\ue007"},
                ],
            }]})
            wait_for(driver, lambda: driver.js("""const root=document.querySelector('.navigation-bar');
              return document.activeElement===root?.querySelector('[data-aculta-navigation-close]');"""))
            opened = driver.js("""const root=document.querySelector('.navigation-bar');
              const button=root?.querySelector('[data-aculta-navigation-toggle]');
              const panel=root?.querySelector('[data-aculta-navigation-panel]');
              const close=root?.querySelector('[data-aculta-navigation-close]');
              return {expanded:button?.getAttribute('aria-expanded'),visibility:panel&&getComputedStyle(panel).visibility,
                active:document.activeElement?.outerHTML?.slice(0,180),close:close?.outerHTML?.slice(0,180),
                hasJs:root?.classList.contains('has-js')};""")
            opened_ok = opened["expanded"] == "true" and opened["visibility"] == "visible" and "navigation-bar__close" in (opened["active"] or "")
            check(f"{purpose}: Enter abre e move foco para fechar", opened_ok, opened)
            driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
                "type": "key",
                "id": "navigation-keyboard",
                "actions": [
                    {"type": "keyDown", "value": "\ue00c"},
                    {"type": "keyUp", "value": "\ue00c"},
                ],
            }]})
            wait_for(driver, lambda: driver.js("""const root=document.querySelector('.navigation-bar');
              const panel=root?.querySelector('[data-aculta-navigation-panel]');
              return panel && getComputedStyle(panel).visibility==='hidden';"""))
            closed = driver.js("""const root=document.querySelector('.navigation-bar');
              const button=root?.querySelector('[data-aculta-navigation-toggle]');
              const panel=root?.querySelector('[data-aculta-navigation-panel]');
              return {expanded:button?.getAttribute('aria-expanded'),visibility:panel&&getComputedStyle(panel).visibility,
                active:document.activeElement?.outerHTML?.slice(0,180),toggle:button?.outerHTML?.slice(0,180)};""")
            closed_ok = closed["expanded"] == "false" and closed["visibility"] == "hidden" and "navigation-bar__toggle" in (closed["active"] or "")
            check(f"{purpose}: Escape fecha e restaura foco", closed_ok, closed)
finally:
    viewport.close()
    driver.close()

no_js = Driver(prefs={"javascript.enabled": False}, bidi=True, browser="firefox")
no_js_viewport = Viewport(no_js)
try:
    for purpose, url in purposes:
        metrics = inspect(no_js, no_js_viewport, url, 320)
        passed = (
            metrics["root"] and metrics["panel"] and metrics["links"] > 0
            and metrics["toggleDisplay"] == "none"
            and metrics["panelDisplay"] != "none"
            and metrics["panelVisibility"] == "visible"
            and metrics["documentWidth"] <= 320
        )
        check(f"{purpose} 320px: menu permanece operável sem JavaScript", passed, metrics)
finally:
    no_js_viewport.close()
    no_js.close()

(OUT / "results.json").write_text(json.dumps({
    "environment": environment,
    "authState": "anonymous",
    "viewportWidths": [320, 767, 768, 1024, 1099, 1100, 1440],
    "purposes": {purpose: url for purpose, url in purposes},
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [item for item in checks if not item["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
