"""Loads a sandbox Mercado Pago preference without submitting a payment."""

import base64
import os
import re
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent / "aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-mercadopago-preference"))
INIT_POINT_FILE = Path(
    os.environ.get("ACULTA_MP_INIT_POINT_FILE", str(OUT / "init-point.url"))
)


def safe_path(path):
    """Redacts the provider preference UUID from a URL path before logging."""
    return re.sub(
        r"/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/",
        "/[preference-id]/",
        path,
        flags=re.IGNORECASE,
    )


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("DISPLAY=:0 é obrigatório para Firefox visível.")
    init_point = INIT_POINT_FILE.read_text(encoding="utf-8").strip()
    parsed = urlparse(init_point)
    host = (parsed.hostname or "").lower()
    if parsed.scheme != "https" or not (
        host == "mercadopago.com.br" or host.endswith(".mercadopago.com.br")
    ):
        raise SystemExit("O arquivo não contém um init_point HTTPS do Mercado Pago Brasil.")

    OUT.mkdir(parents=True, exist_ok=True)
    driver = Driver()
    try:
        driver.get(init_point)
        time.sleep(7)
        summary = driver.js(
            """const body = (document.body?.innerText || '').toLowerCase();
              const navigation = performance.getEntriesByType('navigation')[0];
              return {
                host: location.hostname,
                path: location.pathname,
                title: document.title,
                response_status: navigation?.responseStatus || 0,
                visible_inputs: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')]
                  .filter(e => e.offsetParent !== null).length,
                captcha: !!document.querySelector('iframe[src*="captcha"], [data-sitekey], [class*=captcha i]'),
                challenge: /access denied|forbidden|security check|verify you are human/.test(body)
              };"""
        )
        with (OUT / "provider-checkout.png").open("wb") as image:
            image.write(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))

        print(
            "provider page:",
            {
                "host_ok": summary["host"] == "mercadopago.com.br"
                or summary["host"].endswith(".mercadopago.com.br"),
                "path": safe_path(summary["path"]),
                "title": summary["title"],
                "response_status": summary["response_status"],
                "visible_inputs": summary["visible_inputs"],
                "captcha": summary["captcha"],
                "challenge": summary["challenge"],
            },
        )
        if summary["response_status"] == 403 or summary["challenge"]:
            print("RESULT=provider_security_challenge")
        elif summary["host"] == "mercadopago.com.br" or summary["host"].endswith(".mercadopago.com.br"):
            print("RESULT=provider_payment_page_loaded")
        else:
            print("RESULT=provider_redirect_unexpected")

        if os.environ.get("ACULTA_MP_ADVANCE_TO_CUSTOMER") == "1" and not summary["challenge"]:
            advanced = driver.js(
                """const button = [...document.querySelectorAll('button')]
                  .find(e => (e.innerText || '').trim().toLowerCase() === 'continuar');
                  if (!button) return false;
                  button.click(); return true;"""
            )
            if advanced:
                time.sleep(6)
                next_page = driver.js(
                    """const body = (document.body?.innerText || '').toLowerCase();
                      const navigation = performance.getEntriesByType('navigation')[0];
                      return {
                        host: location.hostname,
                        path: location.pathname,
                        title: document.title,
                        response_status: navigation?.responseStatus || 0,
                        visible_inputs: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')]
                          .filter(e => e.offsetParent !== null).length,
                        login_required: /iniciar sess[aã]o|identifique-se|entre na sua conta/.test(body),
                        challenge: /access denied|forbidden|security check|verify you are human/.test(body)
                      };"""
                )
                with (OUT / "provider-customer-stage.png").open("wb") as image:
                    image.write(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
                print(
                    "customer stage:",
                    {
                        "host_ok": next_page["host"] == "mercadopago.com.br"
                        or next_page["host"].endswith(".mercadopago.com.br"),
                        "path": safe_path(next_page["path"]),
                        "title": next_page["title"],
                        "response_status": next_page["response_status"],
                        "visible_inputs": next_page["visible_inputs"],
                        "login_required": next_page["login_required"],
                        "challenge": next_page["challenge"],
                    },
                )
                if next_page["challenge"] or next_page["response_status"] == 403:
                    print("RESULT=provider_customer_security_challenge")
                elif next_page["login_required"]:
                    print("RESULT=provider_customer_login_required")
                else:
                    print("RESULT=provider_customer_stage_loaded")
            else:
                print("RESULT=provider_continue_button_missing")
    finally:
        driver.close()


if __name__ == "__main__":
    main()
