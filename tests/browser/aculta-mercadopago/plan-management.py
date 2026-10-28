#!/usr/bin/env python3
"""Read-only visual smoke for Mercado Pago plan management in Studio."""
import base64
import os
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "aculta-cpf-checkout"))
from wdlib import Driver


BASE_URL = os.environ.get("ACULTA_ACCOUNT_BASE_URL", "https://conta.aculta.toca.net.br").rstrip("/")
LOGIN_URL = os.environ.get("ACULTA_MP_STUDIO_LOGIN_URL", "")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-mercadopago-plan-management"))


def wait_for(driver, predicate, seconds=20):
    deadline = time.time() + seconds
    while time.time() < deadline:
        if predicate():
            return True
        time.sleep(0.25)
    return False


def on_test_domain(driver):
    host = (urlparse(driver.url()).hostname or "").lower()
    return host == "aculta.toca.net.br" or host.endswith(".aculta.toca.net.br")


def screenshot(driver, name):
    value = driver.req("GET", f"/session/{driver.session}/screenshot")
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / f"{name}.png").write_bytes(base64.b64decode(value))


def viewport(driver, width):
    driver.req("POST", f"/session/{driver.session}/window/rect",
               {"x": 20, "y": 20, "width": width, "height": 900})
    time.sleep(0.4)
    return driver.js("return {width: innerWidth, doc: document.documentElement.scrollWidth};")


def main():
    if not LOGIN_URL:
        raise SystemExit("ACULTA_MP_STUDIO_LOGIN_URL is required; do not print one-time login URLs.")
    parsed = urlparse(LOGIN_URL)
    if parsed.scheme != "https" or not (parsed.hostname or "").endswith(".aculta.toca.net.br"):
        raise SystemExit("The login URL must use the HTTPS ACULTA test domain.")

    driver = Driver()
    checks = []
    try:
        driver.get(LOGIN_URL)
        if not wait_for(driver, lambda: on_test_domain(driver)):
            raise RuntimeError("ACULTA test login did not complete.")
        driver.get(BASE_URL + "/")
        if not wait_for(driver, lambda: driver.exists("a[href*='/studio/conta/mercado-pago/planos']")):
            raise RuntimeError("The Studio hub did not expose the authorized plans entry.")
        checks.append("studio_entry=PASS")
        screenshot(driver, "01-studio-hub")
        driver.click("a[href*='/studio/conta/mercado-pago/planos']")
        if not wait_for(driver, lambda: driver.exists("input[name='reason']")):
            screenshot(driver, "error-plan-form")
            raise RuntimeError(f"The plan management form did not load (title={driver.title()!r}, url={urlparse(driver.url()).path!r}).")
        checks.append("plans_form=PASS")
        action_host = driver.js("return new URL(document.querySelector('form').getAttribute('action'), location.href).hostname;")
        if action_host != "aculta.toca.net.br":
            raise RuntimeError("The plan form does not submit on the canonical MAIN domain.")
        checks.append("integration_action_main_domain=PASS")
        if not driver.exists("main.aculta-main"):
            screenshot(driver, "error-studio-shell")
            raise RuntimeError(f"The plan management page did not render in the ACULTA420 shell (title={driver.title()!r}, url={urlparse(driver.url()).path!r}).")
        checks.append("studio_shell=PASS")
        if not driver.exists("table") or not driver.exists("input[name='amount']") or not driver.exists("input[name='frequency']"):
            raise RuntimeError("The plan list or recurring amount fields are missing.")
        checks.append("plan_list_and_fields=PASS")
        if not all(driver.exists(f"label[for='edit-{field}']") for field in ("reason", "amount", "frequency", "plan-id")):
            raise RuntimeError("A plan field is missing its visible label.")
        if not driver.exists("table caption"):
            raise RuntimeError("The plan list is missing its accessible caption.")
        checks.append("field_labels_and_table_caption=PASS")
        for width in (390, 1280):
            size = viewport(driver, width)
            if size["doc"] > size["width"] + 1:
                raise RuntimeError(f"Horizontal overflow at {width}px.")
            checks.append(f"reflow_{width}px=PASS")
            screenshot(driver, f"02-plans-{width}")
        print("\n".join(checks))
        print(f"screenshots={OUT}")
    finally:
        driver.close()


if __name__ == "__main__":
    main()
