#!/usr/bin/env python3
"""Read-only Firefox layout check for the canonical Account Address form."""

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
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
BASE = os.environ.get("ACCOUNT_ADDRESS_BASE_URL", "https://conta.aculta.toca.net.br").rstrip("/")
URI = os.environ.get("ACCOUNT_ADDRESS_DRUSH_URI", BASE)
UID = os.environ.get("ACCOUNT_ADDRESS_UID", "5")
PATH = "/dados/endereco"
WIDTHS = [320, 360, 390, 599, 600, 768, 1024, 1280, 1440]
NO_JS_WIDTHS = [320, 390, 768, 1440]
results = []


def check(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


def login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    process = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", PATH, "--no-browser", f"--uid={UID}", f"--uri={URI}"],
        cwd=PROJECT, check=True, capture_output=True, text=True, timeout=30, env=env,
    )
    return process.stdout.strip().splitlines()[-1]


def new_driver(no_js=False):
    driver = Driver(prefs={"javascript.enabled": False} if no_js else {}, bidi=True)
    socket = connect(driver.websocket_url)
    socket.send(json.dumps({"id": 1, "method": "browsingContext.getTree", "params": {}}))
    while True:
        response = json.loads(socket.recv())
        if response.get("id") == 1:
            context = response["result"]["contexts"][0]["context"]
            break
    return driver, socket, context


def viewport(driver, socket, context, width):
    viewport.command_id += 1
    command_id = viewport.command_id
    socket.send(json.dumps({
        "id": command_id,
        "method": "browsingContext.setViewport",
        "params": {"context": context, "viewport": {"width": width, "height": 900}},
    }))
    while True:
        response = json.loads(socket.recv())
        if response.get("id") == command_id:
            break
    time.sleep(0.2)
    return int(driver.js("return innerWidth"))


viewport.command_id = 1


def form_metrics(driver):
    return driver.js("""return (() => {
      const path = location.pathname;
      const postal = document.querySelector('input[name$="[postal_code]"]');
      const form = postal?.closest('form');
      const number = form?.querySelector('input[name$="[street_number]"]');
      const sn = form?.querySelector('input[name$="[street_number_sn]"]');
      const complement = form?.querySelector('input[name$="[address_line2]"]');
      const row = form?.querySelector('.portal-address-number-row');
      const content = document.querySelector('.portal-account__content');
      const rect = (element) => {
        if (!element) return null;
        const box = element.getBoundingClientRect();
        return {left: Math.round(box.left * 100) / 100,
          right: Math.round(box.right * 100) / 100,
          top: Math.round(box.top * 100) / 100,
          width: Math.round(box.width * 100) / 100};
      };
      const country = [...(form?.querySelectorAll('[name*="[country_code]"]') || [])];
      const countryHidden = country.some((element) => element.type === 'hidden');
      const lib = window.drupalSettings?.ajaxPageState?.libraries || '';
      const scripts = [...document.scripts].map((script) => script.src).filter(Boolean);
      const cepBehavior = typeof window.Drupal?.behaviors?.acultaCepAddress === 'object';
      return {
        path, loggedIn: document.body.classList.contains('user-logged-in'),
        formsWithPostal: document.querySelectorAll('form input[name$="[postal_code]"]').length,
        fieldNames: ['postal_code','address_line1','street_number','street_number_sn',
          'address_line2','dependent_locality','locality','administrative_area']
          .map((name) => [name, !!form?.querySelector(`[name$="[${name}]"]`)]),
        hiddenCountry: countryHidden,
        cepLibrary: lib.includes('aculta_portal_cep/cep-address')
          || scripts.some((src) => src.includes('cep-address')) || cepBehavior,
        addressLayoutRow: !!row, content: rect(content), row: rect(row),
        number: rect(number?.closest('.form-item, .js-form-item') || number),
        sn: rect(sn?.closest('.form-check, .form-item, .js-form-item') || sn),
        complement: rect(complement?.closest('.form-item, .js-form-item') || complement),
        scrollWidth: document.documentElement.scrollWidth,
        viewportWidth: innerWidth,
        accountMenuHasMyData: [...document.querySelectorAll('.portal-account__menu a, .portal-account__accordion a')]
          .some((link) => new URL(link.href, location.href).pathname.replace(/\\/$/, '') === '/dados'),
        addressTabActive: [...document.querySelectorAll('[data-portal-account-data-nav] a')]
          .some((link) => new URL(link.href, location.href).pathname.replace(/\\/$/, '') === arguments[0]
            && link.getAttribute('aria-current') === 'page'),
      };
    })();""", [PATH])


def run(no_js=False):
    driver, socket, context = new_driver(no_js)
    try:
        driver.get(login_url())
        driver.get(f"{BASE}{PATH}")
        time.sleep(1.0)
        initial = form_metrics(driver)
        label = "no-JS" if no_js else "JS"
        check(f"{label}: rota da conta legada usa o endereço canônico", initial["path"] == PATH
              and initial["loggedIn"] and initial["formsWithPostal"] == 1,
              {"path": initial["path"], "loggedIn": initial["loggedIn"], "formsWithPostal": initial["formsWithPostal"]})
        check(f"{label}: menu legado leva a Meus Dados e destaca Endereço",
              initial["accountMenuHasMyData"] and initial["addressTabActive"],
              {"accountMenuHasMyData": initial["accountMenuHasMyData"], "addressTabActive": initial["addressTabActive"]})
        check(f"{label}: contrato de campos do formulário unificado", all(value for _, value in initial["fieldNames"])
              and initial["hiddenCountry"],
              {"fields": initial["fieldNames"], "hiddenCountry": initial["hiddenCountry"]})
        if not no_js:
            check("JS: behavior CEP do aculta_portal_cep carregado", initial["cepLibrary"],
                  {"cepLibrary": initial["cepLibrary"]})

        widths = NO_JS_WIDTHS if no_js else WIDTHS
        for width in widths:
            actual = viewport(driver, socket, context, width)
            metrics = form_metrics(driver)
            check(f"{label} {width}px: viewport sem overflow", actual == width and metrics["scrollWidth"] <= width,
                  {"viewport": actual, "document": metrics["scrollWidth"]})
            if not no_js:
                row, content = metrics["row"], metrics["content"]
                contained = bool(row and content and row["left"] >= content["left"] - 2
                                 and row["right"] <= content["right"] + 2)
                pieces = [metrics["number"], metrics["sn"], metrics["complement"]]
                ordered = all(pieces) and pieces[0]["right"] <= pieces[1]["left"] + 4 \
                    and pieces[1]["right"] <= pieces[2]["left"] + 4
                check(f"JS {width}px: agrupamento existente cabe no conteúdo", metrics["addressLayoutRow"]
                      and contained and ordered,
                      {"row": row, "content": content, "number": pieces[0], "sn": pieces[1], "complement": pieces[2]})
            # Mask typed values in screenshots; captures stay outside the repository.
            driver.js("""if (!document.querySelector('[data-address-visual-mask]')) {
              const style=document.createElement('style'); style.dataset.addressVisualMask='true';
              style.textContent='input:not([type="hidden"]){color:transparent!important;text-shadow:none!important;caret-color:transparent!important} input::placeholder{color:transparent!important}';
              document.head.append(style);
            }""")
            image = driver.req("GET", f"/session/{driver.session}/screenshot")
            suffix = "nojs" if no_js else "js"
            (OUT / f"account-address-{suffix}-{width}.png").write_bytes(base64.b64decode(image))
        return True
    finally:
        socket.close()
        driver.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

run(no_js=False)
run(no_js=True)
(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [item for item in results if not item["passed"]]
print(f"\n{len(results)-len(failed)}/{len(results)} verificações passaram; evidências: {OUT}")
sys.exit(1 if failed else 0)
