#!/usr/bin/env python3
"""Check the saved-content presentation in a local Drupal-shaped fixture."""

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

out_value = os.environ.get("SUITE_OUT")
if not out_value:
    raise SystemExit("Defina SUITE_OUT para PNG e JSON fora do repositório.")
output = Path(out_value)
output.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(output / "profiles"))

widths = [320, 360, 390, 599, 600, 768, 1024, 1280, 1440]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


def set_width(driver, width):
    import asyncio
    import websockets

    async def resize():
        async with websockets.connect(driver.websocket_url) as ws:
            await ws.send(json.dumps({"id": 1, "method": "browsingContext.getTree", "params": {}}))
            while True:
                event = json.loads(await ws.recv())
                if event.get("id") == 1:
                    context = event["result"]["contexts"][0]["context"]
                    break
            await ws.send(json.dumps({
                "id": 2,
                "method": "browsingContext.setViewport",
                "params": {"context": context, "viewport": {"width": width, "height": 900}},
            }))
            while True:
                event = json.loads(await ws.recv())
                if event.get("id") == 2:
                    return

    asyncio.run(resize())


def press_tab(driver):
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "saved-content-focus",
            "actions": [
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
            ],
        }],
    })


css_files = [
    PROJECT / "web/themes/custom/aculta420/css/tokens.css",
    PROJECT / "web/themes/custom/aculta420/css/base.css",
    PROJECT / "web/themes/custom/aculta420/css/layout.css",
    PROJECT / "web/themes/custom/aculta420/css/components/content.css",
    PROJECT / "web/themes/custom/aculta420/css/components/studio.css",
]
css = "\n".join(path.read_text() for path in css_files)
font_dir = PROJECT / "web/themes/custom/aculta420/assets/fonts/montserrat"
for subset in ("latin", "latin-ext"):
    font_data = base64.b64encode((font_dir / f"Montserrat-{subset}.woff2").read_bytes()).decode("ascii")
    css = css.replace(
        f'url("../../assets/fonts/montserrat/Montserrat-{subset}.woff2")',
        f'url("data:font/woff2;base64,{font_data}")',
    )

fixture = f"""<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>{css}
*, *::before, *::after {{ box-sizing: border-box; }}
body {{ margin: 0; font: 16px/1.5 sans-serif; }}
.aculta-main {{ padding-block: 1rem; }}
.aculta-container {{ width: calc(100% - .5rem); max-width: 80rem; margin-inline: auto; padding-inline: 1rem; }}
.item-list {{ margin: 0; }}
.nav-tabs > li > a {{ border: 1px solid transparent; }}
</style></head><body><main class="aculta-main"><div class="aculta-container">
<section class="studio studio-saved-page" data-portal-ui="studio-saved-content">
  <h1 class="page-title" data-portal-ui="studio-page-title">Meu espaço: salvos e recomendações</h1>
  <div class="item-list"><ul class="nav nav-tabs studio-saved-tabs" data-portal-ui="studio-saved-tabs">
    <li><a class="is-active active-tab" aria-current="page" href="#salvos">Salvos (3)</a></li>
    <li><a aria-current="false" href="#seguindo">Seguindo (2)</a></li>
    <li><a aria-current="false" href="#recomendacoes">Recomendações</a></li>
  </ul></div>
  <div class="studio-saved-list-wrapper" data-portal-ui="studio-saved-list-wrapper">
    <div class="item-list"><ul class="studio-card-compact-list" data-portal-ui="studio-card-compact-list">
      <li><div class="studio-card-compact saved-item" data-portal-ui="studio-card-compact saved-item">
        <a class="compact-card-title" data-portal-ui="studio-saved-card-title" href="#publicacao-1">Publicação salva com um título que ocupa mais de uma linha em uma tela pequena</a>
        <div class="compact-card-meta"><span data-portal-ui="studio-saved-meta">Salvo em 31/10/2026</span> <span class="badge badge-purpose" data-portal-ui="studio-saved-purpose">REVISTA</span></div>
      </div></li>
      <li><div class="studio-card-compact saved-item" data-portal-ui="studio-card-compact saved-item">
        <a class="compact-card-title" data-portal-ui="studio-saved-card-title" href="#topico-2">Tópico acompanhado</a>
        <div class="compact-card-meta"><span data-portal-ui="studio-saved-meta">Salvo em 30/10/2026</span> <span class="badge badge-purpose" data-portal-ui="studio-saved-purpose">FORUM</span></div>
      </div></li>
      <li><div class="studio-card-compact recommendation-item" data-portal-ui="studio-card-compact recommendation-item">
        <a class="compact-card-title" data-portal-ui="studio-saved-card-title" href="#artigo-3">Conteúdo disponível na comunidade</a>
        <div class="studio-recommendation-reason" data-portal-ui="studio-recommendation-reason"><span data-portal-ui="studio-recommendation-badge">Relacionado a seus interesses</span></div>
      </div></li>
    </ul></div>
    <div class="studio-saved-pagination" data-portal-ui="studio-saved-pagination">
      <a class="pager-next" data-portal-ui="studio-saved-pager-next" href="#proxima">Próxima »</a>
      <span class="pager-info" data-portal-ui="studio-saved-pager-info">Página 1 de 2 (18 itens)</span>
    </div>
    <div class="studio-saved-empty" data-portal-ui="studio-saved-empty" hidden><p>Você ainda não possui itens nesta lista.</p></div>
  </div>
</section></div></main></body></html>"""

driver = Driver(bidi=True)
try:
    from urllib.parse import quote

    for mode in ("light", "dark"):
        page = fixture if mode == "light" else fixture.replace(
            '<html lang="pt-BR">', '<html lang="pt-BR" data-bs-theme="dark">'
        )
        driver.get("data:text/html;charset=utf-8," + quote(page))
        for width in widths:
            set_width(driver, width)
            time.sleep(0.1)
            metrics = driver.js("""return (() => {
              const root = document.querySelector('.studio-saved-page');
              const tabs = [...root.querySelectorAll('.studio-saved-tabs a')];
              const cards = [...root.querySelectorAll('.studio-card-compact')];
              const links = [...root.querySelectorAll('.compact-card-title')];
              const rect = (element) => { const r = element.getBoundingClientRect(); return {width: Math.round(r.width), height: Math.round(r.height)}; };
              const luminance = (color) => color.match(/[0-9]+/g).slice(0, 3).map((value) => {
                const channel = Number(value) / 255;
                return channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
              }).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
              const metaStyle = getComputedStyle(root.querySelector('[data-portal-ui="studio-saved-meta"]'));
              const surfaceStyle = getComputedStyle(cards[0]);
              const luminances = [luminance(metaStyle.color), luminance(surfaceStyle.backgroundColor)].sort((a, b) => b - a);
              return {
                viewport: innerWidth,
                document: document.documentElement.scrollWidth,
                activeTabs: tabs.filter((link) => link.getAttribute('aria-current') === 'page').length,
                tabTargets: tabs.map(rect),
                cardCount: cards.length,
                cardWidths: cards.map((card) => Math.round(card.getBoundingClientRect().width)),
                titleTargets: links.map(rect),
                tokens: getComputedStyle(root).getPropertyValue('--aculta-studio-fidelity-surface').trim(),
                cardSurface: getComputedStyle(cards[0]).backgroundColor,
                metaContrast: (luminances[0] + 0.05) / (luminances[1] + 0.05),
                tabsOverflow: root.querySelector('.studio-saved-tabs').scrollWidth > root.querySelector('.studio-saved-tabs').clientWidth,
                emptyVisible: getComputedStyle(root.querySelector('.studio-saved-empty')).display !== 'none'
              };
            })();""")
            expected_surface = "rgb(255, 255, 255)" if mode == "light" else "rgb(22, 24, 26)"
            check(f"{mode}/{width}: sem overflow e abas quebráveis", metrics["document"] == width and not metrics["tabsOverflow"], metrics)
            check(f"{mode}/{width}: uma aba atual", metrics["activeTabs"] == 1, metrics["activeTabs"])
            check(f"{mode}/{width}: abas e títulos têm alvo de 44px", all(item["width"] >= 44 and item["height"] >= 44 for item in metrics["tabTargets"] + metrics["titleTargets"]), metrics["tabTargets"] + metrics["titleTargets"])
            check(f"{mode}/{width}: três itens de conteúdo sem colapso", metrics["cardCount"] == 3 and all(value > 0 for value in metrics["cardWidths"]), metrics["cardWidths"])
            check(f"{mode}/{width}: cartões usam a superfície do modo", metrics["cardSurface"] == expected_surface, metrics["cardSurface"])
            check(f"{mode}/{width}: metadados alcançam contraste AA", metrics["metaContrast"] >= 4.5, round(metrics["metaContrast"], 2))
            check(f"{mode}/{width}: estado vazio não substitui uma lista preenchida", not metrics["emptyVisible"], metrics["emptyVisible"])
            if width in (320, 1440):
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (output / f"saved-{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))

        if mode == "light":
            driver.get("data:text/html;charset=utf-8," + quote(fixture))
            driver.js("""document.querySelector('.studio-card-compact-list').remove(); document.querySelector('.studio-saved-pagination').remove(); document.querySelector('.studio-saved-empty').hidden = false; return true;""")
            set_width(driver, 320)
            empty_metrics = driver.js("""return {
              visible: getComputedStyle(document.querySelector('.studio-saved-empty')).display !== 'none',
              listAbsent: !document.querySelector('.studio-card-compact-list'),
              message: document.querySelector('.studio-saved-empty p').textContent.trim(),
              width: document.documentElement.scrollWidth
            };""")
            check("320px: estado vazio informativo visível sem lista", empty_metrics["visible"] and empty_metrics["listAbsent"] and bool(empty_metrics["message"]) and empty_metrics["width"] == 320, empty_metrics)

            driver.get("data:text/html;charset=utf-8," + quote(fixture))
            set_width(driver, 320)
            focus_order = ["Salvos (3)", "Seguindo (2)", "Recomendações", "Publicação salva com um título que ocupa mais de uma linha em uma tela pequena"]
            for expected in focus_order:
                press_tab(driver)
                focus = driver.js("""const element = document.activeElement; const r = element.getBoundingClientRect(); const style = getComputedStyle(element); return {text: element.textContent.trim(), focusVisible: element.matches(':focus-visible'), outlineStyle: style.outlineStyle, outlineWidth: style.outlineWidth, width: Math.round(r.width), height: Math.round(r.height)};""")
                check(f"320px: Tab foca {expected} com indicador visível", focus["text"] == expected and focus["focusVisible"] and focus["outlineStyle"] != "none" and float(focus["outlineWidth"].replace("px", "")) >= 2 and focus["width"] >= 44 and focus["height"] >= 44, focus)

    summary = {"checks": len(results), "passed": sum(1 for item in results if item["ok"]), "failed": [item for item in results if not item["ok"]], "fixture_only": True}
    (output / "results.json").write_text(json.dumps({"summary": summary, "results": results}, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps(summary, ensure_ascii=False))
    raise SystemExit(1 if summary["failed"] else 0)
finally:
    driver.close()
