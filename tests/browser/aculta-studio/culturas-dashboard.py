#!/usr/bin/env python3
"""Check the Culturas dashboard presentation with the theme candidate CSS."""

import base64
import json
import os
from pathlib import Path
import sys
import time
from urllib.parse import quote

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
    "css/components/forms.css",
    "css/components/content.css",
    "css/components/studio.css",
)
CSS = "\n".join((ROOT / "web/themes/custom/aculta420" / path).read_text() for path in CSS_PATHS)
BOOTSTRAP = (ROOT / "web/libraries/bootstrap/dist/css/bootstrap.min.css").read_text()
RESULTS = []

HTML = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Culturas</title>
<style>*{box-sizing:border-box}body{margin:0;font:16px/1.5 sans-serif}.aculta-main{padding-block:1rem}</style>
</head><body class="path-culturas"><header class="aculta-container">Institution Bar e Domain Header ficam fora da área Studio.</header>
<main class="aculta-main"><div class="aculta-container"><section class="aculta-events-organizer-dashboard" data-portal-ui="event-organizer-dashboard">
<p>Crie e gerencie seus eventos, datas e locais. A edição usa as permissões de Eventos.</p>
<details class="culturas-guide"><summary>Como organizar eventos no ACULTA Studio Culturas</summary><ol><li>Crie evento</li><li>Adicione datas</li></ol></details>
<div><a href="#novo">Criar evento</a><a href="#local">Cadastrar local</a></div>
<div class="organizer-dashboard-filters"><form method="get"><div class="form-item"><label for="busca">Buscar por nome</label><input id="busca" name="busca" placeholder="Nome do evento"></div>
<div class="form-item"><label for="status">Situação</label><select id="status"><option>Todas as situações</option><option>Publicado</option></select></div>
<button class="button button--secondary" type="submit">Filtrar</button><a href="#limpar">Limpar filtros</a></form></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Evento</th><th>Próxima data</th><th>Local</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
<tr><td>Uma publicação cultural extensa com um título longo</td><td>30/10/2026 19:30</td><td>São Paulo (SP)</td><td>Publicado</td><td><a href="#editar">Gerenciar</a></td></tr>
<tr><td>Outro evento comunitário</td><td>Sem datas agendadas</td><td>Não definido</td><td>Rascunho</td><td><a href="#editar2">Gerenciar</a></td></tr></tbody></table></div>
<nav class="pager"><a href="#proxima">Próxima página</a></nav></section></div></main></body></html>"""


class Viewport:
    def __init__(self, driver):
        from websockets.sync.client import connect

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
            data = driver.js("""return (()=>{const q=s=>document.querySelector(s),r=e=>e.getBoundingClientRect();
const form=q('.organizer-dashboard-filters form'), field=q('#busca'), tableWrap=q('.table-responsive'), table=q('table'), root=q('.aculta-events-organizer-dashboard');
const actions=root?.querySelector(':scope > .culturas-guide + div'),links=Array.from(actions?.querySelectorAll('a')||[]);
return {width:innerWidth,docWidth:document.documentElement.scrollWidth,rootWidth:r(root).width,
formColumns:getComputedStyle(form).gridTemplateColumns.split(' ').length,
fieldBorders:[getComputedStyle(field).borderTopColor,getComputedStyle(field).borderRightColor,getComputedStyle(field).borderBottomColor,getComputedStyle(field).borderLeftColor],
tableWidth:r(tableWrap).width,tableOverflow:table.scrollWidth>tableWrap.clientWidth,
guideFocus:getComputedStyle(q('summary')).minBlockSize,buttonHeight:r(links[0]).height,actionCount:links.length,
primaryBackground:getComputedStyle(links[0]).backgroundColor,secondaryBorderWidth:getComputedStyle(links[1]).borderTopWidth,
shellPosition:getComputedStyle(q('header')).position,normalLinkDisplay:getComputedStyle(q('header')).display,
mainFont:getComputedStyle(root).fontFamily};})();""")
            record(f"{mode}/{width}px sem overflow horizontal no documento", data["docWidth"] <= width, data)
            expected_columns = 1 if width < 768 else 3
            record(f"{mode}/{width}px filtro progride mobile-first", data["formColumns"] == expected_columns, {"columns": data["formColumns"]})
            parsed = [tuple(int(channel) for channel in color.removeprefix("rgb(").removesuffix(")").split(", ")) for color in data["fieldBorders"]]
            record(f"{mode}/{width}px campo Fidelity mantém laterais claras e base escura", parsed[0] == parsed[1] == parsed[3] and parsed[2] != parsed[0], {"borders": data["fieldBorders"]})
            record(f"{mode}/{width}px tabela contida no dashboard", data["tableWidth"] <= data["rootWidth"] + 1, {"table": data["tableWidth"], "dashboard": data["rootWidth"]})
            record(f"{mode}/{width}px alvos interativos >=44px", data["buttonHeight"] >= 44 and float(data["guideFocus"].replace("px", "")) >= 44, {"action": data["buttonHeight"], "guide": data["guideFocus"]})
            record(f"{mode}/{width}px links do controller recebem tratamento tátil", data["actionCount"] == 2 and data["primaryBackground"] not in ("transparent", "rgba(0, 0, 0, 0)") and data["secondaryBorderWidth"] != "0px", {k: data[k] for k in ("actionCount", "primaryBackground", "secondaryBorderWidth")})
            record(f"{mode}/{width}px Institution/Domain shell intactos", data["shellPosition"] == "static" and data["normalLinkDisplay"] == "block", {"position": data["shellPosition"], "display": data["normalLinkDisplay"]})
            if width in (320, 1440):
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"culturas-dashboard-{mode}-{width}.png").write_bytes(base64.b64decode(shot))
        driver.js("document.querySelector('#busca').focus();")
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "culturas-keyboard", "actions": [
            {"type": "keyDown", "value": chr(0xE004)}, {"type": "keyUp", "value": chr(0xE004)}]}]})
        focus = driver.js("const e=document.activeElement,s=getComputedStyle(e);return {tag:e.tagName,outline:s.outlineStyle,width:s.outlineWidth};")
        record(f"{mode} foco visível com teclado", focus["tag"] == "SELECT" and focus["outline"] == "solid" and focus["width"] == "3px", focus)
        driver.req("DELETE", f"/session/{driver.session}/actions")
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in RESULTS if not result["passed"]]
print(f"\n{len(RESULTS) - len(failed)}/{len(RESULTS)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
