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

WIDTHS = [
    320, 360, 390, 479, 480, 481, 599, 600, 767, 768,
    991, 992, 1024, 1099, 1100, 1119, 1120, 1279, 1280,
    1359, 1360, 1439, 1440, 1599, 1600, 1919, 1920,
]
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
<h1 id="hub-title">Experiências ACULTA</h1>
<ul class="studio__tabs nav" aria-label="Áreas do Studio">
<li><a class="studio__tab" aria-current="page" href="#marley">Marley</a></li>
<li><a class="studio__tab" href="#we-sativa">ACULTA STUDIO WE SATIVA</a></li>
<li><a class="studio__tab" href="#green-stash">ACULTA STUDIO GREEN STASH</a></li>
</ul>
<ul class="studio__subtabs nav" aria-label="Seções da área">
<li><a class="studio__link" aria-current="page" href="#produtos">Produtos</a></li>
<li><a class="studio__link" href="#remessas">Envios e remessas</a></li>
<li><a class="studio__link" href="#estoque">Estoque</a></li>
</ul>
<ul class="studio__actions">
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
          const navLinks = [...document.querySelectorAll('.studio__tabs a, .studio__subtabs a')];
          const rect = container.getBoundingClientRect();
          return {
            viewport,
            document: document.documentElement.scrollWidth,
            containerWidth: Math.round(rect.width),
            containerPaddingInline: getComputedStyle(container).paddingInlineStart,
            columns: getComputedStyle(list).gridTemplateColumns.trim().split(/\\s+/).length,
            cardCount: cards.length,
            radius: getComputedStyle(card).borderRadius,
            border: getComputedStyle(card).borderTopWidth,
            shadow: getComputedStyle(card).boxShadow,
            bodyPaddingInline: getComputedStyle(body).paddingInline,
            fontFamily: getComputedStyle(card).fontFamily,
            fontLoadedRegular: document.fonts.check('400 16px Montserrat', 'Experiências ACULTA'),
            fontLoadedBold: document.fonts.check('700 16px Montserrat', 'Experiências ACULTA'),
            bodyFontSize: getComputedStyle(card).fontSize,
            bodyLineHeight: getComputedStyle(card).lineHeight,
            h1FontSize: parseFloat(getComputedStyle(document.querySelector('#hub-title')).fontSize),
            h3FontSize: getComputedStyle(card.querySelector('h3')).fontSize,
            h3FontWeight: getComputedStyle(card.querySelector('h3')).fontWeight,
            studioScope: card.closest('.studio') !== null,
            navigationTargets: navLinks.map((link) => {
              const target = link.getBoundingClientRect();
              return {text: link.textContent.trim(), width: Math.round(target.width), height: Math.round(target.height)};
            }),
          };
        })();""")
        mobile = width < 768
        if width < 768:
            expected_container_width = width - 8
            expected_container_padding = "16px"
        elif width < 1120:
            expected_container_width = width - 40
            expected_container_padding = "24px"
        elif width < 1360:
            expected_container_width = width - 40
            expected_container_padding = "56px"
        elif width < 1920:
            expected_container_width = min(width - 96, 1712)
            expected_container_padding = "56px"
        else:
            expected_container_width = min(width - 96, 1760)
            expected_container_padding = "80px"
        check(f"{width}px: viewport CSS exato", metrics["viewport"] == width, metrics["viewport"])
        check(f"{width}px: sem overflow", metrics["document"] <= width,
              f"viewport={width}, documento={metrics['document']}")
        check(f"{width}px: layout fidelity do container",
              metrics["containerWidth"] == expected_container_width
              and metrics["containerPaddingInline"] == expected_container_padding,
              f"largura={metrics['containerWidth']}/{expected_container_width}, "
              f"padding={metrics['containerPaddingInline']}/{expected_container_padding}")
        check(f"{width}px: grade mobile-first", metrics["columns"] == (1 if mobile else 2),
              f"colunas={metrics['columns']}")
        check(f"{width}px: consumidor e SDC presentes",
              metrics["studioScope"] and metrics["cardCount"] == 2,
              f"studio={metrics['studioScope']}, cartões={metrics['cardCount']}")
        check(f"{width}px: abas com alvos mínimos de 44px",
              len(metrics["navigationTargets"]) == 6
              and all(target["width"] >= 44 and target["height"] >= 44
                      for target in metrics["navigationTargets"]),
              json.dumps(metrics["navigationTargets"], ensure_ascii=False))
        check(f"{width}px: geometria Fidelity aplicada",
              metrics["radius"] == "24px" and metrics["border"] == "1px"
              and metrics["bodyPaddingInline"] == "32px",
              f"raio={metrics['radius']}, borda={metrics['border']}, padding={metrics['bodyPaddingInline']}")
        check(f"{width}px: Montserrat 3.1.5 carregada",
              'Montserrat' in metrics["fontFamily"]
              and metrics["fontLoadedRegular"] and metrics["fontLoadedBold"],
              f"family={metrics['fontFamily']}, regular={metrics['fontLoadedRegular']}, "
              f"bold={metrics['fontLoadedBold']}")
        expected_h1_size = min(48, max(32, width * 0.07))
        check(f"{width}px: escala tipográfica Fidelity",
              metrics["bodyFontSize"] == "16px" and metrics["bodyLineHeight"] == "24px"
              and abs(metrics["h1FontSize"] - expected_h1_size) <= 0.1
              and metrics["h3FontSize"] == "24px" and metrics["h3FontWeight"] == "700",
              f"body={metrics['bodyFontSize']}/{metrics['bodyLineHeight']}, "
              f"h1={metrics['h1FontSize']}, h3={metrics['h3FontSize']}/{metrics['h3FontWeight']}")
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
