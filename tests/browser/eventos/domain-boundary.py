#!/usr/bin/env python3
"""Homologa a regra de domínio de Eventos e a gestão de Datas no Evento.

Regra vigente: todo `/painel-administrativo/` responde somente no MAIN. O
purpose `eventos` serve as páginas públicas; o bloco somente leitura
"Próximos eventos" fica na sidebar do MAIN.

Verifica, autenticado como administrador pelo host de Conta:
- o admin de Eventos no MAIN carrega e não expõe "Adicionar data" nem a rota
  antiga global;
- a criação de Evento oferece Datas/Locais no próprio fluxo;
- a edição de Evento lista as Datas, abre a inclusão escopada e a ordem;
- a página pública em eventos aponta a ação de edição para o MAIN;
- o admin, o OSM e as Datas são negados (404) no host de Eventos;
- o OSM carrega no MAIN e o bloco "Próximos eventos" aparece no MAIN e não em
  eventos;
- a rota antiga global de Data responde 404.

Ambiente: servidor de desenvolvimento (*.aculta.toca.net.br), Firefox ESR
visível em DISPLAY=:0 via geckodriver. A URL de login de uso único é lida de
ACULTA_LOGIN_URL_FILE e nunca é impressa. Artefatos vão para $SUITE_OUT.
"""

import base64
import json
import os
import re
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
EVENT_ID = os.environ.get("EVENT_ID", "")
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

    # 1. Admin de Eventos no MAIN.
    driver.get(MAIN + "/painel-administrativo/eventos")
    time.sleep(1.2)
    text = page_text(driver)
    hrefs = driver.js("return Array.from(document.querySelectorAll('a')).map(a => a.getAttribute('href') || '');") or []
    check("main.overview.carrega", "gestão de eventos" in (driver.title() + " " + text).lower(), driver.title())
    check("main.overview.sem.adicionar.data", "adicionar data" not in text.lower(), "texto sem 'Adicionar data'")
    check("main.overview.sem.rota.antiga", not any(OLD_ROUTE in h for h in hrefs), "nenhum link para a rota antiga")
    check("main.overview.tem.cadastrar.evento", any("eventos/adicionar" in h for h in hrefs), "link de cadastro de Evento")
    check("main.overview.tem.locais", any("eventos/locais" in h for h in hrefs), "link de Locais")
    shot(driver, "01-main-overview-eventos")

    # 2. Criação de Evento no MAIN com Datas/Locais no fluxo.
    driver.get(MAIN + "/painel-administrativo/eventos/adicionar")
    time.sleep(1.2)
    text = page_text(driver)
    check("main.criar.carrega", "datas e locais do evento" in text.lower(), driver.title())
    check("main.criar.adicionar.outra.data", bool(driver.exists('input[value*="Adicionar outra data"]')) or "adicionar outra data" in text.lower(), "botão de nova Data")
    check("main.criar.seletor.local", "selecionar ou cadastrar local" in text.lower(), "popup de Local")
    shot(driver, "02-main-criar-evento")

    # 3. Página pública em eventos sem ações editoriais; admin no MAIN.
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
        admin_links = driver.js("""
          return Array.from(document.querySelectorAll('a[href]'))
            .map(a => a.getAttribute('href') || '')
            .filter(h => h.includes('/painel-administrativo'));
        """) or []
        check("eventos.evento.sem.acoes.editoriais", admin_links == [], f"links admin: {admin_links}")
        found = True
    check("eventos.evento.existente", found, "evento público encontrado no catálogo")

    if EVENT_ID:
        driver.get(MAIN + f"/painel-administrativo/eventos/{EVENT_ID}/editar")
        time.sleep(1.2)
        text = page_text(driver)
        check("main.editar.lista.datas", "datas e locais deste evento" in text.lower(), "seção de Datas")
        add_href = driver.js("""
          const a = Array.from(document.querySelectorAll('a[href]'))
            .find(a => /\\/painel-administrativo\\/eventos\\/\\d+\\/datas\\/adicionar$/.test(a.getAttribute('href') || ''));
          return a ? a.getAttribute('href') : '';
        """) or ""
        check("main.editar.data.escopada", bool(add_href) and OLD_ROUTE not in add_href, add_href)
        check("main.editar.sem.rota.antiga", not (driver.js("return document.documentElement.innerHTML;") or "").count(OLD_ROUTE), "nenhuma referência à rota antiga")
        shot(driver, "03-main-editar-evento-datas")
        driver.get(MAIN + f"/painel-administrativo/eventos/{EVENT_ID}/datas/ordem")
        time.sleep(1.0)
        check("main.ordem.datas", "salvar ordem" in page_text(driver).lower() or bool(driver.exists('input[value*="Salvar ordem"]')), driver.title())
        shot(driver, "04-main-ordem-datas")
    else:
        check("main.editar.evento.informado", False, "Defina EVENT_ID para homologar a edição e a ordem")

    # 4. O editorial e o OSM são negados no host de Eventos.
    for path in ["/painel-administrativo/eventos", "/painel-administrativo/eventos/adicionar", "/integracoes/openstreetmap/configuracao", OLD_ROUTE]:
        driver.get(EVENTOS + path)
        time.sleep(1.0)
        check("eventos.nega" + path.replace("/", "."), is_denied(driver, path), "titulo=" + driver.title())
        shot(driver, "05-eventos-nega" + path.replace("/", "-"))

    # 5. OSM disponível no MAIN.
    driver.get(MAIN + "/integracoes/openstreetmap/configuracao")
    time.sleep(1.2)
    text = (driver.title() + " " + page_text(driver)).lower()
    check("main.osm.disponivel", "openstreetmap para eventos" in text, driver.title())
    shot(driver, "06-main-osm")

    # 6. Bloco somente leitura no MAIN e ausente em eventos.
    driver.get(MAIN + "/")
    time.sleep(1.2)
    check("main.bloco.proximos.eventos", "próximos eventos" in page_text(driver).lower(), "bloco na sidebar")
    shot(driver, "07-main-bloco-eventos")
    driver.get(EVENTOS + "/")
    time.sleep(1.2)
    check("eventos.sem.bloco.eventos", "próximos eventos" not in page_text(driver).lower(), "bloco ausente")

    # 7. Rota antiga global também no MAIN.
    driver.get(MAIN + OLD_ROUTE)
    time.sleep(1.0)
    check("main.rota.antiga.404", is_denied(driver, OLD_ROUTE), "titulo=" + driver.title())
    shot(driver, "08-main-rota-antiga-404")

finally:
    driver.close()

passed = sum(1 for c in checks if c["passed"])
total = len(checks)
(OUT / "resultado.json").write_text(json.dumps(checks, ensure_ascii=False, indent=2))
print(f"\n{passed}/{total} verificações PASS")
sys.exit(0 if passed == total else 1)
