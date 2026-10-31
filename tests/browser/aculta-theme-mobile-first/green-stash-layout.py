#!/usr/bin/env python3
"""Check the Studio Green Stash candidate layout in a local render-array fixture."""

import asyncio
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
if not OUT.is_absolute() or PROJECT in OUT.parents:
    raise SystemExit("SUITE_OUT deve ser absoluto e fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))

WIDTHS = [320, 360, 390, 599, 600, 768, 1024, 1280, 1440]
MODES = ["light", "dark"]
ZOOM_TARGETS = (2.0, 4.0)
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


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

links = "".join(
    f'<a class="nav-link aculta-green-stash__navigation-link" href="#{slug}" aria-current="{"page" if slug == "produtos" else "false"}">{label}</a>'
    for slug, label in [
        ("visao-geral", "Visão geral"), ("produtos", "Produtos"),
        ("envios", "Envios"),
    ]
)
overview_links = "".join(
    f'<a class="nav-link aculta-green-stash__navigation-link" href="#{slug}" aria-current="{"page" if slug == "visao-geral" else "false"}">{label}</a>'
    for slug, label in [
        ("visao-geral", "Visão geral"), ("produtos", "Produtos"),
        ("envios", "Envios"),
    ]
)
markup = f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><style>{css}
*,*::before,*::after{{box-sizing:border-box}}body{{margin:0;font:16px/1.5 sans-serif}}
.aculta-main{{padding-block:1rem}}.aculta-container{{margin-inline:auto;width:100%}}
</style></head><body><main class="aculta-main"><div class="aculta-container">
<section class="studio aculta-green-stash"><h2>Produtos</h2>
<div class="aculta-green-stash__navigation" role="navigation" aria-label="Áreas do ACULTA STUDIO GREEN STASH">{links}</div>
<p>Nesta área são exibidos somente produtos autorizados para a loja atual.</p>
<div class="aculta-green-stash__products"><div class="search"><form><label for="busca">Buscar produtos</label>
<input id="busca" type="search"><button type="submit">Buscar</button></form></div>
<div class="item"><h3>Óleo artesanal com nome extenso para testar quebra natural</h3><a href="#detalhe">Ver detalhes</a></div>
<div class="next"><a href="#proxima">Próxima página</a></div></div>
<div class="aculta-green-stash__shipments"><div class="item"><div class="content"><h3>Pedido #420-000123</h3>
<p>Estado: em preparação</p><p>Serviço: envio padrão</p><a href="#rastreio">Ver rastreamento</a></div></div></div>
</section>
<section class="studio aculta-green-stash"><h2>Visão geral</h2>
<div class="aculta-green-stash__navigation" role="navigation" aria-label="Áreas do ACULTA STUDIO GREEN STASH">{overview_links}</div>
<p>Esta área está em preparação. Nenhuma operação comercial está disponível nesta tela.</p>
</section></div></main></body></html>"""

driver = Driver(bidi=True)
try:
    driver.get("data:text/html;charset=utf-8," + quote(markup))

    async def set_width(width):
        async with __import__("websockets").connect(driver.websocket_url) as ws:
            await ws.send(json.dumps({"id": 1, "method": "browsingContext.getTree", "params": {}}))
            while True:
                response = json.loads(await asyncio.wait_for(ws.recv(), timeout=10))
                if response.get("id") == 1:
                    if "error" in response:
                        raise RuntimeError(f"WebDriver BiDi getTree: {response['error']}")
                    context = response["result"]["contexts"][0]["context"]
                    break
            await ws.send(json.dumps({"id": 2, "method": "browsingContext.setViewport",
                                      "params": {"context": context, "viewport": {"width": width, "height": 900}}}))
            while True:
                response = json.loads(await asyncio.wait_for(ws.recv(), timeout=10))
                if response.get("id") == 2:
                    if "error" in response:
                        raise RuntimeError(f"WebDriver BiDi setViewport: {response['error']}")
                    return

    for mode in MODES:
        driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0])", [mode])
        for width in WIDTHS:
            asyncio.run(set_width(width))
            time.sleep(0.08)
            metrics = driver.js("""return (() => {
              const nav = document.querySelector('.aculta-green-stash__navigation');
              const links = [...nav.querySelectorAll('a')];
              const active = nav.querySelector('[aria-current="page"]');
              const overviewRoot = document.querySelectorAll('.aculta-green-stash')[1];
              const overviewNav = overviewRoot.querySelector('.aculta-green-stash__navigation');
              const overviewLinks = [...overviewNav.querySelectorAll('a')];
              const overviewActive = overviewNav.querySelector('[aria-current="page"]');
              const product = document.querySelector('.aculta-green-stash__products > div:has(> h3)');
              const shipment = document.querySelector('.aculta-green-stash__shipments > div:has(> div > h3)');
              const search = document.querySelector('.aculta-green-stash__products form');
              const notices = [...document.querySelectorAll('.aculta-green-stash > p')];
              const color = (value) => value.match(/[\\d.]+/g).slice(0, 3).map(Number);
              const luminance = (value) => color(value).map((part) => {
                const channel = part / 255;
                return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
              }).reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
              const callouts = notices.map((notice) => {
                const style = getComputedStyle(notice);
                const colors = [luminance(style.color), luminance(style.backgroundColor)].sort((a,b) => b-a);
                const contrast = (colors[0] + 0.05) / (colors[1] + 0.05);
                const rect = notice.getBoundingClientRect();
                return {text: notice.textContent.trim(), width: rect.width,
                  background: style.backgroundColor, border: style.borderInlineStartWidth,
                  padding: style.paddingInlineStart, margin: style.marginBlockStart,
                  contrast: Math.round(contrast * 100) / 100};
              });
              const input = search.querySelector('input[type="search"]');
              const button = search.querySelector('button');
              const rect = (el) => el.getBoundingClientRect();
              return {width: innerWidth, document: document.documentElement.scrollWidth,
                columns: getComputedStyle(nav).gridTemplateColumns.trim().split(/\\s+/).length,
                linkCount: links.length, links: links.map((el) => el.textContent.trim()),
                navLinkTops: links.map(el => Math.round(el.getBoundingClientRect().top)),
                navMinLinkHeight: Math.min(...links.map(el => el.getBoundingClientRect().height)),
                active: active?.textContent.trim(),
                overviewLinkCount: overviewLinks.length,
                overviewActive: overviewActive?.textContent.trim(),
                overviewNotice: overviewRoot.querySelector(':scope > p')?.textContent.trim(),
                minLinkHeight: Math.min(...links.map((el) => rect(el).height)),
                productCard: Boolean(product), shipmentCard: Boolean(shipment),
                searchSurface: getComputedStyle(search).backgroundColor,
                callouts,
                inputHeight: rect(input).height, buttonHeight: rect(button).height,
                cardWidth: product ? rect(product).width : 0,
                navWidth: rect(nav).width, mode: document.documentElement.dataset.bsTheme};
            })();""")
            record(f"{mode}/{width}px: viewport e sem overflow", metrics["width"] == width and metrics["document"] <= width, metrics)
            record(f"{mode}/{width}px: menu integrado de Green Stash mantém três áreas juntas e tocáveis",
                   metrics["columns"] == 3 and metrics["linkCount"] == 3
                   and len(set(metrics["navLinkTops"])) == 1 and metrics["navMinLinkHeight"] >= 44,
                   {key: metrics[key] for key in ("columns", "linkCount", "navLinkTops",
                                                   "navMinLinkHeight", "navWidth", "document")})
            record(f"{mode}/{width}px: somente três áreas implementadas e seção atual marcada",
                   metrics["linkCount"] == 3 and metrics["active"] == "Produtos"
                   and set(metrics["links"]) == {"Visão geral", "Produtos", "Envios"}, metrics)
            record(f"{mode}/{width}px: visão geral mostra estado real sem ação fictícia",
                   metrics["overviewLinkCount"] == 3
                   and metrics["overviewActive"] == "Visão geral"
                   and metrics["overviewNotice"] == "Esta área está em preparação. Nenhuma operação comercial está disponível nesta tela.",
                   {"linkCount": metrics["overviewLinkCount"], "active": metrics["overviewActive"],
                    "notice": metrics["overviewNotice"]})
            record(f"{mode}/{width}px: alvos de navegação e cartões de listagem",
                   metrics["minLinkHeight"] >= 48 and metrics["productCard"] and metrics["shipmentCard"], metrics)
            record(f"{mode}/{width}px: formulário de busca com controles tocáveis",
                   metrics["inputHeight"] >= 44 and metrics["buttonHeight"] >= 44
                   and metrics["searchSurface"] != "rgba(0, 0, 0, 0)", metrics)
            record(f"{mode}/{width}px: estado informativo de Visão geral e Produtos legível",
                   len(metrics["callouts"]) == 2
                   and all(notice["background"] != "rgba(0, 0, 0, 0)"
                           and notice["border"] == "4px"
                           and notice["padding"] == "16px"
                           and notice["margin"] == "0px"
                           and notice["contrast"] >= 4.5
                           and notice["width"] <= width
                           for notice in metrics["callouts"]), metrics["callouts"])
            if width in (320, 390, 1440):
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"green-stash-{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))
                driver.js("document.querySelectorAll('.aculta-green-stash')[1].scrollIntoView({block: 'start'}); return true;")
                time.sleep(0.08)
                overview_screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"green-stash-overview-{mode}-{width}.png").write_bytes(
                    base64.b64decode(overview_screenshot)
                )
                driver.js("window.scrollTo(0, 0); return true;")
            if width == 320:
                driver.js("document.querySelector('.aculta-green-stash__navigation-link').focus()")
                focus = driver.js("""const el=document.activeElement,s=getComputedStyle(el);
                  return {outlineStyle:s.outlineStyle,outlineWidth:s.outlineWidth};""")
                record(f"{mode}/320px: foco visível", focus["outlineStyle"] != "none" and focus["outlineWidth"] != "0px", focus)

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
              const rect = (node) => {const box=node.getBoundingClientRect();return {
                left:Math.round(box.left*100)/100,right:Math.round(box.right*100)/100,
                width:Math.round(box.width*100)/100};};
              const roots=[...document.querySelectorAll('.aculta-green-stash')];
              const form=roots[0].querySelector('form');
              const cards=[...roots[0].querySelectorAll('.aculta-green-stash__products > div:has(> h3), .aculta-green-stash__shipments > div:has(> div > h3)')];
              const notices=[...document.querySelectorAll('.aculta-green-stash > p')];
              const links=roots.flatMap(root=>[...root.querySelectorAll('.aculta-green-stash__navigation-link')]);
              const measured=[...notices,...links,...cards,form,
                form?.querySelector('input[type="search"]'),form?.querySelector('button')].filter(Boolean);
              return {width:innerWidth,document:document.documentElement.scrollWidth,
                body:document.body.scrollWidth,dpr:devicePixelRatio,
                boxes:measured.map(rect),noticeCount:notices.length,
                productCount:cards.length,linkCount:links.length};
            })();""")
            ratio = 1280 / max(1, zoom_metrics["width"])
            fit = all(box["left"] >= -1 and box["right"] <= zoom_metrics["width"] + 1
                      for box in zoom_metrics["boxes"])
            record(f"{mode}: zoom Firefox {round(target * 100)}% sem overflow nas áreas Green Stash",
                   ratio >= target - 0.08
                   and zoom_metrics["document"] <= zoom_metrics["width"]
                   and zoom_metrics["body"] <= zoom_metrics["width"]
                   and zoom_metrics["noticeCount"] == 2
                   and zoom_metrics["productCount"] == 2
                   and zoom_metrics["linkCount"] == 6
                   and fit,
                   {**zoom_metrics, "target": target, "ratio": ratio, "allElementsFit": fit})
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"green-stash-{mode}-zoom-{round(target * 100)}.png").write_bytes(
                base64.b64decode(screenshot)
            )
        press_browser_key("ctrl+0")
        time.sleep(0.12)

    report = {"checks": results, "passed": sum(item["passed"] for item in results),
              "failed": sum(not item["passed"] for item in results), "kind": "local fixture; not Runtime approval"}
    (OUT / "report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({"passed": report["passed"], "failed": report["failed"], "output": str(OUT)}, ensure_ascii=False))
    raise SystemExit(1 if report["failed"] else 0)
finally:
    driver.close()
