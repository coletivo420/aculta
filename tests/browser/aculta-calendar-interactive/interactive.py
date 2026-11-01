#!/usr/bin/env python3
"""Revisa a visão interativa do calendário no Firefox ESR servido."""

import base64
import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))

HOST = os.environ.get("CALENDAR_HOST", "https://aculta.toca.net.br")
OUT = os.environ.get("SUITE_OUT", "")
ROUTE = "/calendario/interativo"

if not OUT:
    print("FAIL: SUITE_OUT é obrigatório.")
    raise SystemExit(1)
if os.environ.get("DISPLAY") != ":0":
    print("FAIL: o Firefox precisa usar DISPLAY=:0.")
    raise SystemExit(1)

os.makedirs(OUT, mode=0o700, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", OUT)

import wdlib  # noqa: E402


def wait(driver, script, expected=True, attempts=80):
    for _ in range(attempts):
        if bool(driver.js(script)) is expected:
            return True
        time.sleep(0.25)
    return False


def set_window(driver, width):
    driver.req("POST", f"/session/{driver.session}/window/rect", {"width": width, "height": 900})
    time.sleep(0.5)


def screenshot(driver, width):
    png = driver.req("GET", f"/session/{driver.session}/screenshot")
    with open(os.path.join(OUT, f"interactive-{width}px.png"), "wb") as stream:
        stream.write(base64.b64decode(png))


def main():
    result = {"route": ROUTE, "host": HOST, "viewports": []}
    driver = wdlib.Driver()
    try:
        driver.get(HOST + ROUTE)
        if not wait(driver, "return document.readyState === 'complete'"):
            raise RuntimeError("A página não terminou de carregar.")
        result["anonymous_http"] = driver.js(
            "const n=performance.getEntriesByType('navigation')[0]; return n ? (n.responseStatus || 0) : 0"
        )
        if result["anonymous_http"] != 200:
            raise RuntimeError(f"Rota interativa anônima esperada 200, recebida {result['anonymous_http']}.")
        if "Calendário interativo" not in driver.title():
            raise RuntimeError(f"Título inesperado: {driver.title()!r}.")

        # Desktop: o componente é inicializado e a agenda alternativa fica oculta.
        set_window(driver, 1280)
        if not wait(driver, "return typeof window.scheduler !== 'undefined' && typeof window.scheduler.init === 'function'"):
            raise RuntimeError("DHTMLX Scheduler não foi carregado.")
        if not wait(driver, "return !!document.querySelector('#aculta-calendar-interactive.dhx_cal_container')"):
            raise RuntimeError("A grade do DHTMLX não foi inicializada.")

        desktop = driver.js(
            "const c=document.querySelector('#aculta-calendar-interactive'); "
            "const a=document.querySelector('#aculta-calendar-agenda'); "
            "const b=document.querySelector('#aculta-calendar-contrast'); "
            "return {component_hidden: c ? c.hidden : null, "
            "agenda_hidden: a ? a.hidden : null, "
            "button_hidden: b ? b.hidden : null, "
            "days: document.querySelectorAll('#aculta-calendar-interactive .dhx_cal_header .dhx_scale_bar').length, "
            "events: document.querySelectorAll('#aculta-calendar-interactive .dhx_cal_event').length, "
            "scroll: document.documentElement.scrollWidth, "
            "client: document.documentElement.clientWidth, "
            "errors: document.querySelectorAll('.messages--error, [role=alert]').length}"
        )
        if desktop["component_hidden"] is not False:
            raise RuntimeError("O componente interativo está oculto em 1280px.")
        if desktop["agenda_hidden"] is not True:
            raise RuntimeError("A agenda alternativa deveria estar oculta em 1280px.")
        if desktop["days"] < 7:
            raise RuntimeError("A grade do mês não tem os sete dias da semana.")
        if desktop["button_hidden"] is not False:
            raise RuntimeError("O botão de alto contraste está oculto em 1280px.")
        if desktop["scroll"] > desktop["client"]:
            raise RuntimeError("Rolagem horizontal no desktop.")
        if desktop["errors"]:
            raise RuntimeError("Mensagens de erro visíveis no desktop.")
        result["desktop"] = desktop
        screenshot(driver, 1280)

        # Alternância de alto contraste (apenas client-side).
        driver.click("#aculta-calendar-contrast")
        theme = driver.js("return document.documentElement.getAttribute('data-scheduler-theme')")
        pressed = driver.js("return document.querySelector('#aculta-calendar-contrast').getAttribute('aria-pressed')")
        if theme != "contrast-black" or pressed != "true":
            raise RuntimeError(f"Alto contraste não ativou: tema={theme!r}, aria-pressed={pressed!r}.")
        result["contrast"] = {"theme": theme, "aria_pressed": pressed}
        screenshot(driver, "1280-contraste")

        # Mobile: abaixo de md a grade dá lugar à agenda HTML.
        set_window(driver, 390)
        time.sleep(0.5)
        mobile = driver.js(
            "const c=document.querySelector('#aculta-calendar-interactive'); "
            "const a=document.querySelector('#aculta-calendar-agenda'); "
            "const b=document.querySelector('#aculta-calendar-contrast'); "
            "return {viewport: document.documentElement.clientWidth, "
            "component_hidden: c ? c.hidden : null, "
            "agenda_hidden: a ? a.hidden : null, "
            "button_hidden: b ? b.hidden : null, "
            "scroll: document.documentElement.scrollWidth, "
            "client: document.documentElement.clientWidth, "
            "agenda_text: a ? a.innerText.slice(0, 120) : ''}"
        )
        if mobile["component_hidden"] is not True:
            raise RuntimeError("O componente interativo deveria ficar oculto abaixo de md.")
        if mobile["agenda_hidden"] is not False:
            raise RuntimeError("A agenda alternativa deveria aparecer abaixo de md.")
        if mobile["scroll"] > mobile["client"]:
            raise RuntimeError("Rolagem horizontal no layout estreito.")
        if not mobile["agenda_text"].strip():
            raise RuntimeError("A agenda alternativa está vazia.")
        result["mobile"] = mobile
        screenshot(driver, 390)

        result["scheduler_version"] = driver.js(
            "return window.scheduler && window.scheduler.version ? window.scheduler.version : null"
        )
        with open(os.path.join(OUT, "resultado.json"), "w", encoding="utf-8") as stream:
            json.dump(result, stream, ensure_ascii=False, indent=2)
        print(f"PASS: calendário interativo 200, grade inicializada, contraste e fallback móvel. Evidência: {OUT}")
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
