#!/usr/bin/env python3
"""Validate the ACULTA420 V2 data-table SDC in visible Firefox ESR."""

import base64
import json
import os
import sys
import threading
import time
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

THEME = ROOT / "web/themes/custom/aculta420_v2"
COMPONENT = THEME / "components/data-table"
TOKENS = (THEME / "src/styles/tokens.css").read_text()
CSS = (COMPONENT / "data-table.css").read_text()
JS = (COMPONENT / "data-table.js").read_text()
rows = "".join(
    f'<tr><th scope="row">Registro {index}</th>'
    f'<td data-label="Nome">Valor longo do registro {index}</td></tr>'
    for index in range(1, 7)
)
table = (
    '<table class="data-table__table"><caption>Registros</caption>'
    '<thead><tr><th scope="col">Registro</th><th scope="col">Nome</th></tr></thead>'
    f"<tbody>{rows}</tbody></table>"
)
fixture = f'''<!doctype html><html lang="pt-BR" data-theme="light">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>{TOKENS}\n{CSS}\n.data-table--scroll .data-table__table{{min-inline-size:100rem}}
@media(width >= 40rem){{.data-table--cards .data-table__table{{min-inline-size:100rem}}}}</style>
<main style="max-width:70rem;margin:auto;padding:1rem"><h1>Tabela de dados</h1>
<div class="data-table data-table--cards" data-aculta-data-table data-mobile-variant="cards">
<div class="data-table__viewport" data-aculta-data-table-viewport data-scroll-label="Tabela larga de dados"
role="region" aria-label="Tabela larga de dados" tabindex="0">{table}</div></div>
<div style="height:1rem"></div>
<div class="data-table data-table--scroll" data-aculta-data-table data-mobile-variant="scroll">
<div class="data-table__viewport" data-aculta-data-table-viewport data-scroll-label="Tabela larga de dados"
role="region" aria-label="Tabela larga de dados" tabindex="0">{table}</div></div></main>
<script>window.Drupal={{behaviors:{{}}}};window.once=(id,sel,ctx)=>Array.from((ctx||document).querySelectorAll(sel));
window.once.remove=(id,sel,ctx)=>Array.from((ctx||document).querySelectorAll(sel));</script>
<script>{JS}</script><script>Drupal.behaviors.acultaDataTable.attach(document);</script></html>'''
(OUT / "fixture.html").write_text(fixture)
(OUT / "fixture-no-js.html").write_text(fixture.split("<script>")[0] + "</html>")


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(OUT), **kwargs)

    def log_message(self, *_args):
        pass


server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
driver = Driver(bidi=True)
socket = connect(driver.websocket_url)
sequence = 0
results = []


def bidi(method, params):
    global sequence
    sequence += 1
    socket.send(json.dumps({"id": sequence, "method": method, "params": params}))
    while True:
        response = json.loads(socket.recv())
        if response.get("id") == sequence:
            if "error" in response:
                raise RuntimeError(response["error"])
            return response.get("result", {})


try:
    driver.get(f"http://127.0.0.1:{server.server_port}/fixture.html")
    time.sleep(0.3)
    context = bidi("browsingContext.getTree", {})["contexts"][0]["context"]
    for mode in ("light", "dark"):
        driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
        for width in (320, 390, 768, 959, 960, 1440):
            bidi("browsingContext.setViewport", {
                "context": context,
                "viewport": {"width": width, "height": 1000},
            })
            time.sleep(0.2)
            metrics = driver.js("""return (() => {
              const card=document.querySelector('.data-table--cards [data-aculta-data-table-viewport]');
              const scroll=document.querySelector('.data-table--scroll [data-aculta-data-table-viewport]');
              return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
                cardDisplay:getComputedStyle(document.querySelector('.data-table--cards table')).display,
                cardRole:card.getAttribute('role'),cardTabIndex:card.getAttribute('tabindex'),
                scrollRole:scroll.getAttribute('role'),scrollLabel:scroll.getAttribute('aria-label'),
                scrollTabIndex:scroll.getAttribute('tabindex'),
                scrollWidth:scroll.scrollWidth,clientWidth:scroll.clientWidth};
            })();""")
            mobile = width < 640
            passed = (
                metrics["documentWidth"] <= width
                and (metrics["cardRole"] is None and metrics["cardTabIndex"] is None if mobile
                     else metrics["cardRole"] == "region" and metrics["cardTabIndex"] == "0")
                and metrics["scrollRole"] == "region"
                and metrics["scrollLabel"] == "Tabela larga de dados"
                and metrics["scrollTabIndex"] == "0"
                and metrics["scrollWidth"] > metrics["clientWidth"]
                and metrics["cardDisplay"] == ("block" if mobile else "table")
            )
            results.append({"mode": mode, **metrics, "passed": passed})
            print(f"{'PASS' if passed else 'FAIL'} {mode} {width}px", flush=True)
            if not passed:
                print(json.dumps(metrics, ensure_ascii=False), flush=True)
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))

    driver.js("document.documentElement.setAttribute('data-theme', 'light')")
    bidi("browsingContext.setViewport", {
        "context": context, "viewport": {"width": 320, "height": 1000},
    })
    driver.get(f"http://127.0.0.1:{server.server_port}/fixture-no-js.html")
    time.sleep(0.2)
    no_js = driver.js("""return {documentWidth:document.documentElement.scrollWidth,
      cardDisplay:getComputedStyle(document.querySelector('.data-table--cards table')).display,
      cardLabel:document.querySelector('.data-table--cards td').getAttribute('data-label'),
      scrollRole:document.querySelector('.data-table--scroll [data-aculta-data-table-viewport]').getAttribute('role'),
      scrollTabIndex:document.querySelector('.data-table--scroll [data-aculta-data-table-viewport]').getAttribute('tabindex')};""")
    no_js["passed"] = no_js["documentWidth"] <= 320 and no_js["cardDisplay"] == "block" and no_js["cardLabel"] == "Nome" and no_js["scrollRole"] == "region" and no_js["scrollTabIndex"] == "0"
    print(f"{'PASS' if no_js['passed'] else 'FAIL'} JavaScript desativado em 320px", flush=True)
    (OUT / "no-js-320.png").write_bytes(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
    (OUT / "results.json").write_text(json.dumps({"matrix": results, "no_js": no_js}, ensure_ascii=False, indent=2))
    if not all(item["passed"] for item in results) or not no_js["passed"]:
        raise SystemExit("Falha na validação visual do data-table.")
finally:
    socket.close()
    driver.close()
    server.shutdown()
