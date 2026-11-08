#!/usr/bin/env python3
"""Valida em Firefox ESR visível os envios convertidos de #ajax para HTMX.

Cobre os três envios já convertidos em docs/htmx-envios.md:

- SocialLinksSettingsForm: "+ Adicionar botão" troca o wrapper inteiro.
- SurfaceEditForm: "Salvar" com campo obrigatório vazio devolve o formulário
  com erro, sem gravar configuração.

Não envia o formulário de superfície com dados válidos: a gravação fica fora do
escopo desta verificação. Usa conta de teste por drush uli no host de conta.
"""

import json
import os
import subprocess
import sys
import time
from datetime import date
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit

ROOT = Path(__file__).resolve().parents[4]
DRUPAL_ROOT = Path(os.environ.get("ACULTA_DRUPAL_ROOT", "/home/aculta")).resolve()
OUT = Path(os.environ.get(
    "SUITE_OUT",
    ROOT / "tests/browser/capturas/htmx-envios" / date.today().isoformat(),
)).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT.parent / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Use Firefox ESR visível em DISPLAY=:0.")

sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

ADMIN_UID = os.environ.get("ACULTA_ADMIN_TEST_UID", "257")
DEPLOY = json.loads(
    (ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json").read_text()
)
TEST_HOSTS = {item["domain_id"]: item["test_host"] for item in DEPLOY["domain_transition"]["entries"]}
MAIN = f"https://{TEST_HOSTS['aculta_org']}"
ACCOUNT = f"https://{TEST_HOSTS['conta_aculta_org']}"
SOCIAL = f"{MAIN}/painel-administrativo/aculta/redes-sociais"
SURFACE = f"{MAIN}/painel-administrativo/aculta/caminhos/wiki/editar"


def one_time_login_url():
    process = subprocess.run(
        ["php", str(DRUPAL_ROOT / "vendor/drush/drush/drush.php"), "uli", "/", "--no-browser",
         f"--uid={ADMIN_UID}", f"--uri={ACCOUNT}"],
        cwd=DRUPAL_ROOT, check=True, capture_output=True, text=True, timeout=60,
    )
    generated = urlsplit(process.stdout.strip().splitlines()[-1])
    account = urlsplit(ACCOUNT)
    return urlunsplit((account.scheme, account.netloc, generated.path, generated.query, generated.fragment))


def wait_for(driver, script, timeout=15):
    deadline = time.time() + timeout
    while time.time() < deadline:
        value = driver.js(script)
        if value:
            return value
        time.sleep(0.2)
    return None


def main():
    driver = Driver(bidi=True, browser="firefox")
    results = {"checks": {}, "failures": []}
    try:
        driver.get(one_time_login_url())
        time.sleep(1.5)

        # SocialLinksSettingsForm: adicionar botão via HTMX.
        driver.get(SOCIAL)
        wait_for(driver, "return !!document.querySelector('#edit-buttons-wrapper-add');")
        field_selector = '#aculta-social-links-buttons-wrapper input[type="text"]'
        before = driver.js(f"return document.querySelectorAll('{field_selector}').length")
        driver.js("window.__acultaNoReload = 'social'; return true;")
        driver.js(
            "document.querySelector('#edit-buttons-wrapper-add').click(); return true;")
        after = wait_for(
            driver,
            f"var n=document.querySelectorAll('{field_selector}').length;"
            f"return n > {before} ? n : 0;")
        results["checks"]["social_add_items_before"] = before
        results["checks"]["social_add_items_after"] = after
        results["checks"]["social_add_htmx"] = after is not None
        results["checks"]["social_add_no_navigation"] = driver.js(
            "return window.__acultaNoReload === 'social';") is True

        # SurfaceEditForm: erro de validação sem gravar.
        driver.get(SURFACE)
        wait_for(driver, "return !!document.querySelector('#edit-titulo');")
        driver.js("window.__acultaNoReload = 'surface'; return true;")
        driver.js("document.querySelector('#edit-titulo').value = ''; return true;")
        driver.js("document.querySelector('#edit-submit').click(); return true;")
        error = wait_for(
            driver,
            "var f=document.querySelector('#aculta-paths-surface-edit');"
            "if(!f) return '';"
            "var c=f.querySelector('.has-error, .form-item--error-message, .messages--error');"
            "return c ? 'erro de validação no fragmento' : '';")
        results["checks"]["surface_validation_error"] = error or ""
        results["checks"]["surface_htmx_error_fragment"] = bool(error)
        results["checks"]["surface_no_navigation"] = driver.js(
            "return window.__acultaNoReload === 'surface';") is True

        checks = results["checks"]
        results["ok"] = all([
            checks["social_add_htmx"],
            checks["social_add_no_navigation"],
            checks["surface_htmx_error_fragment"],
            checks["surface_no_navigation"],
        ])
        if not results["ok"]:
            results["failures"].append("um ou mais envios não responderam como fragmento HTMX")
    finally:
        driver.close()
        (OUT / "manifest.json").write_text(json.dumps(results, ensure_ascii=False, indent=2))
    print(json.dumps(results["checks"], ensure_ascii=False))
    print("ok" if results.get("ok") else "FALHOU")


if __name__ == "__main__":
    main()
