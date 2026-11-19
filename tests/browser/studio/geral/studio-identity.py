#!/usr/bin/env python3
"""Check the OCS-A1 Aculta Identity token layer against the Fidelity layer.

The fixture renders the same Studio markup twice (Fidelity and Identity) and
verifies that only the presentation tokens change: institutional colours,
contrasts and no overflow. It does not authenticate or query Drupal.
"""

import asyncio
import base64
import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import quote

PROJECT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(PROJECT / "tests/browser/shop/aculta-cpf-checkout"))
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
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


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
    PROJECT / "web/themes/custom/aculta420/css/components/studio-identity.css",
]
css = "\n".join(path.read_text() for path in css_files)
font_dir = PROJECT / "web/themes/custom/aculta420/assets/fonts/montserrat"
for subset in ("latin", "latin-ext"):
    font_data = base64.b64encode((font_dir / f"Montserrat-{subset}.woff2").read_bytes()).decode("ascii")
    css = css.replace(
        f'url("../../assets/fonts/montserrat/Montserrat-{subset}.woff2")',
        f'url("data:font/woff2;base64,{font_data}")',
    )

markup = f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><style>{css}
*,*::before,*::after{{box-sizing:border-box}}body{{margin:0}}
.aculta-main{{padding-block:1rem}}
.aculta-container{{margin-inline:auto;width:100%;padding-inline:1rem}}
</style></head><body class="path-culturas"><main class="aculta-main">
<div class="container aculta-container">
  <div class="aculta-highlighted"><div class="alert-wrapper" data-drupal-messages>
    <div class="alert alert-success d-flex align-items-center" role="status" aria-label="Status">
      <div><h2 class="alert-heading">Mensagem de status</h2>Curso salvo.</div>
    </div>
  </div></div>
  <div class="aculta-content"><div class="studio">
    <h1>Ação do Studio</h1>
    <p>Texto com <a href="#acao">link institucional</a>.</p>
    <form>
      <div class="form-item"><label for="campo">Campo</label><input id="campo"></div>
      <div class="form-item"><label for="selecao">Seleção</label><select id="selecao"><option>Opção</option></select></div>
      <div class="form-item"><label for="texto">Texto</label><textarea id="texto"></textarea></div>
      <div class="form-actions"><button type="submit" class="form-submit">Salvar</button></div>
    </form>
    <nav class="nav nav-tabs studio__tabs"><a class="nav-link studio__tab" href="#ativo" aria-current="page">Ativo</a></nav>
    <div class="card aculta-card"><div class="card-body">Card do Studio</div></div>
  </div></div>
</div></main></body></html>"""

driver = Driver(bidi=True)
try:
    driver.get("data:text/html;charset=utf-8," + quote(markup))

    async def set_width(width):
        async with __import__("websockets").connect(driver.websocket_url) as ws:
            await ws.send(json.dumps({"id": 1, "method": "browsingContext.getTree", "params": {}}))
            while True:
                response = json.loads(await asyncio.wait_for(ws.recv(), timeout=10))
                if response.get("id") == 1:
                    context = response["result"]["contexts"][0]["context"]
                    break
            await ws.send(json.dumps({"id": 2, "method": "browsingContext.setViewport",
                                      "params": {"context": context, "viewport": {"width": width, "height": 900}}}))
            while True:
                response = json.loads(await asyncio.wait_for(ws.recv(), timeout=10))
                if response.get("id") == 2:
                    return

    def measure():
        return driver.js("""return (() => {
          const studio = document.querySelector('.studio');
          const probe = document.createElement('span');
          studio.append(probe);
          const token = (name) => {
            probe.style.color = 'var(' + name + ')';
            return getComputedStyle(probe).color;
          };
          const rgb = (value) => (value.match(/[\\d.]+/g) || ['0','0','0']).slice(0, 3).map(Number);
          const lum = (value) => rgb(value).map((part) => {
            const c = part / 255;
            return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
          }).reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
          const contrast = (a, b) => {
            const values = [lum(a), lum(b)].sort((x, y) => y - x);
            return Math.round(((values[0] + 0.05) / (values[1] + 0.05)) * 100) / 100;
          };
          const link = studio.querySelector('a');
          const button = studio.querySelector('button');
          const input = studio.querySelector('input');
          const heading = studio.querySelector('h1');
          const card = studio.querySelector('.aculta-card');
          const tab = studio.querySelector('.studio__tab[aria-current="page"]');
          const alert = document.querySelector('.aculta-highlighted .alert');
          const measured = {
            identity: document.documentElement.classList.contains('studio-identity'),
            mode: document.documentElement.dataset.bsTheme,
            width: innerWidth, document: document.documentElement.scrollWidth,
            tokens: {
              primary: token('--aculta-studio-fidelity-primary'),
              link: token('--aculta-studio-fidelity-link'),
              text: token('--aculta-studio-fidelity-text'),
              muted: token('--aculta-studio-fidelity-muted'),
              surface: token('--aculta-studio-fidelity-surface'),
              surfaceMuted: token('--aculta-studio-fidelity-surface-muted'),
              buttonPrimaryBg: token('--aculta-studio-fidelity-button-primary-background'),
              buttonPrimaryText: token('--aculta-studio-fidelity-button-primary-text'),
              inputBorderBottom: token('--aculta-studio-fidelity-input-border-bottom'),
              focus: token('--aculta-studio-fidelity-input-focus'),
            },
            computed: {
              linkColor: getComputedStyle(link).color,
              linkContrast: contrast(getComputedStyle(link).color, getComputedStyle(document.body).backgroundColor),
              buttonBg: getComputedStyle(button).backgroundColor,
              buttonText: getComputedStyle(button).color,
              buttonContrast: contrast(getComputedStyle(button).color, getComputedStyle(button).backgroundColor),
              buttonBorder: getComputedStyle(button).borderTopColor,
              inputBorderBottom: getComputedStyle(input).borderBottomColor,
              alertBorder: getComputedStyle(alert).borderTopColor,
              headingFamily: getComputedStyle(heading).fontFamily,
              inputRadius: getComputedStyle(input).borderTopLeftRadius,
              buttonRadius: getComputedStyle(button).borderTopLeftRadius,
              cardRadius: getComputedStyle(card).borderTopLeftRadius,
              tabShadow: getComputedStyle(tab).boxShadow,
            },
          };
          probe.remove();
          return measured;
        })();""")

    for mode in MODES:
        driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0])", [mode])
        for identity in (False, True):
            driver.js("document.documentElement.classList.toggle('studio-identity', arguments[0])", [identity])
            for width in WIDTHS:
                asyncio.run(set_width(width))
                time.sleep(0.06)
                metrics = measure()
                tokens = metrics["tokens"]
                computed = metrics["computed"]
                channel = "identity" if identity else "fidelity"
                if identity:
                    expected_primary = "rgb(12, 60, 41)" if mode == "light" else "rgb(244, 239, 232)"
                    record(f"{channel}/{mode}/{width}px: token primário institucional",
                           tokens["primary"] == expected_primary,
                           {"primary": tokens["primary"], "expected": expected_primary})
                    record(f"{channel}/{mode}/{width}px: CTA amarelo com texto verde-escuro",
                           tokens["buttonPrimaryBg"] == "rgb(242, 202, 54)"
                           and tokens["buttonPrimaryText"] == "rgb(12, 60, 41)",
                           {"bg": tokens["buttonPrimaryBg"], "text": tokens["buttonPrimaryText"]})
                    record(f"{channel}/{mode}/{width}px: link e botão mantêm contraste AA",
                           computed["linkContrast"] >= 4.5 and computed["buttonContrast"] >= 4.5,
                           {k: computed[k] for k in ("linkContrast", "buttonContrast", "linkColor",
                                                    "buttonBg", "buttonText")})
                    record(f"{channel}/{mode}/{width}px: acabamento com Oswald e cantos institucionais",
                           "Oswald" in computed["headingFamily"]
                           and computed["inputRadius"] == computed["cardRadius"]
                           and computed["inputRadius"] != "0px",
                           {k: computed[k] for k in ("headingFamily", "inputRadius",
                                                    "buttonRadius", "cardRadius")})
                    record(f"{channel}/{mode}/{width}px: aba ativa com acento amarelo",
                           "inset" in computed["tabShadow"] and "242, 202, 54" in computed["tabShadow"],
                           {"tabShadow": computed["tabShadow"]})
                else:
                    record(f"{channel}/{mode}/{width}px: camada Fidelity preservada",
                           tokens["primary"] == "rgb(157, 25, 128)",
                           {"primary": tokens["primary"]})
                record(f"{channel}/{mode}/{width}px: sem overflow horizontal",
                       metrics["width"] == width and metrics["document"] <= width,
                       {k: metrics[k] for k in ("width", "document", "mode")})
                if width in (320, 1440):
                    shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    (OUT / f"{channel}-{mode}-{width}.png").write_bytes(base64.b64decode(shot))
    driver.js("document.documentElement.classList.remove('studio-identity')")
finally:
    driver.close()

summary = {"total": len(results), "passed": sum(1 for item in results if item["passed"]),
           "failed": [item for item in results if not item["passed"]]}
(OUT / "report.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2))
print(json.dumps({k: summary[k] for k in ("total", "passed")}, ensure_ascii=False))
if summary["failed"]:
    raise SystemExit(1)
