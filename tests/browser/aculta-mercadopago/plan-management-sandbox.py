#!/usr/bin/env python3
"""Create and update one uniquely named Mercado Pago sandbox plan via Studio."""
import json
import os
import subprocess
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlparse

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "aculta-cpf-checkout"))
from wdlib import Driver


BASE_URL = os.environ.get("ACULTA_ACCOUNT_BASE_URL", "https://conta.aculta.toca.net.br").rstrip("/")
LOGIN_URL = os.environ.get("ACULTA_MP_STUDIO_LOGIN_URL", "")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-mercadopago-plan-management"))


def wait_for(driver, predicate, seconds=45):
    deadline = time.time() + seconds
    while time.time() < deadline:
        if predicate():
            return True
        time.sleep(0.4)
    return False


def on_test_domain(driver):
    host = (urlparse(driver.url()).hostname or "").lower()
    return host == "aculta.toca.net.br" or host.endswith(".aculta.toca.net.br")


def set_value(driver, name, value):
    return driver.js(
        """const input = document.querySelector(`input[name='${arguments[0]}']`);
          if (!input || input.disabled) return false;
          input.value = arguments[1];
          input.dispatchEvent(new Event('input', {bubbles: true}));
          input.dispatchEvent(new Event('change', {bubbles: true}));
          return true;""",
        [name, value],
    )


def submit(driver):
    driver.click("form input[type='submit'], form button[type='submit']")


def read_remote_plan(plan_id):
    code = (
        '$gateway = \\Drupal::entityTypeManager()->getStorage("commerce_payment_gateway")->load("mercado_pago"); '
        '$plan = \\Drupal::service("aculta_pagamentos_mercadopago.recurrence_service")->obterPlano($gateway, getenv("ACULTA_MP_PLAN_ID")); '
        'echo json_encode(["reason" => $plan->reason ?? null, "amount" => $plan->auto_recurring->transaction_amount ?? null]);'
    )
    env = os.environ.copy()
    env.pop("ACULTA_MP_STUDIO_LOGIN_URL", None)
    env["ACULTA_MP_PLAN_ID"] = plan_id
    result = subprocess.run(
        ["vendor/bin/drush", "ev", code],
        cwd=Path(__file__).resolve().parents[3],
        env=env,
        capture_output=True,
        check=True,
        text=True,
        timeout=45,
    )
    return json.loads(result.stdout)


def main():
    if not LOGIN_URL:
        raise SystemExit("ACULTA_MP_STUDIO_LOGIN_URL is required; do not print one-time login URLs.")
    parsed = urlparse(LOGIN_URL)
    if parsed.scheme != "https" or not (parsed.hostname or "").endswith(".aculta.toca.net.br"):
        raise SystemExit("The login URL must use the HTTPS ACULTA test domain.")

    suffix = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    reason = f"ACULTA Plano Sandbox {suffix}"
    updated_reason = os.environ.get("ACULTA_MP_PLAN_UPDATE_REASON", f"ACULTA Plano Sandbox Atualizado {suffix}")
    driver = Driver()
    try:
        driver.get(LOGIN_URL)
        if not wait_for(driver, lambda: on_test_domain(driver)):
            raise RuntimeError("ACULTA test login did not complete.")
        driver.get(BASE_URL + "/studio/conta/mercado-pago/planos")
        if not wait_for(driver, lambda: driver.exists("input[name='reason']")):
            raise RuntimeError("Sandbox plan form did not load.")
        action_host = driver.js("return new URL(document.querySelector('form').getAttribute('action'), location.href).hostname;")
        if action_host != "aculta.toca.net.br":
            raise RuntimeError("The plan form does not submit on the canonical MAIN domain.")

        plan_id = os.environ.get("ACULTA_MP_PLAN_ID", "")
        saved_plan_id = OUT / "created-plan-id.txt"
        if not plan_id and saved_plan_id.is_file():
            plan_id = saved_plan_id.read_text(encoding="utf-8").strip()
        created_now = not plan_id
        OUT.mkdir(parents=True, exist_ok=True)
        if plan_id:
            saved_plan_id.write_text(plan_id, encoding="utf-8")
        else:
            if not set_value(driver, "reason", reason) or not set_value(driver, "amount", "1.00"):
                raise RuntimeError("Could not fill the sandbox plan fields.")
            submit(driver)
            if not wait_for(driver, lambda: "foi criado no Mercado Pago." in driver.js("return document.body.innerText;")):
                raise RuntimeError("Sandbox plan creation was not confirmed; inspect the page before retrying.")
            plan_id = driver.js(
                """const match = document.body.innerText.match(/O plano ([A-Za-z0-9_-]+) foi criado no Mercado Pago/);
                 return match ? match[1] : '';""",
            )
            if not plan_id:
                raise RuntimeError("Created sandbox plan was not found in the refreshed list.")
            OUT.mkdir(parents=True, exist_ok=True)
            (OUT / "created-plan-id.txt").write_text(plan_id, encoding="utf-8")

        if not set_value(driver, "reason", updated_reason) or not set_value(driver, "amount", "1.25") or not set_value(driver, "plan_id", plan_id):
            raise RuntimeError("Could not prepare the sandbox plan update; the created ID is saved in SUITE_OUT.")
        submit(driver)
        if not wait_for(driver, lambda: "O plano foi atualizado no Mercado Pago." in driver.js("return document.body.innerText;")):
            messages = driver.js("return [...document.querySelectorAll('.messages, [role=alert]')].map((node) => node.innerText.trim()).filter(Boolean).join(' | ');")
            safe_messages = messages.replace(plan_id, "[plan-id]")[:200]
            if safe_messages:
                print(f"visible_message={safe_messages}")
            raise RuntimeError("Sandbox plan update was not confirmed; the created ID is saved in SUITE_OUT.")
        state = read_remote_plan(plan_id)
        if state.get("reason") != updated_reason or float(state.get("amount", 0)) != 1.25:
            raise RuntimeError("Sandbox plan values did not match on remote API requery.")
        if created_now:
            print("sandbox_plan_create=PASS")
        print("sandbox_plan_update=PASS")
        print("sandbox_plan_remote_requery=PASS")
        print("created-plan-id-file=SUITE_OUT/created-plan-id.txt")
    finally:
        driver.close()


if __name__ == "__main__":
    main()
