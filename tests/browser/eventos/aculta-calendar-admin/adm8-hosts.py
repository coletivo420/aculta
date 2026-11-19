#!/usr/bin/env python3
"""Review the calendar embed-host administration page in visible Firefox ESR."""

import base64
import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))

HOST = os.environ.get("CALENDAR_HOST", "https://aculta.toca.net.br")
ACCOUNT_HOST = os.environ.get("ACCOUNT_HOST", "https://conta.aculta.toca.net.br")
USERNAME = os.environ.get("ADM8_TEST_USER", "")
PASS_FILE = os.environ.get("ADM8_TEST_PASS_FILE", "")
OUT = os.environ.get("SUITE_OUT", "")
WIDTHS = (1280, 390)

if not OUT or not USERNAME or not PASS_FILE or not os.path.isfile(PASS_FILE):
    print("FAIL: SUITE_OUT, ADM8_TEST_USER e ADM8_TEST_PASS_FILE são obrigatórios.")
    raise SystemExit(1)
if os.environ.get("DISPLAY") != ":0":
    print("FAIL: o Firefox precisa usar DISPLAY=:0.")
    raise SystemExit(1)

os.makedirs(OUT, mode=0o700, exist_ok=True)
os.environ["ACULTA_FF_PROFILE_ROOT"] = OUT

import wdlib  # noqa: E402


def wait_for_page(driver):
    for _ in range(80):
        ready = driver.js("return document.readyState === 'complete'")
        if ready:
            return
        time.sleep(0.25)
    raise RuntimeError("A página não terminou de carregar.")


def login(driver):
    with open(PASS_FILE, encoding="utf-8") as stream:
        password = stream.read().strip()
    driver.get(ACCOUNT_HOST + "/entrar")
    wait_for_page(driver)
    for _ in range(30):
        token_length = driver.js(
            "const field=document.querySelector('[name=cf-turnstile-response]'); "
            "return field ? field.value.length : -1"
        )
        if token_length > 0:
            break
        time.sleep(2)
    if not driver.exists("#edit-name") or not driver.exists("#edit-pass"):
        raise RuntimeError("Formulário de login de teste não encontrado.")
    driver.type("#edit-name", USERNAME)
    driver.type("#edit-pass", password)
    if driver.exists("#edit-submit"):
        driver.click("#edit-submit")
    else:
        driver.submit("#edit-pass")
    for _ in range(40):
        logged_in = driver.js("return document.body.classList.contains('user-logged-in')")
        if logged_in:
            return
        time.sleep(0.5)
    raise RuntimeError("A conta temporária não autenticou no portal de conta.")


def screenshot(driver, width):
    driver.req(
        "POST",
        f"/session/{driver.session}/window/rect",
        {"width": width, "height": 900},
    )
    time.sleep(0.5)
    png = driver.req("GET", f"/session/{driver.session}/screenshot")
    with open(os.path.join(OUT, f"adm8-{width}px.png"), "wb") as stream:
        stream.write(base64.b64decode(png))


def main():
    result = {"route": "/painel-administrativo/calendario/incorporacao", "viewports": []}
    driver = wdlib.Driver()
    try:
        driver.get(HOST + result["route"])
        wait_for_page(driver)
        anonymous_status = driver.js(
            "const n=performance.getEntriesByType('navigation')[0]; "
            "return n ? (n.responseStatus || 0) : 0"
        )
        if anonymous_status != 403:
            raise RuntimeError(f"Acesso anônimo esperado 403, recebido {anonymous_status}.")
        result["anonymous_http"] = anonymous_status

        login(driver)
        driver.get(HOST + result["route"])
        wait_for_page(driver)
        time.sleep(0.5)

        page = driver.js(
            "const n=performance.getEntriesByType('navigation')[0]; "
            "const root=document.querySelector('.aculta-calendar-embed-hosts'); "
            "return {title: document.title, text: root ? root.innerText : '', "
            "logged_in: document.body.classList.contains('user-logged-in'), "
            "status: n ? (n.responseStatus || 0) : 0, "
            "tables: root ? root.querySelectorAll('table').length : 0, "
            "rows: root ? root.querySelectorAll('table tbody tr').length : 0, "
            "headers: root ? root.querySelectorAll('table thead th').length : 0, "
            "forms: root ? root.querySelectorAll('table form').length : 0, "
            "buttons: root ? root.querySelectorAll('table button, table input[type=submit]').length : 0}"
        )
        if not page["logged_in"]:
            raise RuntimeError("A sessão da conta temporária não foi mantida no host principal.")
        if "Incorporação do calendário" not in page["title"]:
            raise RuntimeError(
                "Título ADM8 ausente; "
                f"HTTP {page['status']}, título recebido: {page['title']!r}."
            )
        if page["status"] != 200:
            raise RuntimeError(f"A rota ADM8 retornou HTTP {page['status']}.")
        for label in ("Ambiente de teste", "Produção"):
            if label not in page["text"]:
                raise RuntimeError(f"Cabeçalho ausente: {label}.")
        if page["tables"] != 1 or page["headers"] != 3 or page["rows"] < 1:
            raise RuntimeError("Tabela administrativa não tem os três cabeçalhos esperados.")
        if page["forms"] or page["buttons"]:
            raise RuntimeError("A página somente leitura apresentou controles de edição.")

        result["page"] = {
            "title": page["title"],
            "table_count": page["tables"],
            "data_rows": page["rows"],
            "column_headers": page["headers"],
            "forms": page["forms"],
            "edit_controls": page["buttons"],
        }
        for width in WIDTHS:
            driver.req(
                "POST",
                f"/session/{driver.session}/window/rect",
                {"width": width, "height": 900},
            )
            metrics = driver.js(
                "return {viewport: document.documentElement.clientWidth, "
                "scroll: document.documentElement.scrollWidth}"
            )
            screenshot(driver, width)
            viewport = {
                "requested": width,
                "actual": metrics["viewport"],
                "scroll": metrics["scroll"],
                "horizontal_overflow": metrics["scroll"] > metrics["viewport"],
            }
            result["viewports"].append(viewport)
            if viewport["horizontal_overflow"]:
                raise RuntimeError(f"Rolagem horizontal em {width}px.")

        with open(os.path.join(OUT, "resultado.json"), "w", encoding="utf-8") as stream:
            json.dump(result, stream, ensure_ascii=False, indent=2)
        print(f"PASS: ADM8 autenticada; tabela somente leitura, sem overflow. Evidência: {OUT}")
        return 0
    except Exception as error:
        result["error"] = str(error)
        with open(os.path.join(OUT, "resultado.json"), "w", encoding="utf-8") as stream:
            json.dump(result, stream, ensure_ascii=False, indent=2)
        print(f"FAIL: {error}")
        return 1
    finally:
        driver.close()


if __name__ == "__main__":
    raise SystemExit(main())
