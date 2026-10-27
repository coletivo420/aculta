#!/usr/bin/env python3
"""Sign in to the authorized Mercado Pago test buyer, without submitting a payment."""

import re
import sys
import time
import unicodedata
from pathlib import Path
from urllib.parse import urlparse

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "aculta-cpf-checkout"))

from wdlib import Driver


ROOT = Path(__file__).resolve().parents[3]
BUYER_FILE = ROOT / "secrets" / "mercadolivre_test_user"
HOME_URL = "https://www.mercadopago.com.br/"


def normalize(value):
    value = unicodedata.normalize("NFKD", value)
    return re.sub(r"[^a-z0-9]+", "", value.encode("ascii", "ignore").decode().lower())


def read_credentials():
    lines = BUYER_FILE.read_text(encoding="utf-8", errors="replace").splitlines()
    values = {}
    labels = {
        "usuariodeteste": "username",
        "senha": "password",
        "codigodeverificacao": "verification_code",
    }
    for index, line in enumerate(lines):
        key = labels.get(normalize(line))
        if not key:
            continue
        value = next((item.strip() for item in lines[index + 1:] if item.strip()), "")
        if value:
            values[key] = value
    if not values.get("username") or not values.get("password"):
        raise ValueError("required test buyer fields are unavailable")
    return values


def visible_inputs(driver):
    return driver.js(
        """return Array.from(document.querySelectorAll('input'))
          .filter(e => e.type !== 'hidden' && !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length))
          .map(e => ({type: e.type || 'text', name: e.name || '', id: e.id || '',
            placeholder: e.placeholder || '', autocomplete: e.autocomplete || '',
            selector: e.id ? '#' + CSS.escape(e.id) : (e.name ? '[name="' + CSS.escape(e.name) + '"]' : '')}));"""
    )


def submit_input(driver, selector):
    driver.js(
        """const input = document.querySelector(arguments[0]);
          if (!input || !input.form) return false;
          input.form.requestSubmit();
          return true;""",
        [selector],
    )


def wait_for_inputs(driver, seconds=25):
    deadline = time.time() + seconds
    while time.time() < deadline:
        fields = visible_inputs(driver)
        if fields:
            return fields
        time.sleep(0.5)
    return []


def choose(fields, predicate):
    return next((field for field in fields if field["selector"] and predicate(field)), None)


def logged_in(driver):
    host = (urlparse(driver.url()).hostname or "").lower()
    fields = visible_inputs(driver)
    pending = any(field["type"] == "password" for field in fields)
    account_marker = driver.js(
        """const body = (document.body ? document.body.innerText : '').toLowerCase();
          const accountControl = document.querySelector(
            '[data-testid*="account"], [aria-label*="conta" i], a[href*="logout"], button[aria-label*="perfil" i]');
          return !!accountControl || /minha conta|encerrar sess[aã]o|sair da conta/.test(body);"""
    )
    blocked = provider_blocked(driver)
    return (
        host.endswith(("mercadopago.com.br", "mercadolivre.com.br"))
        and not pending
        and not blocked
        and bool(account_marker)
    )


def provider_blocked(driver):
    return driver.js(
        """const navigation = performance.getEntriesByType('navigation')[0];
          const body = document.body ? document.body.innerText : '';
          return (navigation && navigation.responseStatus === 403)
            || /\\b403\\b|access denied|forbidden/i.test(body);"""
    )


def main():
    credentials = read_credentials()
    driver = Driver()
    outcome = "login_incomplete"
    try:
        driver.get(HOME_URL)
        time.sleep(2)
        login_action = driver.js(
            """const candidates = Array.from(document.querySelectorAll('a,button,[role="button"]'));
              const target = candidates.find(el => {
                const label = (el.innerText || el.getAttribute('aria-label') || '').toLowerCase();
                return label.includes('iniciar sessão') || label.includes('iniciar sessao')
                  || label.includes('entrar') || label.includes('login');
              });
              if (!target) return {href: '', clicked: false};
              if (target.tagName === 'A' && target.href) return {href: target.href, clicked: false};
              target.click();
              return {href: '', clicked: true};"""
        )
        if login_action["href"]:
            driver.get(login_action["href"])
        elif login_action["clicked"]:
            time.sleep(2)
        else:
            driver.get("https://www.mercadopago.com.br/hub/login")
        fields = wait_for_inputs(driver)
        user = choose(
            fields,
            lambda field: field["type"] in ("email", "text")
            and re.search(r"user|usuario|email|login", field["name"] + field["id"] + field["placeholder"], re.I),
        )
        if user is None:
            user = choose(fields, lambda field: field["type"] in ("email", "text"))
        if user is None:
            outcome = "provider_access_blocked" if provider_blocked(driver) else "login_form_unavailable"
            return outcome
        driver.type(user["selector"], credentials["username"])
        submit_input(driver, user["selector"])
        time.sleep(2)

        fields = wait_for_inputs(driver, 15)
        password = choose(fields, lambda field: field["type"] == "password")
        if password:
            driver.type(password["selector"], credentials["password"])
            submit_input(driver, password["selector"])
            time.sleep(3)

        fields = visible_inputs(driver)
        code = choose(
            fields,
            lambda field: re.search(
                r"one-time-code|verification|verificacao|otp|codigo|code",
                field["autocomplete"] + field["name"] + field["id"] + field["placeholder"],
                re.I,
            ),
        )
        if code and credentials.get("verification_code"):
            driver.type(code["selector"], credentials["verification_code"])
            submit_input(driver, code["selector"])
            time.sleep(3)

        fields = visible_inputs(driver)
        challenge = any(
            re.search(r"captcha|challenge|turnstile", field["name"] + field["id"] + field["placeholder"], re.I)
            for field in fields
        ) or driver.exists("iframe[src*='captcha'], iframe[src*='challenge']")
        if challenge:
            outcome = "manual_security_challenge"
        elif logged_in(driver):
            outcome = "authenticated_test_buyer"
        elif provider_blocked(driver):
            outcome = "provider_access_blocked"
        elif any(field["type"] == "password" for field in fields):
            outcome = "authentication_rejected_or_incomplete"
        else:
            outcome = "login_incomplete"
        return outcome
    except Exception:
        # Never print exception text: browser errors can contain form details.
        return "browser_or_login_error"
    finally:
        driver.close()
        credentials.clear()


if __name__ == "__main__":
    print("result=" + main())
