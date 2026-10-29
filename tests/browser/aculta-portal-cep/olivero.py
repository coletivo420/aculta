#!/usr/bin/env python3
"""Smoke-test the authenticated address form with Olivero in Firefox ESR."""

import base64
import json
import os
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent / "aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
ULI_FILE = Path(sys.argv[1])
ULI = ULI_FILE.read_text(encoding="utf-8").strip()
ACCOUNT_URL = "https://conta.aculta.toca.net.br/dados/endereco"
ADDRESS = "field_address[0][address]"
checks = []


def check(label, passed, detail=""):
    checks.append(bool(passed))
    print(("PASS " if passed else "FAIL ") + label + (f" ({detail})" if detail else ""), flush=True)


def value(field):
    selector = f'[name="{ADDRESS}[{field}]"]'
    return D.js("var e=document.querySelector(arguments[0]); return e ? e.value : null;", [selector])


def wait_for(predicate, timeout=25):
    end = time.time() + timeout
    while time.time() < end:
        result = predicate()
        if result:
            return result
        time.sleep(0.4)
    return predicate()


class Viewport:
    """Sets a real CSS viewport through WebDriver BiDi."""

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

    def set(self, width, height):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": height},
        })
        time.sleep(0.3)
        return int(D.js("return window.innerWidth"))

    def close(self):
        self.socket.close()


D = Driver(bidi=True)
viewport = Viewport(D)
try:
    D.get(ULI)
    time.sleep(1)
    D.get(ACCOUNT_URL)
    if not wait_for(lambda: D.exists(f'[name="{ADDRESS}[postal_code]"]')):
        raise RuntimeError("Campo de CEP não apareceu em Meus dados > Endereço.")

    source = D.source()
    if os.environ.get("CEP_EXPECT_OLIVERO", "1") == "1":
        check("Olivero ativo", "/core/themes/olivero/" in source, D.js("return document.body.className"))
        check("nenhum asset ACULTA420", "/themes/custom/aculta420/" not in source)
    else:
        print("Tema carregado:", D.js("return document.body.className"), flush=True)
    behavior = D.js("return typeof Drupal.behaviors.acultaCepAddress?.attach === 'function'")
    check("behavior CEP registrado", behavior is True)

    api_probe = D.js("""return fetch('/aculta_portal_cep/01001000', {credentials:'same-origin', headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}).then(async r=>{const j=await r.json();return {status:r.status,ok:j.ok,keys:Object.keys(j.data||{}),hasCity:!!j.data?.locality,hasState:!!j.data?.administrative_area}}).catch(e=>({error:e.name}));""")
    print("Probe AJAX:", api_probe, flush=True)
    for field in ("postal_code", "address_line1", "dependent_locality", "locality", "administrative_area", "street_number"):
        check(f"campo {field} presente", D.exists(f'[name="{ADDRESS}[{field}]"]'))

    postal = f'[name="{ADDRESS}[postal_code]"]'
    element = D.find(postal)["element-6066-11e4-a52e-4f735466cecf"]
    D.req("POST", f"/session/{D.session}/element/{element}/clear", {})
    D.type(postal, "01001000")
    check("CEP preenche cidade", wait_for(lambda: value("locality") == "São Paulo"), value("locality") or "")
    check("CEP preenche estado", wait_for(lambda: value("administrative_area") == "SP"), value("administrative_area") or "")
    print("Aviso CEP:", D.js("return document.querySelector('[data-aculta-cep-status]')?.innerText || ''"), flush=True)
    check("rua preenchida fica somente leitura", wait_for(lambda: D.js(
        "var e=document.querySelector(arguments[0]); return e && e.readOnly;",
        [f'[name="{ADDRESS}[address_line1]"]'],
    )))
    check("número permanece editável", not D.js(
        "var e=document.querySelector(arguments[0]); return !e || e.readOnly;",
        [f'[name="{ADDRESS}[street_number]"]'],
    ))

    for target in (1280, 390, 320):
        actual = viewport.set(target, 1000)
        metrics = D.js("return {client:document.documentElement.clientWidth, scroll:document.documentElement.scrollWidth};")
        check(f"reflow sem overflow em {target}px", metrics["scroll"] <= metrics["client"] + 1, str(metrics))
        check(f"viewport alcança {target}px", abs(actual - target) <= 2, str(actual))
        screenshot = D.req("GET", f"/session/{D.session}/screenshot")
        (OUT / f"olivero-{target}.png").write_bytes(base64.b64decode(screenshot))
finally:
    viewport.close()
    D.close()

passed = sum(checks)
print(f"RESULTADO: {passed}/{len(checks)} verificações OK")
sys.exit(0 if passed == len(checks) else 1)
