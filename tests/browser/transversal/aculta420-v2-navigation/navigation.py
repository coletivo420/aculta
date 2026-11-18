#!/usr/bin/env python3
"""Exercise V2's Drupal SDC navigation in visible Firefox ESR."""

import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
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
domain_brand_names = {
    "main": {"aculta", "início"},
    "account": {"minha conta", "conta"},
    "support": {"apoio"},
    "magazine": {"revista 420", "coletivo 420"},
    "wiki": {"wiki420"},
    "shop": {"loja"},
    "courses": {"cursos"},
    "forum": {"fórum"},
    "links": {"redes sociais da aculta", "redes sociais"},
    "eventos": {"eventos da aculta", "eventos"},
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
      const brandLink=root?.querySelector('.navigation-bar__brand-link, .navigation-bar__brand a[href]');
      const brandImage=root?.querySelector('.navigation-bar__brand img');
      const wikiSearchForm=root?.querySelector('.navigation-bar__search form[role="search"]');
      const wikiSearchInput=wikiSearchForm?.querySelector('input[type="search"]');
      const wikiSearchLabel=wikiSearchInput?.labels?.[0]??null;
      const wikiSearchPreviewId=wikiSearchInput?.dataset.previewTarget??null;
      const style=(element,property)=>element?getComputedStyle(element).getPropertyValue(property):null;
      return {
        root:!!root,
        rootCount:document.querySelectorAll('.navigation-bar').length,
        panel:!!panel,
        toggle:!!toggle,
        toggleDisplay:style(toggle,'display'),
        panelVisibility:style(panel,'visibility'),
        panelDisplay:style(panel,'display'),
        expanded:toggle?.getAttribute('aria-expanded')??null,
        links:panel?.querySelectorAll('a[href]').length??0,
        linkHeight:link?Math.ceil(link.getBoundingClientRect().height):0,
        brandCount:root?.querySelectorAll('.navigation-bar__brand').length??0,
        brandLink:!!brandLink,
        brandHref:brandLink?.href??null,
        brandName:(brandLink?.getAttribute('aria-label')||brandLink?.innerText||brandImage?.alt||'').trim(),
        brandImage:!!brandImage,
        brandImageLoaded:(brandImage?.naturalWidth??0)>0,
        wikiSearchForm:!!wikiSearchForm,
        wikiSearchMethod:wikiSearchForm?.method?.toUpperCase()??null,
        wikiSearchActionOrigin:wikiSearchForm?new URL(wikiSearchForm.action,location.href).origin:null,
        wikiSearchInputName:wikiSearchInput?.name??null,
        wikiSearchInputId:wikiSearchInput?.id??null,
        wikiSearchInputLabelled:!!wikiSearchInput?.labels?.length,
        wikiSearchLabelFor:wikiSearchLabel?.htmlFor??null,
        wikiSearchPreviewId,
        wikiSearchIdsUnique:!!wikiSearchInput?.id && !!wikiSearchPreviewId
          && wikiSearchLabel?.htmlFor===wikiSearchInput.id
          && document.querySelectorAll(`[id="${wikiSearchInput.id}"]`).length===1
          && document.querySelectorAll(`[id="${wikiSearchPreviewId}"]`).length===1,
        wikiSearchPreviewTargetMatches:!!wikiSearchPreviewId
          && Array.from(wikiSearchForm?.querySelectorAll('[id]')??[]).some((element)=>element.id===wikiSearchPreviewId),
        wikiSearchSubmit:!!wikiSearchForm?.querySelector('button[type="submit"],input[type="submit"]'),
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
            parsed_url = urlparse(url)
            expected_origin = f"{parsed_url.scheme}://{parsed_url.netloc}"
            wiki_search_ok = (
                metrics["wikiSearchForm"]
                and metrics["wikiSearchMethod"] == "GET"
                and metrics["wikiSearchActionOrigin"] == expected_origin
                and metrics["wikiSearchInputName"] == "q"
                and metrics["wikiSearchInputLabelled"]
                and metrics["wikiSearchIdsUnique"]
                and metrics["wikiSearchPreviewTargetMatches"]
                and metrics["wikiSearchSubmit"]
            ) if purpose == "wiki" else not metrics["wikiSearchForm"]
            header_ok = (
                metrics["root"]
                and metrics["rootCount"] == 1
                and metrics["brandCount"] == 1
                and metrics["brandLink"]
                and metrics["brandName"].casefold() in domain_brand_names[purpose]
                and metrics["brandHref"] == url
                and wiki_search_ok
                and metrics["navigationInDomainHeader"] is True
                and metrics["domainHeaders"] == 1
                and metrics["institutionBars"] == 1
                and metrics["requestedWidth"] == width
                and metrics["documentWidth"] <= width
            )
            check(f"{purpose} {width}px: Domain Header e reflow", header_ok, metrics)

            if purpose == "main":
                menu_ok = (
                    metrics["panel"] and metrics["toggle"]
                    and metrics["links"] > 0
                    and metrics["toggleDisplay"] == ("flex" if mobile else "none")
                    and metrics["expanded"] == "false"
                    and len(metrics["navigationLandmarks"]) == 2
                    and len({label.casefold() for label in metrics["navigationLandmarks"] if label}) == 2
                    and (metrics["linkHeight"] >= 44 if mobile else metrics["panelVisibility"] == "visible")
                )
                menu_label = "menu responsivo e landmarks"
            else:
                menu_ok = not metrics["panel"] and not metrics["toggle"]
                menu_label = "sem menu compartilhado vazio"
            check(f"{purpose} {width}px: {menu_label}", menu_ok, metrics)

        if purpose == "main":
            mobile = inspect(driver, viewport, url, 320)
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

main_pages = [(purpose, url) for purpose, url in purposes if purpose == "main"]
if main_pages:
    no_js = Driver(prefs={"javascript.enabled": False}, bidi=True, browser="firefox")
    no_js_viewport = Viewport(no_js)
    try:
        for purpose, url in main_pages:
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
