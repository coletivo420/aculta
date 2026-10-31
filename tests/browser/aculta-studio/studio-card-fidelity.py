#!/usr/bin/env python3
"""Visually validate Studio fidelity card/container CSS in a local fixture."""

import base64
import json
import os
import subprocess
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
ZOOM_TARGETS = (2.0, 4.0)
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


def press_tab(driver):
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "studio-card-fidelity",
            "actions": [
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
            ],
        }],
    })


def press_browser_key(key):
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True,
    ).splitlines()
    if not windows:
        raise RuntimeError("Janela visível do Firefox ESR não encontrada.")
    subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", key], check=True)


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
.list-unstyled {{ padding-inline-start: 0; list-style: none; }}
</style></head><body><main class="aculta-main"><div class="aculta-container">
<section class="studio aculta-account-entry" aria-labelledby="hub-title">
<h1 id="hub-title">Minha Conta</h1>
<p>Acesse seus dados, segurança e experiências ACULTA.</p>
<h2>Experiências</h2>
<ul class="studio__actions list-unstyled d-grid gap-2" aria-label="Experiências">
<li><div class="aculta-card card aculta-card--studio-fidelity"><div class="card-body"><a href="#marley">Marley</a></div></div></li>
<li><div class="aculta-card card aculta-card--studio-fidelity"><div class="card-body"><a href="#ganjalab">Ganjalab</a></div></div></li>
<li><div class="aculta-card card aculta-card--studio-fidelity"><div class="card-body"><a href="#we-sativa">ACULTA STUDIO WE SATIVA</a></div></div></li>
<li><div class="aculta-card card aculta-card--studio-fidelity"><div class="card-body"><a href="#green-stash">ACULTA STUDIO GREEN STASH</a></div></div></li>
<li><div class="aculta-card card aculta-card--studio-fidelity"><div class="card-body"><a href="#culturas">Culturas</a></div></div></li>
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
                event = json.loads(await asyncio.wait_for(ws.recv(), timeout=10))
                if event.get("id") == tree_id:
                    if "error" in event:
                        raise RuntimeError(f"WebDriver BiDi getTree: {event['error']}")
                    context = event["result"]["contexts"][0]["context"]
                    break
            command_id = tree_id + 1
            await ws.send(json.dumps({
                "id": command_id,
                "method": "browsingContext.setViewport",
                "params": {"context": context, "viewport": {"width": width, "height": 900}},
            }))
            while True:
                event = json.loads(await asyncio.wait_for(ws.recv(), timeout=10))
                if event.get("id") == command_id:
                    if "error" in event:
                        raise RuntimeError(f"WebDriver BiDi setViewport: {event['error']}")
                    break
    driver.get(data_url)
    for width in WIDTHS:
        asyncio.run(set_width(width))
        time.sleep(0.15)
        metrics = driver.js("""return (() => {
          const viewport = window.innerWidth;
          const container = document.querySelector('.aculta-container');
          const list = document.querySelector('.studio__actions');
          const cards = [...document.querySelectorAll('.aculta-account-entry .aculta-card--studio-fidelity')];
          const card = cards[0];
          const body = card.querySelector('.card-body');
          const experienceLinks = cards.map((item) => item.querySelector('.card-body > a'));
          const rect = container.getBoundingClientRect();
          return {
            viewport,
            document: document.documentElement.scrollWidth,
            containerWidth: Math.round(rect.width),
            containerPaddingInline: getComputedStyle(container).paddingInlineStart,
            columns: getComputedStyle(list).gridTemplateColumns.trim().split(/\\s+/).length,
            cardCount: cards.length,
            experienceLabels: experienceLinks.map((link) => link?.textContent.trim()),
            experienceLinkTargets: experienceLinks.map((link) => {
              const target = link.getBoundingClientRect();
              return {width: Math.round(target.width), height: Math.round(target.height)};
            }),
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
            cardLinkFontSize: getComputedStyle(experienceLinks[0]).fontSize,
            cardLinkFontWeight: getComputedStyle(experienceLinks[0]).fontWeight,
            studioScope: card.closest('.studio') !== null,
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
        expected_experiences = ["Marley", "Ganjalab", "ACULTA STUDIO WE SATIVA", "ACULTA STUDIO GREEN STASH", "Culturas"]
        check(f"{width}px: entrada real com cinco SDCs e destinos",
              metrics["studioScope"] and metrics["cardCount"] == 5
              and metrics["experienceLabels"] == expected_experiences,
              f"studio={metrics['studioScope']}, destinos={metrics['experienceLabels']}")
        check(f"{width}px: destinos com alvo mínimo de 44×44px",
              len(metrics["experienceLinkTargets"]) == 5
              and all(target["width"] >= 44 and target["height"] >= 44
                      for target in metrics["experienceLinkTargets"]),
              json.dumps(metrics["experienceLinkTargets"], ensure_ascii=False))
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
              and metrics["cardLinkFontSize"] == "16px",
              f"body={metrics['bodyFontSize']}/{metrics['bodyLineHeight']}, "
              f"h1={metrics['h1FontSize']}, card-link={metrics['cardLinkFontSize']}/{metrics['cardLinkFontWeight']}")
        if width in (320, 390, 768, 1440):
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"studio-card-{width}.png").write_bytes(base64.b64decode(screenshot))
        if width == 320:
            for index, expected_label in enumerate(expected_experiences, start=1):
                press_tab(driver)
                time.sleep(0.05)
                focus = json.loads(driver.js("""const link=document.activeElement;const r=link.getBoundingClientRect();const s=getComputedStyle(link);return JSON.stringify({label:link.textContent.trim(),focusVisible:link.matches(':focus-visible'),outlineStyle:s.outlineStyle,outlineWidth:s.outlineWidth,width:Math.round(r.width),height:Math.round(r.height)});"""))
                visible_ring = focus["outlineStyle"] != "none" and focus["outlineWidth"] not in ("0px", "0px 0px")
                check(f"320px: Tab {index} foca {expected_label} com indicador e alvo 44×44px",
                      focus["label"] == expected_label and focus["focusVisible"]
                      and visible_ring and focus["width"] >= 44 and focus["height"] >= 44,
                      json.dumps(focus, ensure_ascii=False))

    for target in ZOOM_TARGETS:
        press_browser_key("ctrl+0")
        time.sleep(0.12)
        asyncio.run(set_width(1280))
        zoom = driver.js("return {width: innerWidth, dpr: devicePixelRatio}")
        for _ in range(24):
            if 1280 / max(1, zoom["width"]) >= target - 0.08:
                break
            press_browser_key("ctrl+plus")
            time.sleep(0.12)
            zoom = driver.js("return {width: innerWidth, dpr: devicePixelRatio}")
        zoom_metrics = driver.js("""return (() => {
          const cards=[...document.querySelectorAll('.aculta-account-entry .aculta-card--studio-fidelity')];
          const links=cards.map(card=>card.querySelector('.card-body > a'));
          const rect=node=>{const box=node.getBoundingClientRect();return {
            left:Math.round(box.left*100)/100,right:Math.round(box.right*100)/100,
            width:Math.round(box.width*100)/100,height:Math.round(box.height*100)/100};};
          return {width:innerWidth,document:document.documentElement.scrollWidth,
            body:document.body.scrollWidth,cardCount:cards.length,
            labels:links.map(link=>link?.textContent.trim()),targets:links.map(rect),
            container:rect(document.querySelector('.aculta-container'))};
        })();""")
        ratio = 1280 / max(1, zoom_metrics["width"])
        all_fit = all(target_box["left"] >= -1
                      and target_box["right"] <= zoom_metrics["width"] + 1
                      and target_box["width"] >= 44 and target_box["height"] >= 44
                      for target_box in zoom_metrics["targets"])
        check(f"Firefox zoom {round(target * 100)}%: cards e rótulos oficiais sem overflow",
              ratio >= target - 0.08
              and zoom_metrics["document"] <= zoom_metrics["width"]
              and zoom_metrics["body"] <= zoom_metrics["width"]
              and zoom_metrics["cardCount"] == 5
              and zoom_metrics["labels"] == ["Marley", "Ganjalab", "ACULTA STUDIO WE SATIVA",
                                              "ACULTA STUDIO GREEN STASH", "Culturas"]
              and zoom_metrics["container"]["left"] >= -1
              and zoom_metrics["container"]["right"] <= zoom_metrics["width"] + 1
              and all_fit,
              {**zoom_metrics, "target": target, "ratio": ratio, "allTargetsFit": all_fit})
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"studio-card-zoom-{round(target * 100)}.png").write_bytes(base64.b64decode(screenshot))
    press_browser_key("ctrl+0")
    summary = {"fixture": "AccountExperienceProvider card consumer (five available Suites)", "widths": WIDTHS, "results": results}
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    driver.close()

failed = [item for item in results if not item["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
