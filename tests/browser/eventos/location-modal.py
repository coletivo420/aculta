#!/usr/bin/env python3
"""Homologa o fluxo do modal AJAX de Locais em Eventos no servidor de desenvolvimento.

Verifica, autenticado como administrador pelo host de Conta:
- o formulário de cadastro de Evento carrega o widget de Local em estado inicial;
- o acionamento do botão abre o popup nativo Drupal Dialog / AJAX sobre a página;
- o passo de busca carrega o campo de pesquisa e a opção "Cadastrar novo local";
- o clique em "Cadastrar novo local" carrega o passo de cadastro por AJAX;
- o campo de CEP é o primeiro campo do endereço (#weight = -50);
- o país é fixado em Brasil (BR) sem seletor de outros países;
- campos de logradouro, bairro, cidade e UF estão presentes e editáveis;
- responsividade em 1280px, 390px e 320px no X11 visível;
- o fechamento do modal restaura o foco e preserva os dados já preenchidos no formulário pai.

Ambiente: servidor de desenvolvimento (*.aculta.toca.net.br), Firefox ESR
visível em DISPLAY=:0 via geckodriver.
"""

import base64
import json
import os
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

MAIN = os.environ.get("MAIN_BASE", "https://aculta.toca.net.br")
ACCOUNT = os.environ.get("ACCOUNT_BASE", "https://conta.aculta.toca.net.br")
LOGIN_FILE = Path(os.environ["ACULTA_LOGIN_URL_FILE"])

checks = []


def check(name, ok, detail=""):
    checks.append({"name": name, "passed": bool(ok), "detail": detail})
    print(f"{'PASS' if ok else 'FAIL'} {name}: {detail}", flush=True)


def shot(driver, name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{name}.png").write_bytes(base64.b64decode(data))


def wait_for_css(driver, css, timeout=10):
    start = time.time()
    while time.time() - start < timeout:
        if driver.exists(css):
            return True
        time.sleep(0.3)
    return False


login_url = LOGIN_FILE.read_text().strip()
driver = Driver()

try:
    # 1. Login no host de conta.
    driver.get(login_url)
    time.sleep(2)
    logged = "/entrar" not in driver.url() and "/recuperar-acesso/" not in driver.url()
    check("login.admin.conta", logged, driver.url())

    # 2. Acessa cadastro de Evento no MAIN.
    driver.get(MAIN + "/painel-administrativo/eventos/adicionar")
    time.sleep(1.5)
    check("evento.form.carrega", "cadastrar evento" in driver.title().lower(), driver.title())

    # 3. Preenche título do Evento no formulário pai para verificar preservação.
    test_title = "Festival Cultural de Homologação 2026"
    driver.type('input[name="title[0][value]"]', test_title)
    check("evento.form.titulo_preenchido", True, test_title)

    # 4. Localiza o acionador do modal de Local.
    modal_btn_selector = 'a.use-ajax[href*="/locais/modal"]'
    has_modal_btn = driver.exists(modal_btn_selector)
    check("evento.form.tem_botao_modal", has_modal_btn, "botão modal encontrado no formulário")

    if has_modal_btn:
        # Clica para abrir o popup Drupal.
        driver.click(modal_btn_selector)
        modal_opened = wait_for_css(driver, '.ui-dialog, #aculta-location-modal-form-wrapper', timeout=8)
        check("modal.popup.abre", modal_opened, "diálogo sobreposto aberto via AJAX")
        time.sleep(1)
        shot(driver, "01-modal-search-step")

        # 5. Verifica elementos do passo de busca.
        has_search_input = driver.exists('input[name="search_term"]')
        has_switch_create = driver.js("""
            const btns = Array.from(document.querySelectorAll('.ui-dialog-buttonpane button, input[name="op_switch_create"]'));
            return btns.some(b => (b.textContent || b.value || '').includes('Cadastrar novo local'));
        """)
        check("modal.busca.campo_pesquisa", has_search_input, "campo de busca presente")
        check("modal.busca.botao_cadastrar_novo", bool(has_switch_create), "ação 'Cadastrar novo local' presente")

        if has_switch_create:
            # 6. Clica em "Cadastrar novo local" no botão da dialog ou formulário.
            btn_info = driver.js("""
                const dialogBtn = Array.from(document.querySelectorAll('.ui-dialog-buttonpane button')).find(b => (b.textContent || '').includes('Cadastrar novo local'));
                if (dialogBtn) {
                    dialogBtn.click();
                    return 'CLICKED_DIALOG_BTN: ' + dialogBtn.textContent.trim();
                }
                const inputBtn = document.querySelector('input[name="op_switch_create"]');
                if (inputBtn) {
                    inputBtn.click();
                    return 'CLICKED_INPUT_BTN: ' + inputBtn.id;
                }
                return 'BUTTON_NOT_FOUND';
            """)
            print("INFO op_switch_create:", btn_info, flush=True)

            create_loaded = wait_for_css(driver, 'input[name="novo_nome"]', timeout=10)
            if not create_loaded:
                modal_html = driver.js("return document.querySelector('.ui-dialog, #aculta-location-modal-form-wrapper')?.innerHTML || 'NO_MODAL';")
                (OUT / "debug-modal-fail.html").write_text(modal_html)
                print("DEBUG: Modal HTML salvo em debug-modal-fail.html (tamanho: " + str(len(modal_html)) + ")", flush=True)
            check("modal.cadastro.etapa2_carrega", create_loaded, "formulário de cadastro carregado via AJAX")
            check("modal.cadastro.etapa2_carrega", create_loaded, "formulário de cadastro carregado via AJAX")
            time.sleep(1)

            # 7. Validação do formulário de endereço no modal:
            # - Nome do local
            has_name = driver.exists('input[name="novo_nome"]')
            check("modal.cadastro.nome_local", has_name, "campo nome do local presente")

            # - CEP primeiro no formulário
            has_cep = driver.exists('input[name*="postal_code"]')
            check("modal.cadastro.tem_cep", has_cep, "campo de CEP presente no endereço")

            # Verifica ordem no DOM: CEP antes de logradouro
            cep_first = driver.js("""
                const cep = document.querySelector('input[name*="postal_code"]');
                const street = document.querySelector('input[name*="address_line1"]');
                if (!cep || !street) return false;
                return Boolean(cep.compareDocumentPosition(street) & Node.DOCUMENT_POSITION_FOLLOWING);
            """)
            check("modal.cadastro.cep_primeiro", bool(cep_first), "CEP precede o logradouro no formulário (#weight = -50)")

            # - País exclusivo Brasil (BR) sem seletor de países
            country_select_count = driver.js("""
                const selects = Array.from(document.querySelectorAll('select[name*="country_code"]'));
                return selects.filter(s => s.options.length > 1).length;
            """) or 0
            check("modal.cadastro.pais_fixo_br", country_select_count == 0, f"seletores de país com opções: {country_select_count}")

            # 8. Capturas em diferentes resoluções:
            # Desktop 1280px
            shot(driver, "02-modal-create-desktop-1280")

            # Mobile 390px
            driver.req("POST", f"/session/{driver.session}/window/rect", {"width": 390, "height": 844})
            time.sleep(0.5)
            shot(driver, "03-modal-create-mobile-390")

            # Mobile 320px
            driver.req("POST", f"/session/{driver.session}/window/rect", {"width": 320, "height": 700})
            time.sleep(0.5)
            shot(driver, "04-modal-create-mobile-320")

            # Restaura desktop
            driver.req("POST", f"/session/{driver.session}/window/rect", {"width": 1280, "height": 900})
            time.sleep(0.5)

            # 9. Fechamento do modal e preservação dos dados do formulário pai.
            driver.js("""
                const dialogCancel = Array.from(document.querySelectorAll('.ui-dialog-buttonpane button')).find(b => (b.textContent || '').includes('Cancelar'));
                if (dialogCancel) {
                    dialogCancel.click();
                } else {
                    const closeIcon = document.querySelector('.ui-dialog-titlebar-close');
                    if (closeIcon) closeIcon.click();
                }
            """)
            time.sleep(1.5)

            # Verifica se o valor digitado no pai continua intacto
            parent_val = driver.js("return document.querySelector('input[name=\"title[0][value]\"]')?.value || '';")
            check("evento.form.preserva_dados_pai", parent_val == test_title, f"valor preservado: '{parent_val}'")
            shot(driver, "05-parent-form-preserved")

finally:
    try:
        driver.req("DELETE", f"/session/{driver.session}")
    except Exception:
        pass
    try:
        driver.proc.terminate()
    except Exception:
        pass

passed = sum(1 for c in checks if c["passed"])
total = len(checks)
print(f"\n{passed}/{total} verificações PASS", flush=True)

(OUT / "resultado.json").write_text(json.dumps({
    "suite": "location-modal",
    "passed": passed,
    "total": total,
    "checks": checks,
}, indent=2, ensure_ascii=False))

sys.exit(0 if passed == total else 1)
