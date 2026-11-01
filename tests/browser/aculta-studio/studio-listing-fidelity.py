#!/usr/bin/env python3
"""Validate Next-inspired responsive tables used by Studio listings."""

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
CSS_PATHS = (
    "css/tokens.css",
    "css/base.css",
    "css/components/buttons.css",
    "css/components/content.css",
    "css/components/studio.css",
)
CSS = "\n".join((ROOT / "web/themes/custom/aculta420" / path).read_text() for path in CSS_PATHS)
BOOTSTRAP = (ROOT / "web/libraries/bootstrap/dist/css/bootstrap.min.css").read_text()
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

HTML = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Listagem Studio</title>
<style>*{box-sizing:border-box}body{margin:0;font:16px/1.5 sans-serif}.studio__main{min-width:0;padding:1rem}
.studio__listing-wrap{width:100%;max-width:100%}</style></head>
<body class="path-marley"><header class="aculta-container">Institution Bar e Domain Header ficam fora da área Studio.</header>
<div class="studio"><main class="studio__main"><h1>Publicações</h1><div class="studio__listing-wrap" aria-label="Tabela de publicações"><div class="table-responsive">
<table class="table studio__listing"><thead><tr><th>Título</th><th>Situação</th><th>Autor</th><th>Atualizado em</th><th>Ações</th><th>Revisão</th></tr></thead><tbody>
<tr><td><a href="#publicacao">Uma publicação editorial com um título suficientemente longo para testar o reflow</a></td><td>Rascunho</td><td>Autora de teste</td><td>31/10/2026 19:30</td><td><a href="#editar">Editar</a></td><td>Não enviada</td></tr>
<tr><td><a href="#publicacao2">Outra publicação</a></td><td>Publicado</td><td>Outra autora</td><td>30/10/2026 08:15</td><td><a href="#editar2">Editar</a></td><td>Revisada</td></tr>
</tbody></table></div></div><div id="empty-listing-wrap" class="studio__listing-wrap" aria-label="Publicações arquivadas"><div class="table-responsive">
<table id="empty-listing" class="table studio__listing"><thead><tr><th>Título</th><th>Situação</th><th>Autor</th><th>Atualizado em</th><th>Ações</th><th>Revisão</th></tr></thead>
<tbody><tr><td colspan="6">Nenhum item nesta aba.</td></tr></tbody></table></div></div></main></div></body></html>"""


def record(name, passed, detail=None):
    RESULTS.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(HTML))
    driver.js("const b=document.createElement('style');b.textContent=arguments[0];document.head.append(b);const s=document.createElement('style');s.textContent=arguments[1];document.head.append(s);", [BOOTSTRAP, CSS])
    time.sleep(0.2)
    for mode in ("light", "dark"):
        driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0])", [mode])
        for width in WIDTHS:
            viewport.set(width)
            time.sleep(0.08)
            data = driver.js("""const wrap=document.querySelector('.studio__listing-wrap'),table=document.querySelector('.studio__listing');
const scroller=table.parentElement;
const first=table.querySelector('th'),last=table.querySelector('th:last-child'),head=table.querySelector('thead th');
const emptyWrap=document.querySelector('#empty-listing-wrap'),emptyTable=document.querySelector('#empty-listing'),emptyScroller=emptyTable.parentElement,emptyCell=emptyTable.querySelector('tbody td');
const r=e=>e.getBoundingClientRect(),s=e=>getComputedStyle(e);
const token=s(document.querySelector('.studio')).getPropertyValue('--aculta-studio-fidelity-input-border-bottom').trim();
const rgb=token.match(/[a-f0-9]{2}/gi).map(value=>parseInt(value,16));
scroller.scrollLeft=99999;const localScrollMax=scroller.scrollLeft;scroller.scrollLeft=0;
emptyScroller.scrollLeft=99999;const emptyLocalScrollMax=emptyScroller.scrollLeft;emptyScroller.scrollLeft=0;
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,wrapWidth:r(wrap).width,wrapScroll:wrap.scrollWidth,wrapClient:wrap.clientWidth,
scrollerWidth:r(scroller).width,scrollerScroll:scroller.scrollWidth,scrollerClient:scroller.clientWidth,localScrollMax,
tableWidth:r(table).width,tableDisplay:s(table).display,tableLayout:s(table).tableLayout,firstPadding:s(first).paddingInlineStart,lastPadding:s(last).paddingInlineEnd,
cellPadding:s(first).paddingBlockStart,borderCollapse:s(table).borderCollapse,headerWeight:s(head).fontWeight,headerBorder:s(head).borderBottomColor,
headerBorderToken:`rgb(${rgb.join(', ')})`,bodyColor:s(table).color,
emptyText:emptyCell.textContent.trim(),emptyCellWidth:r(emptyCell).width,emptyCellHeight:r(emptyCell).height,
emptyCellPadding:s(emptyCell).paddingBlockStart,emptyWrapWidth:r(emptyWrap).width,
emptyWrapScroll:emptyWrap.scrollWidth,emptyTableWidth:r(emptyTable).width,
emptyScrollerWidth:r(emptyScroller).width,emptyScrollerScroll:emptyScroller.scrollWidth,
emptyScrollerClient:emptyScroller.clientWidth,emptyLocalScrollMax};""")
            record(f"{mode}/{width}px sem overflow no documento", data["documentWidth"] <= width, {"documentWidth": data["documentWidth"]})
            record(f"{mode}/{width}px tabela rola no contêiner interno", data["wrapWidth"] <= width and data["tableWidth"] <= data["scrollerScroll"] + 1 and ((data["scrollerScroll"] > data["scrollerClient"]) == (data["localScrollMax"] > 0)), data)
            record(f"{mode}/{width}px ritmo de tabela OpenCulturas", data["tableDisplay"] == "table" and data["borderCollapse"] == "collapse" and data["cellPadding"] == "8px" and data["firstPadding"] == "0px" and data["lastPadding"] == "0px" and int(data["headerWeight"]) >= 600 and data["headerBorder"] == data["headerBorderToken"], data)
            record(f"{mode}/{width}px estado vazio contido e legível", data["emptyText"] == "Nenhum item nesta aba." and data["emptyCellHeight"] > 0 and data["emptyCellWidth"] <= data["emptyScrollerScroll"] + 1 and data["emptyTableWidth"] <= data["emptyScrollerScroll"] + 1 and data["emptyWrapWidth"] <= width and data["documentWidth"] <= width and ((data["emptyScrollerScroll"] > data["emptyScrollerClient"]) == (data["emptyLocalScrollMax"] > 0)), data)
            if width in (320, 1440):
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"listing-{mode}-{width}.png").write_bytes(base64.b64decode(shot))
                driver.js("document.querySelector('#empty-listing-wrap').scrollIntoView({block:'center',inline:'nearest'});")
                time.sleep(0.05)
                empty_shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"listing-empty-{mode}-{width}.png").write_bytes(base64.b64decode(empty_shot))
                driver.js("window.scrollTo(0,0)")
        driver.js("document.querySelector('.studio__listing-wrap a').focus();")
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "listing-keyboard", "actions": [
            {"type": "keyDown", "value": chr(0xE004)}, {"type": "keyUp", "value": chr(0xE004)}]}]})
        focus = driver.js("const e=document.activeElement,s=getComputedStyle(e);return {href:e.getAttribute('href'),outline:s.outlineStyle,width:s.outlineWidth};")
        record(f"{mode} foco de teclado segue visível na listagem", focus["href"] == "#editar" and focus["outline"] == "solid" and focus["width"] == "3px", focus)
        driver.req("DELETE", f"/session/{driver.session}/actions")
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in RESULTS if not result["passed"]]
print(f"\n{len(RESULTS) - len(failed)}/{len(RESULTS)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
