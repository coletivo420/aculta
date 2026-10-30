#!/usr/bin/env python3
"""Check mobile-first presentation of Events organization forms and cards."""

import base64
import json
import os
from pathlib import Path
import sys
import time
from urllib.parse import quote

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT para armazenar evidências fora do Git.")
OUT = Path(OUT_VALUE).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

WIDTHS = (320, 360, 390, 599, 600, 768, 1024, 1280, 1440)
MODES = ("light", "dark")
RESULTS = []


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 1000},
        })

    def close(self):
        self.socket.close()


def record(name, passed, detail=None):
    RESULTS.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


CSS_PATHS = (
    "css/tokens.css",
    "css/base.css",
    "css/components/buttons.css",
    "css/components/forms.css",
    "css/components/content.css",
    "css/components/event-organization.css",
)
CSS = "\n".join((ROOT / "web/themes/custom/aculta420" / path).read_text() for path in CSS_PATHS)
BOOTSTRAP_CSS = (ROOT / "web/libraries/bootstrap/dist/css/bootstrap.min.css").read_text()

HTML = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>:root{--bs-border-radius:.375rem;--aculta-page-gutter:1rem}
*{box-sizing:border-box}body{margin:0;padding:1rem;font:16px/1.5 sans-serif}
.form-item{min-width:0;margin:0}.form-actions{display:flex;flex-wrap:wrap;gap:.5rem}
</style></head><body><main class="aculta-content">
<div id="aculta-organization-modal-form-wrapper">
  <p class="lead">Selecionar organização principal</p>
  <div class="search-box">
    <div class="form-item"><label for="search">Buscar por nome ou cidade</label><input id="search" type="search" aria-label="Buscar organização"></div>
    <button class="button button--secondary" type="button">Buscar</button>
    <button class="button button--primary" type="button">Cadastrar organização</button>
  </div>
  <div class="aculta-event-organization-location-fields">
    <div class="form-item"><label for="city">Cidade</label><input id="city" type="text"></div>
    <div class="form-item"><label for="uf">UF</label><input id="uf" type="text"></div>
  </div>
  <div class="org-modal-results-table"><table class="table"><thead><tr><th>Organização</th><th>Localidade</th><th>Situação</th><th>Ação</th></tr></thead>
    <tbody><tr><td>Organização Cultural com nome extenso para testar reflow</td><td>Porto Alegre, RS</td><td>Reivindicada</td><td><button class="button button--small">Selecionar</button></td></tr>
    <tr><td>Outra organização</td><td>São Paulo, SP</td><td>Não reivindicada</td><td><button class="button button--small">Selecionar</button></td></tr></tbody></table></div>
</div>
<section class="aculta-event-org-widget"><article class="event-org-card"><h2 class="event-org-card__title">Coletivo de Cultura Popular e Produção Comunitária</h2><span class="badge badge--success event-org-card__badge">Reivindicada</span><p class="event-org-card__locality">Localidade: São Paulo, SP</p><button class="button button--secondary">Trocar organização</button></article>
<div class="event-org-empty">Nenhuma organização coorganizadora vinculada.</div>
<div class="coorganizers-list"><article class="coorganizer-item"><strong class="coorganizer-item__title">Coletivo A com nome longo</strong><span class="badge badge--warning">Não reivindicada</span><button class="button button--danger">Remover</button></article>
<article class="coorganizer-item"><strong class="coorganizer-item__title">Coletivo B</strong><span class="badge badge--success">Reivindicada</span><button class="button button--danger">Remover</button></article></div></section>
<section class="aculta-events-organization-admin-overview"><form class="form--inline"><div class="aculta-event-organization-admin-filter-fields">
<div class="form-item"><label for="filter-name">Nome</label><input id="filter-name"></div><div class="form-item"><label for="filter-claim">Reivindicação</label><select id="filter-claim"><option>Todas</option></select></div><div class="form-item"><label for="filter-review">Acessos</label><select id="filter-review"><option>Todos</option></select></div></div></form></section>
<section class="aculta-organization-profile"><header class="organization-header"><h1 class="organization-title">Organização cultural com um título longo para validar quebra responsiva</h1><p class="organization-location">📍 Campinas / SP</p></header>
<section class="organization-body"><div class="organization-description">Apresentação com várias linhas. Texto seguro e responsivo.</div></section>
<section class="organization-links"><h2>Canais oficiais</h2><p>https://exemplo.org/um-caminho-extenso-sem-espacos-que-deve-quebrar-para-caber-na-tela</p></section>
<section class="organization-events"><h2>Eventos organizados</h2><ul><li><a class="organization-event-item" href="#event">Evento cultural de teste</a></li></ul></section></section>
<section class="aculta-related-content"><h2>Conteúdo relacionado</h2><ul><li class="aculta-related-content__item"><a class="aculta-related-content__link" href="#related">Publicação relacionada</a><span class="aculta-related-content__badge">Revista</span><p class="aculta-related-content__summary">Resumo da publicação relacionada.</p></li></ul></section>
</main></body></html>"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(HTML))
    driver.js("const b=document.createElement('style');b.textContent=arguments[0];document.head.append(b);const s=document.createElement('style');s.textContent=arguments[1];document.head.append(s);", [BOOTSTRAP_CSS, CSS])
    time.sleep(0.2)
    measure = """return (() => {
      const tracks=e=>e?getComputedStyle(e).gridTemplateColumns.split(' ').filter(v=>v.endsWith('px')).length:0;
      const table=document.querySelector('.org-modal-results-table');
      const card=document.querySelector('.event-org-card');
      return {width:innerWidth, docWidth:document.documentElement.scrollWidth,
        hasSearch:!!document.querySelector('.search-box'),hasLocation:!!document.querySelector('.aculta-event-organization-location-fields'),
        hasCoorganizers:!!document.querySelector('.coorganizers-list'),hasAdmin:!!document.querySelector('.aculta-events-organization-admin-overview .form--inline'),
        searchTracks:tracks(document.querySelector('.search-box')),
        locationTracks:tracks(document.querySelector('.aculta-event-organization-location-fields')),
        coorganizerTracks:tracks(document.querySelector('.coorganizers-list')),
        adminTracks:tracks(document.querySelector('.aculta-event-organization-admin-overview .form--inline')),
        tableWidth:table?.clientWidth??0,tableScroll:table?.scrollWidth??0,
        cardBackground:card?getComputedStyle(card).backgroundColor:'missing',
        searchButtonHeight:document.querySelector('.search-box .button').getBoundingClientRect().height,
        page:document.querySelector('main').getBoundingClientRect().width};
    })();"""

    for mode in MODES:
        driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0])", [mode])
        time.sleep(0.05)
        for width in WIDTHS:
            viewport.set(width)
            time.sleep(0.08)
            data = driver.js(measure)
            record(f"{mode}/{width}px: sem overflow da página", data["docWidth"] <= width, data)
            expected_search = 1 if width < 576 else 2 if width < 768 else 3
            expected_location = 1 if width < 576 else 2
            expected_coorg = 1 if width < 768 else 2 if width < 992 else 3
            record(f"{mode}/{width}px: formulários e lista progridem por largura",
                   data["hasSearch"] and data["hasLocation"] and data["hasCoorganizers"] and data["hasAdmin"]
                   and data["searchTracks"] == expected_search
                   and data["locationTracks"] == expected_location
                   and data["coorganizerTracks"] == expected_coorg,
                   {k: data[k] for k in ("searchTracks", "locationTracks", "coorganizerTracks")})
            record(f"{mode}/{width}px: tabela rola dentro do painel",
                   data["tableWidth"] <= width
                   and ((width < 576 and data["tableScroll"] > data["tableWidth"])
                        or (width >= 576 and data["tableScroll"] == data["tableWidth"])),
                   {k: data[k] for k in ("tableWidth", "tableScroll")})
            record(f"{mode}/{width}px: controles mantêm alvo de 44px e superfície visível",
                   data["searchButtonHeight"] >= 44 and data["cardBackground"] not in ("transparent", "rgba(0, 0, 0, 0)"),
                   {k: data[k] for k in ("searchButtonHeight", "cardBackground")})
            if width in (320, 1440):
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"organization-layout-{mode}-{width}.png").write_bytes(base64.b64decode(shot))

        driver.js("document.querySelector('#search').focus();")
        tab = chr(0xE004)
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "org-keyboard", "actions": [
            {"type": "keyDown", "value": tab}, {"type": "keyUp", "value": tab}]}]})
        focus = driver.js("const e=document.activeElement,s=getComputedStyle(e);return {tag:e.tagName,id:e.id,outline:s.outlineStyle,width:s.outlineWidth};")
        record(f"{mode}: foco de teclado visível", focus["tag"] == "BUTTON" and focus["outline"] == "solid" and focus["width"] == "3px", focus)
        driver.req("DELETE", f"/session/{driver.session}/actions")
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in RESULTS if not result["passed"]]
print(f"\n{len(RESULTS) - len(failed)}/{len(RESULTS)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
