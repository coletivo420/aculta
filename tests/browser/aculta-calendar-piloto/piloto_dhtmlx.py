#!/usr/bin/env python3
"""Piloto isolado do DHTMLX Scheduler (edição GPL). Não é código de produção.

Mede: renderização de eventos, atributos de acessibilidade, navegação por teclado (Tab e setas),
rolagem horizontal em 320 px (num iframe de 320 px, porque o Firefox não reduz a janela abaixo de ~500 px),
capturas em 1280 px e 320 px, e URLs externas no CSS do componente.

Variáveis de ambiente:
  SUITE_OUT      pasta de saída, obrigatória e fora do repositório
  PILOT_URL      página do piloto (padrão: index.html local)
  PILOT_URL_320  página com o iframe de 320 px (padrão: viewport320.html local)
  PILOT_CSS      CSS do componente, para listar URLs externas
  PILOT_LABEL    rótulo da variante (ex.: "padrao" ou "key_nav"), usado nos nomes dos arquivos
"""
import base64, json, os, pathlib, re, sys, time

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1] / "aculta-cpf-checkout"))
os.environ.setdefault("FIREFOX", "/usr/bin/firefox")
import wdlib  # noqa: E402

URL = os.environ.get("PILOT_URL", "http://127.0.0.1:8765/index.html")
URL_320 = os.environ.get("PILOT_URL_320", "http://127.0.0.1:8765/viewport320.html")
CSS = pathlib.Path(os.environ.get("PILOT_CSS", "dhtmlx/dhtmlxscheduler.css"))
LABEL = os.environ.get("PILOT_LABEL", "padrao")
OUT = pathlib.Path(os.environ["SUITE_OUT"])  # obrigatório: pasta fora do repositório
OUT.mkdir(parents=True, exist_ok=True)
TAB, ARROW_RIGHT = "", ""  # códigos Unicode do WebDriver: Tab e seta direita


def key(d, k, times=1):
    for _ in range(times):
        d.req("POST", f"/session/{d.session}/actions", {"actions": [{"type": "key", "id": "kbd", "actions": [
            {"type": "keyDown", "value": k}, {"type": "keyUp", "value": k}]}]})
        time.sleep(0.15)


def focus_desc(d):
    return d.js("""const a=document.activeElement; return a ? {
        tag:a.tagName, cls:(a.className||'').toString().slice(0,80), role:a.getAttribute('role'),
        label:a.getAttribute('aria-label'), tabindex:a.getAttribute('tabindex'),
        text:(a.textContent||'').trim().slice(0,60) } : null;""")


def medir_1280(d, results):
    d.get(URL)
    d.req("POST", f"/session/{d.session}/timeouts", {"pageLoad": 60000, "script": 60000})
    time.sleep(1.5)
    results["scheduler_carregado"] = d.js("return typeof scheduler !== 'undefined' && !!scheduler.init")
    results["key_nav_configurado"] = d.js("return typeof scheduler !== 'undefined' ? scheduler.config.key_nav : null")
    # Na visão de mês da v7, cada evento tem um .dhx_cal_event_line_content (medido no DOM).
    results["eventos_renderizados_mes"] = d.js(
        "return document.querySelectorAll('.dhx_cal_event_line_content').length")
    results["controles_com_tabindex"] = d.js(
        "return [...document.querySelectorAll('.dhx_cal_tab, .dhx_cal_nav_button')].map(e=>e.getAttribute('tabindex'))")
    results["container_tabindex"] = d.js(
        "return document.querySelector('.dhx_cal_container') ? document.querySelector('.dhx_cal_container').getAttribute('tabindex') : 'sem container'")
    results["aria"] = d.js("""return {
        com_role: document.querySelectorAll('[role]').length,
        com_aria_label: document.querySelectorAll('[aria-label]').length,
        role_application: document.querySelectorAll('[role=application]').length,
        roles: [...new Set([...document.querySelectorAll('[role]')].map(e=>e.getAttribute('role')))].slice(0,20)
    };""")

    # Teclado: 12 Tab a partir do body, depois duas setas à direita.
    d.js("document.body.focus();")
    trilha = []
    for _ in range(12):
        key(d, TAB)
        trilha.append(focus_desc(d))
    results["teclado_tab_trilha"] = trilha
    key(d, ARROW_RIGHT, 2)
    results["teclado_apos_setas"] = focus_desc(d)

    (OUT / f"piloto-{LABEL}-1280px.png").write_bytes(base64.b64decode(d.req("GET", f"/session/{d.session}/screenshot")))


def medir_320(d, results):
    d.get(URL_320)
    d.req("POST", f"/session/{d.session}/timeouts", {"pageLoad": 60000, "script": 60000})
    time.sleep(2)
    results["iframe_320px"] = d.js("""const f=document.getElementById('f'); const doc=f.contentDocument;
        return {largura_iframe: f.clientWidth, viewport_interno: doc.documentElement.clientWidth,
                scroll_interno: doc.documentElement.scrollWidth,
                overflow_horizontal: doc.documentElement.scrollWidth > doc.documentElement.clientWidth};""")
    (OUT / f"piloto-{LABEL}-320px.png").write_bytes(base64.b64decode(d.req("GET", f"/session/{d.session}/screenshot")))


def main():
    results = {"variante": LABEL, "url": URL, "url_320": URL_320,
               "ambiente": "piloto isolado, dados fictícios, servidor local 127.0.0.1"}
    css_text = CSS.read_text(encoding="utf-8", errors="ignore") if CSS.exists() else ""
    results["css_urls_externas"] = sorted(set(re.findall(r"https?://[^\s\"')]+", css_text)))

    d = wdlib.Driver()
    try:
        medir_1280(d, results)
        medir_320(d, results)
    finally:
        d.close()

    arquivo = OUT / f"piloto-{LABEL}-resultados.json"
    arquivo.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(results, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
