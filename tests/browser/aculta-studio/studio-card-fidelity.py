#!/usr/bin/env python3
"""Visually validate Studio fidelity card/container CSS in a local fixture."""

import base64
import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import quote

PROJECT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT para guardar PNG e JSON fora do repositório.")
OUT = Path(OUT_VALUE)
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))

WIDTHS = [320, 360, 390, 599, 600, 768, 1024, 1280, 1440]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


css_files = [
    PROJECT / "web/themes/custom/aculta420/css/tokens.css",
    PROJECT / "web/themes/custom/aculta420/css/base.css",
    PROJECT / "web/themes/custom/aculta420/css/layout.css",
    PROJECT / "web/themes/custom/aculta420/css/components/content.css",
    PROJECT / "web/themes/custom/aculta420/css/components/studio.css",
    PROJECT / "web/themes/custom/aculta420/components/patterns/card/card.css",
]
css = "\n".join(path.read_text() for path in css_files)
font_dir = PROJECT / "web/themes/custom/aculta420/assets/fonts/montserrat"
for subset in ("latin", "latin-ext"):
    font_path = font_dir / f"Montserrat-{subset}.woff2"
    font_data = base64.b64encode(font_path.read_bytes()).decode("ascii")
    css = css.replace(
        f'url("../../assets/fonts/montserrat/Montserrat-{subset}.woff2")',
        f'url("data:font/woff2;base64,{font_data}")',
    )
markup = f"""<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>{css}
*, *::before, *::after {{ box-sizing: border-box; }}
body {{ margin: 0; font: 16px/1.5 sans-serif; }}
.aculta-main {{ padding-block: 1rem; }}
.aculta-container {{ margin-inline: auto; }}
.studio-hub {{ min-width: 0; }}
.studio-hub .studio__actions {{ display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; padding: 0; margin: 0; list-style: none; }}
.studio-hub .studio__actions > li {{ min-width: 0; }}
.card {{ display: flex; flex-direction: column; min-width: 0; }}
.card-body {{ flex: 1 1 auto; }}
.card-body h3 {{ margin-block: 0 .5rem; }}
.card-body p {{ margin: 0; }}
@media (min-width: 48rem) {{ .studio-hub .studio__actions {{ grid-template-columns: repeat(2, minmax(0, 1fr)); }} }}
</style></head><body><main class="aculta-main"><div class="aculta-container">
<section class="studio studio-hub" aria-labelledby="hub-title">
<h1 id="hub-title">Experiências ACULTA</h1><ul class="studio__actions">
<li><article class="aculta-card card aculta-card--studio-fidelity"><div class="card-body"><h3><a href="#marley">Marley — acesso editorial</a></h3><p>Gerencie atividades editoriais e conteúdos disponíveis na sua conta.</p></div></article></li>
<li><article class="aculta-card card aculta-card--studio-fidelity"><div class="card-body"><h3><a href="#greenstash">ACULTA STUDIO GREEN STASH</a></h3><p>A área de gestão comercial integrada do Studio.</p></div></article></li>
</ul></section></div></main></body></html>"""

data_url = "data:text/html;charset=utf-8," + quote(markup)
driver = Driver(bidi=True)
bidi = None
try:
    import websockets
    import asyncio

    async def set_width(width):
        async with websockets.connect(driver.websocket_url) as ws:
            tree_id = 1
            await ws.send(json.dumps({"id": tree_id, "method": "browsingContext.getTree", "params": {}}))
            while True:
                event = json.loads(await ws.recv())
                if event.get("id") == tree_id:
                    context = event["result"]["contexts"][0]["context"]
                    break
            command_id = tree_id + 1
            await ws.send(json.dumps({
                "id": command_id,
                "method": "browsingContext.setViewport",
                "params": {"context": context, "viewport": {"width": width, "height": 900}},
            }))
            while True:
                event = json.loads(await ws.recv())
                if event.get("id") == command_id:
                    break
    driver.get(data_url)
    for width in WIDTHS:
        asyncio.run(set_width(width))
        time.sleep(0.15)
        metrics = driver.js("""return (() => {
          const viewport = window.innerWidth;
          const container = document.querySelector('.aculta-container');
          const list = document.querySelector('.studio__actions');
          const cards = [...document.querySelectorAll('.aculta-card--studio-fidelity')];
          const card = cards[0];
          const body = card.querySelector('.card-body');
          const rect = container.getBoundingClientRect();
          return {
            viewport,
            document: document.documentElement.scrollWidth,
            containerWidth: Math.round(rect.width),
            columns: getComputedStyle(list).gridTemplateColumns.trim().split(/\\s+/).length,
            cardCount: cards.length,
            radius: getComputedStyle(card).borderRadius,
            border: getComputedStyle(card).borderTopWidth,
            shadow: getComputedStyle(card).boxShadow,
            bodyPaddingInline: getComputedStyle(body).paddingInline,
            studioScope: card.closest('.studio') !== null,
          };
        })();""")
        mobile = width < 768
        check(f"{width}px: viewport CSS exato", metrics["viewport"] == width, metrics["viewport"])
        check(f"{width}px: sem overflow", metrics["document"] <= width,
              f"viewport={width}, documento={metrics['document']}")
        check(f"{width}px: grade mobile-first", metrics["columns"] == (1 if mobile else 2),
              f"colunas={metrics['columns']}")
        check(f"{width}px: consumidor e SDC presentes",
              metrics["studioScope"] and metrics["cardCount"] == 2,
              f"studio={metrics['studioScope']}, cartões={metrics['cardCount']}")
        check(f"{width}px: geometria Fidelity aplicada",
              metrics["radius"] == "24px" and metrics["border"] == "1px"
              and metrics["bodyPaddingInline"] == "32px",
              f"raio={metrics['radius']}, borda={metrics['border']}, padding={metrics['bodyPaddingInline']}")
        if width in (320, 390, 768, 1440):
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"studio-card-{width}.png").write_bytes(base64.b64decode(screenshot))
    summary = {"fixture": "Studio Hub card consumer", "widths": WIDTHS, "results": results}
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    driver.close()

failed = [item for item in results if not item["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
