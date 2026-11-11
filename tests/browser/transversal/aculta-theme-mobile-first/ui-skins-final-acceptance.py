#!/usr/bin/env python3
"""Homologação residual de UI Skins: zoom 200%, teclado e cache-hit.

Percorre os dez domínios no ambiente de desenvolvimento, em Firefox ESR
visível, e registra PASS/FAIL por domínio para as pendências DEFERRED das
ADRs 054/055. O aceite visual humano continua fora deste script.
"""

import json
import os
import ssl
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))

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
DOMAIN_ENV = os.environ.get("PURPOSE_MODE_DOMAIN_ENV", "test")
if DOMAIN_ENV not in ("test", "production"):
    raise SystemExit("PURPOSE_MODE_DOMAIN_ENV deve ser test ou production.")
deploy_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
entries = json.loads(deploy_path.read_text())["domain_transition"]["entries"]
hosts = {item["domain_id"]: item[f"{DOMAIN_ENV}_host"] for item in entries}
SELECTED = {
    value.strip() for value in os.environ.get("PURPOSE_ACCEPT_DOMAINS", "").split(",")
    if value.strip()
}
if SELECTED - set(DOMAIN_IDS):
    raise SystemExit("PURPOSE_ACCEPT_DOMAINS contém domínio desconhecido.")
DOMAINS = [(key, f"https://{hosts[domain_id]}/") for key, domain_id in DOMAIN_IDS.items()
           if not SELECTED or key in SELECTED]
WIDTHS = [int(value) for value in os.environ.get("PURPOSE_ACCEPT_WIDTHS", "320,768,1440").split(",")]
TAB_STEPS = int(os.environ.get("PURPOSE_ACCEPT_TABS", "30"))
checks = []


def check(name, passed, detail):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)


def send_keys(driver, actions):
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "acceptance-keyboard", "actions": actions,
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


def page_metrics(driver):
    return json.loads(driver.js(r"""return JSON.stringify((() => {
      const root=document.documentElement;
      const focus=document.activeElement;
      const focusStyle=focus?getComputedStyle(focus):null;
      const focusRect=focus?focus.getBoundingClientRect():null;
      return {
        innerWidth, devicePixelRatio,
        visualScale:window.visualViewport?window.visualViewport.scale:null,
        documentWidth:root.scrollWidth,
        siteKey:root.getAttribute('data-site-key'),
        skin:root.getAttribute('data-skin'),
        mainCount:document.querySelectorAll('main').length,
        h1Count:document.querySelectorAll('main h1').length,
        institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
        activeElement:focus?{tag:focus.tagName.toLowerCase(),id:focus.id,
          className:String(focus.className||'').slice(0,120),
          focusVisible:focus.matches(':focus-visible'),
          outline:focusStyle.outlineStyle,outlineWidth:focusStyle.outlineWidth,
          boxShadow:focusStyle.boxShadow,
          visible:!!focusRect&&focusRect.width>0&&focusRect.height>0,
          insideViewport:!!focusRect&&focusRect.left>=-1&&focusRect.right<=innerWidth+1}:null
      };
    })());"""))


def cache_hit(url):
    context = ssl._create_unverified_context()
    headers = {"User-Agent": "aculta-adr-acceptance"}
    results = []
    for _ in range(3):
        request = urllib.request.Request(url, headers=headers)
        try:
            with urllib.request.urlopen(request, context=context, timeout=20) as response:
                results.append({
                    "status": response.status,
                    "x_drupal_cache": response.headers.get("X-Drupal-Cache"),
                    "x_drupal_dynamic_cache": response.headers.get("X-Drupal-Dynamic-Cache"),
                    "cache_control": response.headers.get("Cache-Control"),
                })
        except urllib.error.URLError as error:
            results.append({"error": str(error)})
    return results


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(prefs={"ui.prefersReducedMotion": 1}, bidi=True)
viewport = Viewport(driver)
try:
    for domain, url in DOMAINS:
        cache = cache_hit(url)
        cacheable = [item for item in cache if item.get("x_drupal_cache") in ("HIT", "MISS")]
        check(f"{domain}: cache-hit observável",
              bool(cacheable) and cache[-1].get("x_drupal_cache") in ("HIT", "MISS"),
              cache)
        for width in WIDTHS:
            viewport.set(width)
            driver.get(url)
            time.sleep(0.4)

            key_metrics = {"reached": 0, "visibleFocus": 0, "problems": []}
            seen = set()
            for _ in range(TAB_STEPS):
                send_keys(driver, [{"type": "keyDown", "value": ""},
                                   {"type": "keyUp", "value": ""}])
                time.sleep(0.02)
                state = page_metrics(driver)
                active = state["activeElement"]
                if not active:
                    continue
                # O ciclo de tabulação terminou quando o foco volta ao body;
                # a partir daí o Firefox deixa de casar :focus-visible.
                if active["tag"] == "body":
                    break
                identifier = (active["tag"], active["id"], active["className"])
                if identifier in seen:
                    continue
                seen.add(identifier)
                key_metrics["reached"] += 1
                outlined = (active["outline"] not in ("none", None)
                            and active["outlineWidth"] not in ("0px", None))
                shadowed = active["boxShadow"] not in ("none", None, "")
                if (active["focusVisible"] and active["visible"]
                        and active["insideViewport"] and (outlined or shadowed)):
                    key_metrics["visibleFocus"] += 1
                else:
                    key_metrics["problems"].append(active)
            check(f"{domain} {width}px: teclado com foco visível",
                  key_metrics["reached"] >= 3
                  and key_metrics["visibleFocus"] == key_metrics["reached"]
                  and not key_metrics["problems"], key_metrics)
finally:
    viewport.close()
    driver.close()

# Zoom 200%: layout.css.devPixelsPerPx=2 emula o zoom de página no Firefox
# e reduz a largura CSS efetiva, exercitando o reflow. Usa sessão exclusiva
# porque o geckodriver não expõe duas sessões BiDi simultâneas de forma
# confiável.
zoom_driver = Driver(prefs={"layout.css.devPixelsPerPx": "2"}, bidi=True)
zoom_viewport = Viewport(zoom_driver)
try:
    for domain, url in DOMAINS:
        for width in WIDTHS:
            zoom_viewport.set(width)
            zoom_driver.get(url)
            time.sleep(0.3)
            zoomed = page_metrics(zoom_driver)
            check(f"{domain} {width}px: zoom 200% sem overflow",
                  (zoomed["devicePixelRatio"] or 0) >= 1.99
                  and zoomed["documentWidth"] <= zoomed["innerWidth"] + 1
                  and zoomed["mainCount"] == 1
                  and zoomed["h1Count"] == 1
                  and zoomed["institutionBar"] == 1,
                  zoomed)
finally:
    zoom_viewport.close()
    zoom_driver.close()

(OUT / "final-acceptance.json").write_text(json.dumps({
    "environment": DOMAIN_ENV,
    "domains": [domain for domain, _ in DOMAINS],
    "widths": WIDTHS,
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["passed"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
