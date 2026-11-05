#!/usr/bin/env python3
"""Exercise draft card accent tokens in visible Firefox ESR."""

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

sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

THEME = ROOT / "web/themes/custom/aculta420_v2"
TOKENS = (THEME / "src/styles/tokens.css").read_text()
BASE = (THEME / "src/styles/base.css").read_text()
CSS = (THEME / "components/card-cultural/card-cultural.css").read_text()
cards = "".join(
    f'<article class="card-cultural card-cultural--default card-cultural--accent-{color}">'
    f'<div class="card-cultural__body"><h2 class="card-cultural__title">'
    f'<a href="/{color}">{label}</a></h2></div></article>'
    for color, label in (("green", "Evento"), ("yellow", "Mapa"), ("red", "Local"))
)
html = f'''<!doctype html><html lang="pt-BR" data-theme="light">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>{TOKENS}\n{BASE}\n{CSS}\nmain{{display:grid;gap:1rem;max-width:60rem;margin:auto;padding:1rem}}</style>
<main><h1>Stories DRAFT de acento</h1>{cards}</main></html>'''
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
    return driver.js("""return (()=>{const colors=['green','yellow','red'];
      const cards=[...document.querySelectorAll('.card-cultural')].map((card,index)=>({
        accent:colors[index],width:parseFloat(getComputedStyle(card).borderInlineStartWidth),
        color:getComputedStyle(card).borderInlineStartColor,
        expected:(()=>{const probe=document.createElement('span');probe.style.color='var(--accent-'+colors[index]+'-stripe)';document.body.append(probe);const value=getComputedStyle(probe).color;probe.remove();return value;})()}));
      return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,cards};})();""")


def zoom_key(key):
    window = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()[-1]
    subprocess.run(["xdotool", "windowactivate", "--sync", window], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", key], check=True)


try:
    zoom_key("ctrl+0")
    driver.get(f"http://127.0.0.1:{server.server_port}/fixture.html")
    time.sleep(0.3)
    for mode in ("light", "dark"):
        driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
        for width in (320, 390, 768, 959, 960, 1440):
            set_viewport(width)
            data = inspect()
            data["mode"] = mode
            data["passed"] = data["documentWidth"] <= width and all(
                card["width"] >= 6 and card["color"] == card["expected"]
                for card in data["cards"]
            )
            records.append(data)
            print(f"{'PASS' if data['passed'] else 'FAIL'} {mode} {width}px", flush=True)
            (OUT / f"{mode}-{width}.png").write_bytes(base64.b64decode(
                driver.req("GET", f"/session/{driver.session}/screenshot")
            ))

    set_viewport(320)
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "accent-tab", "actions": [
            {"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"},
        ],
    }]})
    focus = driver.js("""const e=document.activeElement,s=getComputedStyle(e);
      return {href:e.getAttribute('href'),outline:s.outlineStyle,width:s.outlineWidth};""")
    focus["passed"] = focus["href"] == "/green" and focus["outline"] != "none" and float(focus["width"].replace("px", "")) >= 3
    print(f"{'PASS' if focus['passed'] else 'FAIL'} Tab e foco visível", focus, flush=True)

    set_viewport(640)
    driver.get(f"http://127.0.0.1:{server.server_port}/fixture.html")
    for _ in range(24):
        zoom = driver.js("return {width:innerWidth,dpr:devicePixelRatio,document:document.documentElement.scrollWidth}")
        if zoom["dpr"] >= 1.92:
            break
        zoom_key("ctrl+plus"); time.sleep(0.12)
    zoom = driver.js("return {width:innerWidth,dpr:devicePixelRatio,document:document.documentElement.scrollWidth}")
    zoom["passed"] = zoom["dpr"] >= 1.92 and zoom["width"] <= 320 and zoom["document"] <= zoom["width"]
    print(f"{'PASS' if zoom['passed'] else 'FAIL'} zoom real 200%", zoom, flush=True)
    (OUT / "zoom-200.png").write_bytes(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
    (OUT / "results.json").write_text(json.dumps({"matrix": records, "keyboard": focus, "zoom": zoom}, indent=2))
    if not all(record["passed"] for record in records) or not focus["passed"] or not zoom["passed"]:
        raise SystemExit("Falha na validação do acento visual.")
finally:
    zoom_key("ctrl+0")
    driver.close()
    server.shutdown()
