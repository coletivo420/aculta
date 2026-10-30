#!/usr/bin/env python3
"""Homologa a fronteira de domínio de Eventos e a gestão de Datas no Evento.

Verifica, autenticado como administrador no host de Eventos:
- a visão geral administrativa de Eventos não expõe "Adicionar data" nem a
  antiga rota global `/painel-administrativo/eventos/datas/adicionar`;
- o formulário de criação de Evento oferece Datas/Locais no próprio fluxo;
- a edição de Evento lista as Datas e abre a inclusão de Data em popup
  escopado ao Evento;
- a mesma rota editorial é negada (404) no domínio MAIN;
- a integração OpenStreetMap está disponível em Eventos e negada em MAIN.

Ambiente: servidor de desenvolvimento (*.aculta.toca.net.br), Firefox ESR
visível em DISPLAY=:0 via geckodriver. A URL de login de uso único é lida de
ACULTA_LOGIN_URL_FILE e nunca é impressa. Artefatos vão para $SUITE_OUT.
"""

import base64
import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

EVENTOS = os.environ.get("EVENTOS_BASE", "https://eventos.aculta.toca.net.br")
MAIN = os.environ.get("MAIN_BASE", "https://aculta.toca.net.br")
ACCOUNT = os.environ.get("ACCOUNT_BASE", "https://conta.aculta.toca.net.br")
LOGIN_FILE = Path(os.environ["ACULTA_LOGIN_URL_FILE"])
OLD_ROUTE = "/painel-administrativo/eventos/datas/adicionar"

checks = []


def check(name, ok, detail=""):
    checks.append({"name": name, "passed": bool(ok), "detail": detail})
    print(f"{'PASS' if ok else 'FAIL'} {name}: {detail}", flush=True)


def shot(driver, name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{name}.png").write_bytes(base64.b64decode(data))


def page_text(driver):
    return driver.js("return document.body ? document.body.innerText : '';") or ""


def is_denied(driver, expected_path):
    """Detecta negação por purpose: 404 vazio ou página de erro."""
    text = (driver.title() + " " + page_text(driver)).strip().lower()
    if text == "":
        return urlparse(driver.url()).path == expected_path
    return any(s in text for s in ["página não encontrada", "page not found", "404", "não foi encontrada", "acesso negado"])


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

login_url = LOGIN_FILE.read_text().strip()
if not login_url.startswith(ACCOUNT + "/"):
    raise SystemExit("A URL de login deve pertencer ao host de Conta (cookie compartilhado).")

driver = Driver()
try:
    driver.get(login_url)
    time.sleep(2)
    logged = "/entrar" not in driver.url() and "/recuperar-acesso/" not in driver.url()
    check("login.admin.conta", logged, driver.url())

    # 1. Visão geral administrativa de Eventos.
    driver.get(EVENTOS + "/painel-administrativo/eventos")
    time.sleep(1.2)
    text = page_text(driver)
    hrefs = driver.js("return Array.from(document.querySelectorAll('a')).map(a => a.getAttribute('href') || '');") or []
    check("overview.carrega", "gestão de eventos" in (driver.title() + " " + text).lower(), driver.title())
    check("overview.sem.adicionar.data", "adicionar data" not in text.lower(), "texto sem 'Adicionar data'")
    check("overview.sem.rota.antiga", not any(OLD_ROUTE in h for h in hrefs), "nenhum link para a rota antiga")
    check("overview.tem.cadastrar.evento", any("eventos/adicionar" in h for h in hrefs), "link de cadastro de Evento")
    check("overview.tem.locais", any("eventos/locais" in h for h in hrefs), "link de Locais")
    shot(driver, "01-overview-eventos")

    # 2. Formulário de criação de Evento com Datas/Locais no fluxo.
    driver.get(EVENTOS + "/painel-administrativo/eventos/adicionar")
    time.sleep(1.2)
    text = page_text(driver)
    check("criar.carrega", "datas e locais do evento" in text.lower(), driver.title())
    check("criar.adicionar.outra.data", bool(driver.exists('input[value*="Adicionar outra data"]')) or "adicionar outra data" in text.lower(), "botão de nova Data")
    check("criar.seletor.local", "selecionar ou cadastrar local" in text.lower(), "popup de Local")
    shot(driver, "02-criar-evento")

    # 3. Edição de um Evento existente com Datas no contexto.
    # A raiz do purpose eventos serve o catálogo; /eventos é caminho interno.
    driver.get(EVENTOS + "/")
    time.sleep(1.2)
    paths = driver.js("""
      return Array.from(document.querySelectorAll('a[href]'))
        .map(a => { try { return new URL(a.getAttribute('href'), location.origin); } catch (e) { return null; } })
        .filter(u => u && /^\\/eventos\\/[a-z0-9-]+$/.test(u.pathname))
        .map(u => u.pathname);
    """) or []
    found = False
    if paths:
        driver.get(EVENTOS + paths[0])
        time.sleep(1.2)
        edit_href = driver.js("""
          const a = Array.from(document.querySelectorAll('a[href]'))
            .find(a => /\\/painel-administrativo\\/eventos\\/\\d+\\/editar$/.test(a.getAttribute('href') || ''));
          return a ? a.getAttribute('href') : '';
        """) or ""
        check("evento.tem.acao.editar", bool(edit_href), edit_href)
        if edit_href:
            driver.get(EVENTOS + edit_href)
            time.sleep(1.2)
            text = page_text(driver)
            check("editar.lista.datas", "datas e locais deste evento" in text.lower(), "seção de Datas")
            add_href = driver.js("""
              const a = Array.from(document.querySelectorAll('a[href]'))
                .find(a => /\\/painel-administrativo\\/eventos\\/\\d+\\/datas\\/adicionar$/.test(a.getAttribute('href') || ''));
              return a ? a.getAttribute('href') : '';
            """) or ""
            check("editar.data.escopada", bool(add_href) and OLD_ROUTE not in add_href, add_href)
            check("editar.sem.rota.antiga", not (driver.js("return document.documentElement.innerHTML;") or "").count(OLD_ROUTE), "nenhuma referência à rota antiga")
            shot(driver, "03-editar-evento-datas")
            found = True
    check("evento.existente.para.editar", found, "evento público encontrado no catálogo")

    # 4. A mesma rota é negada no domínio MAIN.
    for path in ["/painel-administrativo/eventos/adicionar", "/integracoes/openstreetmap/configuracao"]:
        driver.get(MAIN + path)
        time.sleep(1.0)
        check("main.nega" + path.replace("/", "."), is_denied(driver, path), "titulo=" + driver.title())
        shot(driver, "04-main-nega" + path.replace("/", "-"))

    # 5. Integração OSM disponível em Eventos.
    driver.get(EVENTOS + "/integracoes/openstreetmap/configuracao")
    time.sleep(1.2)
    text = (driver.title() + " " + page_text(driver)).lower()
    check("eventos.osm.disponivel", "openstreetmap para eventos" in text, driver.title())
    shot(driver, "05-eventos-osm")

    # 6. A antiga rota global responde 404 em Eventos.
    driver.get(EVENTOS + OLD_ROUTE)
    time.sleep(1.0)
    check("rota.antiga.404", is_denied(driver, OLD_ROUTE), "titulo=" + driver.title())
    shot(driver, "06-rota-antiga-404")

finally:
    driver.close()

passed = sum(1 for c in checks if c["passed"])
total = len(checks)
(OUT / "resultado.json").write_text(json.dumps(checks, ensure_ascii=False, indent=2))
print(f"\n{passed}/{total} verificações PASS")
sys.exit(0 if passed == total else 1)
