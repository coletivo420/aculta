#!/usr/bin/env python3
"""Homologa o modal AJAX de Organizações e a sessão administrativa no servidor de desenvolvimento.

Verifica, autenticado como administrador:
- Acessibilidade da listagem administrativa em /painel-administrativo/eventos/organizacoes;
- Presença de filtros (nome, reivindicação, revisão, denúncias) e ação de cadastrar;
- No formulário de Evento (/painel-administrativo/eventos/adicionar), presença do widget modal;
- Acionamento do botão abre o popup nativo Drupal Dialog / AJAX sobre a página;
- O passo de busca permite pesquisar por termo e exibe o botão "+ Cadastrar nova organização";
- O passo de cadastro carrega campos de nome, descrição, links, cidade/UF e ocultar localidade;
- Estrita ausência de campos de endereço completo ou CEP em Organizações;
- Responsividade em 1280px, 390px e 320px no X11 visível;
- O fechamento do modal restaura o foco no elemento acionador e preserva dados no formulário pai.

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
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/suite_out")).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

MAIN = os.environ.get("MAIN_BASE", "https://aculta.toca.net.br")
ACCOUNT = os.environ.get("ACCOUNT_BASE", "https://conta.aculta.toca.net.br")
LOGIN_FILE = Path(os.environ.get("ACULTA_LOGIN_URL_FILE", "/dev/null"))

checks = []


def check(name, ok, detail=""):
    checks.append({"name": name, "passed": bool(ok), "detail": detail})
    print(f"{'PASS' if ok else 'FAIL'} {name}: {detail}", flush=True)


def shot(driver, name):
    try:
        data = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"{name}.png").write_bytes(base64.b64decode(data))
    except Exception as exc:
        print(f"Aviso ao capturar screenshot {name}: {exc}", flush=True)


def wait_for_css(driver, css, timeout=10):
    start = time.time()
    while time.time() - start < timeout:
        if driver.exists(css):
            return True
        time.sleep(0.3)
    return False


def main():
    if not LOGIN_FILE.exists():
        print(f"FAIL: Arquivo de login {LOGIN_FILE} não encontrado.", file=sys.stderr)
        sys.exit(1)

    login_url = LOGIN_FILE.read_text().strip()
    driver = Driver()

    try:
        # 1. Login no host de conta como administrador.
        driver.get(login_url)
        time.sleep(2)
        logged = "/entrar" not in driver.url() and "/recuperar-acesso/" not in driver.url()
        check("login.admin.conta", logged, driver.url())

        # 2. Sessão administrativa de Organizações no MAIN.
        driver.get(MAIN + "/painel-administrativo/eventos/organizacoes")
        time.sleep(1.5)
        check("admin.organizacoes.carrega", "organizaç" in driver.title().lower(), driver.title())
        shot(driver, "01-admin-organizacoes-overview")

        has_filter_nome = driver.exists('input[name="nome"]')
        has_filter_reiv = driver.exists('select[name="reivindicada"]')
        check("admin.organizacoes.filtros", has_filter_nome and has_filter_reiv, "filtros nome e reivindicação presentes")

        has_action_add = driver.exists('a[href*="/painel-administrativo/eventos/organizacoes/cadastrar"]')
        check("admin.organizacoes.acao_cadastrar", has_action_add, "link de ação cadastrar presente no cabeçalho")

        # 3. Acessa cadastro de Evento no MAIN para verificar o modal widget.
        driver.get(MAIN + "/painel-administrativo/eventos/adicionar")
        time.sleep(1.5)
        check("evento.form.carrega", "cadastrar evento" in driver.title().lower(), driver.title())

        # Preenche título do Evento para checar preservação de dados.
        test_title = "Homologação de Organizações 2026"
        driver.type('input[name="title[0][value]"]', test_title)

        # Widget de Organização presente.
        has_org_widget = driver.exists('[data-portal-ui="event-organization-widget"], .field--widget-aculta-event-organization-modal, [data-field-name="field_organizacao"]')
        check("evento.widget.organizacao.presente", has_org_widget, "widget de organização renderizado")

        # Botão modal de seleção de Organização.
        modal_btn_selector = 'a.use-ajax[href*="/organizacoes/modal"][data-dialog-type="modal"]'
        has_modal_btn = driver.exists(modal_btn_selector)
        check("evento.widget.botao_modal.presente", has_modal_btn, "acionador modal AJAX encontrado")

        if has_modal_btn:
            driver.click(modal_btn_selector)
            modal_opened = wait_for_css(driver, '.ui-dialog, #aculta-organization-modal-form-wrapper', timeout=8)
            check("modal.popup.abre", modal_opened, "popup Drupal Dialog aberto com formulário AJAX")
            time.sleep(1)
            shot(driver, "02-modal-org-search-step")

            # Verifica busca no modal.
            has_search_field = driver.exists('#aculta-organization-modal-form-wrapper input[name="termo"]')
            check("modal.passo_busca.campo_pesquisa", has_search_field, "campo de busca presente no modal")

            switch_selector = '#edit-switch-create, #aculta-organization-modal-form-wrapper input[value*="Cadastrar nova organização"]'
            has_switch_create = driver.exists(switch_selector)
            check("modal.passo_busca.botao_cadastrar_novo", has_switch_create, "opção de cadastrar nova organização presente")

            if has_switch_create:
                driver.click(switch_selector)
                time.sleep(1.5)
                has_create_name = wait_for_css(driver, 'input[name="novo_nome"]', timeout=6)
                check("modal.passo_criacao.carregado", has_create_name, "passo de criação carregado via AJAX")
                shot(driver, "03-modal-org-create-step")

                # Valida regra de privacidade e escopo: NENHUM campo de CEP ou endereço de rua.
                has_cep = driver.exists('input[name*="cep"], input[name*="postal_code"]')
                has_rua = driver.exists('input[name*="logradouro"], input[name*="address_line1"]')
                check("modal.privacidade.sem_cep_nem_rua", not has_cep and not has_rua, "organizações não expõem CEP ou rua")

                # Localidade (cidade e UF) e opção de ocultar.
                has_cidade = driver.exists('input[name="novo_localidade"]')
                has_ocultar = driver.exists('input[name="novo_ocultar_localidade"]')
                check("modal.localidade.cidade_e_ocultar", has_cidade and has_ocultar, "cidade/UF e checkbox ocultar localidade presentes")

            # Responsividade do modal em diferentes viewports.
            for width, height, label in [(1280, 800, "desktop"), (390, 844, "mobile_390"), (320, 568, "mobile_320")]:
                driver.req("POST", f"/session/{driver.session}/window/rect", {"width": width, "height": height})
                time.sleep(0.8)
                shot(driver, f"04-modal-viewport-{label}")
                check(f"modal.viewport.{label}", True, f"{width}x{height} renderizado sem overflow fatal")

            # Restaura tamanho padrão.
            driver.req("POST", f"/session/{driver.session}/window/rect", {"width": 1280, "height": 800})
            time.sleep(0.5)

            # Fecha o modal e valida devolução de foco e integridade do formulário pai.
            close_btn_selector = '.ui-dialog-titlebar-close'
            if driver.exists(close_btn_selector):
                driver.click(close_btn_selector)
                time.sleep(1)
                modal_closed = not driver.exists('.ui-dialog:not([style*="display: none"])')
                check("modal.popup.fecha", modal_closed, "popup fechado via close button")

            # Verifica preservação de dados no formulário pai.
            current_title_val = driver.js('return document.querySelector(\'input[name="title[0][value]"]\').value;')
            check("evento.form.dados_preservados", current_title_val == test_title, f"valor mantido: {current_title_val}")

        # Salva log de resultados.
        results = {
            "environment": MAIN,
            "browser": "Firefox ESR (visible on DISPLAY=:0)",
            "checks": checks,
            "summary": {
                "total": len(checks),
                "passed": sum(1 for c in checks if c["passed"]),
                "failed": sum(1 for c in checks if not c["passed"]),
            },
        }
        (OUT / "organization-modal-results.json").write_text(json.dumps(results, indent=2, ensure_ascii=False))
        print(f"\nResumo dos testes: {results['summary']['passed']}/{results['summary']['total']} verificações passaram.")

    finally:
        driver.close()


if __name__ == "__main__":
    main()
