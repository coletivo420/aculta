#!/usr/bin/env python3
"""Check the Studio status-message fidelity box in a local fixture.

The fixture reuses the Bootstrap alert markup actually delivered by the base
theme (role, dismiss button, heading and links) and the theme stylesheets. It
proves the OCS-F3 feedback presentation and its scoping: the fidelity box must
reach Studio containers and must not leak to a sibling non-Studio container.
It does not authenticate, submit forms, or validate Drupal rendering.
"""

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
    PROJECT / "web/libraries/bootstrap/dist/css/bootstrap.min.css",
    PROJECT / "web/themes/contrib/bootstrap/css/components/alerts.css",
    PROJECT / "web/themes/custom/aculta420/assets/icons/bootstrap-icons/font/bootstrap-icons.min.css",
    PROJECT / "web/themes/custom/aculta420/css/tokens.css",
    PROJECT / "web/themes/custom/aculta420/css/base.css",
    PROJECT / "web/themes/custom/aculta420/css/layout.css",
    PROJECT / "web/themes/custom/aculta420/css/drupal-bootstrap.css",
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


def alert(kind, heading, message, link_text):
    return f"""
<div class="alert alert-dismissible d-flex align-items-center fade show col-12 alert-{kind}" role="alert" aria-label="{heading}">
  <svg class="bi flex-shrink-0 me-4" role="img" aria-label="{heading}"><use xlink:href="#icon"/></svg>
  <div>
    <h2 class="alert-heading">{heading}</h2>
    {message} <a href="#acao">{link_text}</a>
  </div>
  <button type="button" class="btn-close" aria-label="Fechar"></button>
</div>"""


studio_alerts = "".join([
    alert("success", "Mensagem de status", "Curso salvo com sucesso.", "Ver curso"),
    alert("warning", "Aviso", "Revise os dados antes de publicar.", "Revisar"),
    alert("danger", "Erro", "Não foi possível salvar as alterações.", "Tentar de novo"),
    alert("info", "Informação", "As inscrições abrem em breve.", "Saiba mais"),
])
control_alert = alert("success", "Mensagem de status", "Conteúdo de outro purpose.", "Ver")

markup = f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><style>{css}
*,*::before,*::after{{box-sizing:border-box}}body{{margin:0}}
.aculta-main{{padding-block:1rem}}
.aculta-container{{margin-inline:auto;width:100%;padding-inline:1rem}}
</style></head><body>
<main class="aculta-main"><div class="container aculta-container">
  <div class="aculta-highlighted"><div class="alert-wrapper" data-drupal-messages>
    {studio_alerts}
  </div></div>
  <div class="aculta-content"><div class="studio"><h1>ACULTA Studio</h1>
    <p>Conteúdo do Studio autenticado.</p></div></div>
</div></main>
<main class="aculta-main"><div class="container aculta-container">
  <div class="aculta-highlighted"><div class="alert-wrapper" data-drupal-messages>
    {control_alert}
  </div></div>
  <div class="aculta-content"><h1>Outro purpose</h1><p>Conteúdo fora do Studio.</p></div>
</div></main>
</body></html>"""

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
              const studio = document.querySelectorAll('.aculta-highlighted')[0];
              const control = document.querySelectorAll('.aculta-highlighted')[1];
              const alerts = [...studio.querySelectorAll(':scope > .alert-wrapper > .alert')];
              const controlAlert = control.querySelector(':scope > .alert-wrapper > .alert');
              const style = (el) => getComputedStyle(el);
              const rect = (el) => el.getBoundingClientRect();
              const rgb = (value) => (value.match(/[\\d.]+/g) || ['0','0','0']).slice(0, 3).map(Number);
              const lum = (value) => rgb(value).map((part) => {
                const c = part / 255;
                return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
              }).reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
              const contrast = (a, b) => {
                const values = [lum(a), lum(b)].sort((x, y) => y - x);
                return Math.round(((values[0] + 0.05) / (values[1] + 0.05)) * 100) / 100;
              };
              const box = (el) => {
                const s = style(el);
                const b = rect(el);
                return {
                  borderTop: s.borderTopWidth, borderInlineStart: s.borderInlineStartWidth,
                  shadow: s.boxShadow, paddingTop: s.paddingTop, paddingRight: s.paddingRight,
                  display: s.display, flexWrap: s.flexWrap, fontFamily: s.fontFamily,
                  left: Math.round(b.left * 100) / 100, right: Math.round(b.right * 100) / 100,
                  width: Math.round(b.width * 100) / 100,
                };
              };
              const link = alerts[0].querySelector('a');
              const linkStyle = style(link);
              const heading = alerts[0].querySelector('.alert-heading');
              const expected = {
                success: style(alerts[0]).borderTopColor,
                warning: style(alerts[1]).borderTopColor,
                danger: style(alerts[2]).borderTopColor,
                info: style(alerts[3]).borderTopColor,
              };
              const probe = document.createElement('div');
              document.body.appendChild(probe);
              const token = (name) => {
                probe.style.color = 'var(' + name + ')';
                return style(probe).color;
              };
              const tokens = {
                success: token('--bs-success'), warning: token('--bs-warning'),
                danger: token('--bs-danger'), info: token('--bs-info'),
              };
              probe.remove();
              return {
                width: innerWidth, document: document.documentElement.scrollWidth,
                body: document.body.scrollWidth, mode: document.documentElement.dataset.bsTheme,
                count: alerts.length,
                boxes: alerts.map(box), control: box(controlAlert),
                icon: (() => {
                  const r = alerts[0].querySelector('svg').getBoundingClientRect();
                  return {width: Math.round(r.width), height: Math.round(r.height)};
                })(),
                alertHeight: Math.round(rect(alerts[0]).height),
                linkContrast: contrast(linkStyle.color, style(alerts[0]).backgroundColor),
                linkWeight: Number(linkStyle.fontWeight),
                linkDecoration: linkStyle.textDecorationLine,
                headingSize: style(heading).fontSize, headingWeight: Number(style(heading).fontWeight),
                expected, tokens,
                offenders: [...document.querySelectorAll('body *')]
                  .filter((el) => el.getBoundingClientRect().right > innerWidth + 1)
                  .slice(0, 5)
                  .map((el) => ({tag: el.tagName, cls: String(el.className),
                    right: Math.round(el.getBoundingClientRect().right),
                    width: Math.round(el.getBoundingClientRect().width)})),
              };
            })();""")
            boxed = metrics["boxes"][0]
            no_leak = metrics["control"]["shadow"] == "none" and metrics["control"]["borderTop"] != "2px"
            record(f"{mode}/{width}px: sem overflow horizontal",
                   metrics["width"] == width and metrics["document"] <= width and metrics["body"] <= width,
                   {k: metrics[k] for k in ("width", "document", "body", "mode", "offenders")})
            record(f"{mode}/{width}px: quatro mensagens com caixa Fidelity de 2px, sombra e respiro",
                   metrics["count"] == 4 and boxed["borderTop"] == "2px"
                   and boxed["borderInlineStart"] == "2px" and boxed["shadow"] != "none"
                   and boxed["paddingTop"] == "16px" and boxed["display"] == "flex"
                   and boxed["flexWrap"] == "wrap", {**boxed, "count": metrics["count"]})
            record(f"{mode}/{width}px: escopo não vaza para outro purpose",
                   no_leak, {"control": metrics["control"]})
            record(f"{mode}/{width}px: cores de estado seguem os tokens semânticos",
                   metrics["expected"] == metrics["tokens"], {"expected": metrics["expected"], "tokens": metrics["tokens"]})
            record(f"{mode}/{width}px: link, título e contraste legíveis",
                   metrics["linkContrast"] >= 4.5 and metrics["linkWeight"] >= 700
                   and "underline" in metrics["linkDecoration"]
                   and metrics["headingSize"] == "16px" and metrics["headingWeight"] >= 600,
                   {k: metrics[k] for k in ("linkContrast", "linkWeight", "linkDecoration",
                                            "headingSize", "headingWeight")})
            record(f"{mode}/{width}px: cada mensagem cabe no viewport",
                   all(b["left"] >= -1 and b["right"] <= width + 1 for b in metrics["boxes"]),
                   {"boxes": metrics["boxes"]})
            record(f"{mode}/{width}px: ícone não abre vazio vertical",
                   metrics["icon"]["height"] <= 40 and metrics["alertHeight"] <= 200,
                   {"icon": metrics["icon"], "alertHeight": metrics["alertHeight"]})
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"studio-status-{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))

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
              const boxes = [...document.querySelectorAll('.aculta-highlighted > .alert-wrapper > .alert')]
                .map((el) => {const b = el.getBoundingClientRect();return {
                  left: Math.round(b.left * 100) / 100, right: Math.round(b.right * 100) / 100};});
              return {width: innerWidth, document: document.documentElement.scrollWidth,
                body: document.body.scrollWidth, dpr: devicePixelRatio, boxes: boxes,
                borderTop: getComputedStyle(document.querySelector('.aculta-highlighted .alert')).borderTopWidth};
            })();""")
            ratio = 1280 / max(1, zoom_metrics["width"])
            fit = all(b["left"] >= -1 and b["right"] <= zoom_metrics["width"] + 1 for b in zoom_metrics["boxes"])
            record(f"{mode}: zoom Firefox {round(target * 100)}% mantém a caixa contida",
                   ratio >= target - 0.08 and zoom_metrics["document"] <= zoom_metrics["width"]
                   and zoom_metrics["body"] <= zoom_metrics["width"]
                   and zoom_metrics["borderTop"] == "2px" and fit,
                   {**zoom_metrics, "target": target, "ratio": ratio, "allElementsFit": fit})
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"studio-status-{mode}-zoom-{round(target * 100)}.png").write_bytes(
                base64.b64decode(screenshot))
        press_browser_key("ctrl+0")
finally:
    driver.close()

summary = {"total": len(results), "passed": sum(1 for item in results if item["passed"]),
           "failed": [item for item in results if not item["passed"]]}
(OUT / "report.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2))
print(json.dumps({k: summary[k] for k in ("total", "passed")}, ensure_ascii=False))
if summary["failed"]:
    raise SystemExit(1)
