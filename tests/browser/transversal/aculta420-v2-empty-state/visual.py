#!/usr/bin/env python3
"""Validate the ACULTA420 V2 empty-state SDC in visible Firefox ESR."""

import base64
import json
import os
import subprocess
import sys
import threading
import time
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if subprocess.run(["which", "xdotool"], capture_output=True).returncode:
    raise SystemExit("xdotool é necessário para validar zoom real do Firefox.")

sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

THEME = ROOT / "web/themes/custom/aculta420_v2"
COMPONENT = THEME / "components/empty-state"
TOKENS = (THEME / "src/styles/tokens.css").read_text()
CSS = (COMPONENT / "empty-state.css").read_text()
html = f'''<!doctype html><html lang="pt-BR" data-theme="light">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>{TOKENS}\n{CSS}\nmain{{max-width:64rem;margin:auto;padding:1rem}}</style>
<main><h1>Meus cursos</h1><section class="empty-state">
<div class="empty-state__message"><p>Você ainda não está matriculado em nenhum curso. Quando se matricular, seus cursos aparecem aqui.</p></div>
<div class="empty-state__actions"><a href="/catalogo">Explorar o catálogo de cursos</a></div></section>
<section class="empty-state empty-state--compact"><div class="empty-state__message"><p>Este curso ainda não tem aulas publicadas.</p></div></section></main></html>'''
(OUT / "fixture.html").write_text(html)


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(OUT), **kwargs)

    def log_message(self, *_args):
        pass


server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
driver = Driver(bidi=True)
records = []


def set_viewport(width):
    node = r'''const ws=new WebSocket(process.argv[1]);ws.onopen=()=>ws.send(JSON.stringify({id:1,method:'browsingContext.getTree',params:{}}));ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id===1)ws.send(JSON.stringify({id:2,method:'browsingContext.setViewport',params:{context:m.result.contexts[0].context,viewport:{width:Number(process.argv[2]),height:900}}}));else if(m.id===2){if(m.error){console.error(m.error.message);process.exitCode=1;}ws.close();}};'''
    subprocess.run(["node", "-e", node, driver.websocket_url, str(width)], check=True)


def inspect():
    return driver.js("""return (()=>{const d=document.documentElement;
      const states=[...document.querySelectorAll('.empty-state')];
      const action=states[0]?.querySelector('a');
      return {width:innerWidth,documentWidth:d.scrollWidth,stateCount:states.length,
        messageCount:states.filter(x=>x.querySelector('.empty-state__message p')?.textContent.trim()).length,
        actionHref:action?.getAttribute('href'),actionText:action?.textContent.trim(),
        compact:states[1]?.classList.contains('empty-state--compact'),
        background:getComputedStyle(states[0]).backgroundColor,
        borderRadius:getComputedStyle(states[0]).borderRadius};})();""")


def focus_link():
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "empty-state-tab", "actions": [
            {"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"},
        ],
    }]})
    return driver.js("""const e=document.activeElement,s=getComputedStyle(e);
      return {href:e.getAttribute('href'),outlineStyle:s.outlineStyle,outlineWidth:s.outlineWidth};""")


def zoom_in():
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()
    subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+plus"], check=True)


def reset_zoom():
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()
    subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+0"], check=True)


try:
    reset_zoom()
    driver.get(f"http://127.0.0.1:{server.server_port}/fixture.html")
    time.sleep(0.3)
    for mode in ("light", "dark"):
        driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
        for width in (320, 390, 768, 959, 960, 1440):
            set_viewport(width)
            metrics = inspect()
            metrics.update(mode=mode, passed=(metrics["documentWidth"] <= width
                and metrics["stateCount"] == 2 and metrics["messageCount"] == 2
                and metrics["actionHref"] == "/catalogo"
                and metrics["actionText"] == "Explorar o catálogo de cursos"
                and metrics["compact"]))
            records.append(metrics)
            print(f"{'PASS' if metrics['passed'] else 'FAIL'} {mode} {width}px", flush=True)
            (OUT / f"{mode}-{width}.png").write_bytes(base64.b64decode(
                driver.req("GET", f"/session/{driver.session}/screenshot")
            ))

    set_viewport(320)
    driver.get(f"http://127.0.0.1:{server.server_port}/fixture.html")
    time.sleep(0.2)
    focus = focus_link()
    focus["passed"] = focus["href"] == "/catalogo" and focus["outlineStyle"] != "none" and float(focus["outlineWidth"].replace("px", "")) >= 3
    print(f"{'PASS' if focus['passed'] else 'FAIL'} foco visível por Tab", focus, flush=True)
    zoom_records = []
    set_viewport(640)
    driver.get(f"http://127.0.0.1:{server.server_port}/fixture.html")
    for _ in range(24):
        zoom = driver.js("return {width:innerWidth,dpr:devicePixelRatio,document:document.documentElement.scrollWidth}")
        if zoom["dpr"] >= 1.92:
            break
        zoom_in(); time.sleep(0.12)
    zoom = driver.js("""return {width:innerWidth,dpr:devicePixelRatio,document:document.documentElement.scrollWidth,
      state:document.querySelector('.empty-state').getBoundingClientRect().toJSON()}""")
    zoom["passed"] = zoom["dpr"] >= 1.92 and zoom["document"] <= zoom["width"]
    zoom_records.append(zoom)
    print(f"{'PASS' if zoom['passed'] else 'FAIL'} zoom real 200%", zoom, flush=True)
    (OUT / "zoom-200.png").write_bytes(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
    result = {"matrix": records, "keyboard": focus, "zoom": zoom_records}
    (OUT / "results.json").write_text(json.dumps(result, ensure_ascii=False, indent=2))
    if not all(item["passed"] for item in records) or not focus["passed"] or not zoom["passed"]:
        raise SystemExit("Falha na validação visual do empty-state.")
finally:
    reset_zoom()
    driver.close()
    server.shutdown()
