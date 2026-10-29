#!/usr/bin/env python3
"""Read-only Firefox check for the shared address widget in the OSM location wizard."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", str(HERE.parents[2])))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
BASE = os.environ.get("OSM_ADDRESS_BASE_URL", "https://aculta.toca.net.br").rstrip("/")
URI = os.environ.get("OSM_ADDRESS_DRUSH_URI", "https://conta.aculta.toca.net.br")
UID = os.environ.get("OSM_ADDRESS_TEST_UID", "1")
OSM_TYPE = os.environ.get("OSM_ADDRESS_TEST_TYPE", "way")
OSM_ID = os.environ.get("OSM_ADDRESS_TEST_ID", "1370081491")
PATH = "/integracoes/openstreetmap/importar"
checks = []


def check(name, passed, detail=None):
    checks.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


def login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    result = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", PATH, "--no-browser", f"--uid={UID}", f"--uri={URI}"],
        cwd=PROJECT, check=True, capture_output=True, text=True, timeout=30, env=env,
    )
    return result.stdout.strip().splitlines()[-1]


def wait_for(predicate, timeout=35):
    """Wait for an address wizard rebuild or CEP lookup to finish."""
    end = time.time() + timeout
    while time.time() < end:
        if predicate():
            return True
        time.sleep(0.4)
    return bool(predicate())


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

    def set(self, driver, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.25)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get(login_url())
    driver.get(f"{BASE}{PATH}")
    time.sleep(0.8)
    assistant_loaded = driver.exists('[name="element_id"]')
    page = driver.js("""return {host:location.host,
      path:location.pathname.startsWith('/recuperar-acesso/')?'/recuperar-acesso/[redacted]':location.pathname,
      title:document.title,signIn:document.body.innerText.includes('Entrar'),
      denied:document.body.innerText.includes('Acesso negado')};""")
    check("assistente de importação acessível", assistant_loaded, None if assistant_loaded else page)
    if not assistant_loaded:
        (OUT / "summary.json").write_text(json.dumps(checks, ensure_ascii=False, indent=2) + "\n")
        sys.exit(1)
    driver.js("""const field=document.querySelector('[name="element_type"]');
      field.value=arguments[0];field.dispatchEvent(new Event('change',{bubbles:true}));""", [OSM_TYPE])
    driver.type('[name="element_id"]', OSM_ID)
    driver.click('[value="Encontrar"]')
    check("elemento OSM carregado por leitura", wait_for(lambda: driver.exists('[name="selected_tags[addr:street]"]')))

    for tag in ("addr:street", "addr:housenumber", "addr:unit", "addr:suburb", "addr:city", "addr:state", "addr:postcode"):
        selector = f'[name="selected_tags[{tag}]"]'
        if driver.exists(selector) and driver.js("return !document.querySelector(arguments[0]).checked", [selector]):
            driver.click(selector)
    driver.click('[value="Revisar dados"]')
    postal = 'input[name="review[address][postal_code]"]'
    check("etapa de revisão usa widget address", wait_for(lambda: driver.exists(postal)))

    metrics = driver.js("""const f=document.querySelector(arguments[0]);
      const country=document.querySelector('[name="review[address][country_code]"]');
      return {cep:!!f,countryType:country?.type||null,countrySelect:!!document.querySelector('select[name="review[address][country_code]"]'),
        behavior:typeof Drupal.behaviors.acultaCepAddress?.attach==='function',
        addressFields:['address_line1','address_line2','dependent_locality','locality','administrative_area']
          .map(k=>[k,!!document.querySelector(`[name="review[address][${k}]"]`)]),
        formCount:document.querySelectorAll('form').length};""", [postal])
    check("país BR oculto sem seletor", metrics["countryType"] == "hidden" and not metrics["countrySelect"], metrics)
    check("CEP central carregado no assistente OSM", metrics["cep"] and metrics["behavior"], metrics)
    check("campos de endereço esperados presentes", all(ok for _, ok in metrics["addressFields"]), metrics["addressFields"])

    driver.js("""const f=document.querySelector(arguments[0]);f.value='';
      f.dispatchEvent(new Event('input',{bubbles:true}));""", [postal])
    driver.type(postal, "01001000")
    city = 'input[name="review[address][locality]"]'
    state = 'select[name="review[address][administrative_area]"]'
    check("CEP preenche município", wait_for(lambda: driver.js("return document.querySelector(arguments[0])?.value==='São Paulo'", [city])))
    check("CEP preenche UF", wait_for(lambda: driver.js("return document.querySelector(arguments[0])?.value==='SP'", [state])))
    check("assistente permanece na etapa sem salvar", driver.exists('[value="Confirmar e salvar"]'))

    driver.js("""if (!document.querySelector('[data-address-visual-mask]')) {
      const style=document.createElement('style');style.dataset.addressVisualMask='true';
      style.textContent='input:not([type="hidden"]){color:transparent!important;text-shadow:none!important;caret-color:transparent!important}';
      document.head.append(style);
    }""")
    for width in (320, 390, 768, 1440):
        actual = viewport.set(driver, width)
        sizes = driver.js("return {client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}")
        check(f"reflow sem overflow em {width}px", actual == width and sizes["scroll"] <= width, sizes)
        image = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"maps-events-address-{width}.png").write_bytes(base64.b64decode(image))
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(checks, ensure_ascii=False, indent=2) + "\n")
failed = [item for item in checks if not item["passed"]]
print(f"\n{len(checks)-len(failed)}/{len(checks)} verificações passaram; evidências: {OUT}")
sys.exit(1 if failed else 0)
