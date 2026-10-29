#!/usr/bin/env python3
"""Compare Webform Likert and confirmation modal layout before/after its mobile-first CSS migration."""

import base64
import hashlib
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import quote

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
CSS_PATH = "web/themes/custom/aculta420/css/components/webform.css"
BASELINE_REF = os.environ.get("WEBFORM_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "WEBFORM_WIDTHS", "320,360,390,575,576,599,600,650,651,768,769,1024,1280,1440",
).split(",")]
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


def source_css(ref):
    process = subprocess.run(
        ["git", "show", f"{ref}:{CSS_PATH}"], cwd=ROOT, capture_output=True,
        text=True, check=True,
    )
    return process.stdout


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
                return response.get("result", {})

    def set(self, driver, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.15)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

fixture = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<style>:root{--bs-border-radius:4px;--aculta-page-gutter:1rem}*{box-sizing:border-box}
body{margin:0;font:16px sans-serif}.fixture{width:100%;padding:1rem}
.hidden{display:none!important}.webform-likert-help,.webform-likert-description{padding:.5rem;background:#eee}
@media(min-width:48rem){:root{--aculta-page-gutter:clamp(1.5rem,3vw,2rem)}}
.ui-dialog.webform-confirmation-modal{width:600px;min-width:600px;margin:1rem auto;padding:1rem;background:#ddd}</style>
</head><body><main class="fixture"><section class="webform-likert-table">
<p class="webform-likert-help hidden">Ajuda da escala Likert para a pessoa respondente.</p>
<p class="webform-likert-description hidden">Descrição da pergunta em formato de escala.</p></section>
<div class="ui-dialog webform-confirmation-modal" style="width:600px;min-width:600px">Confirmação de envio do formulário.</div>
</main></body></html>"""

measure = """const help=document.querySelector('.webform-likert-help');
const description=document.querySelector('.webform-likert-description');
const modal=document.querySelector('.webform-confirmation-modal');
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
helpDisplay:getComputedStyle(help).display,
descriptionDisplay:getComputedStyle(description).display,
modalWidth:modal.getBoundingClientRect().width,
modalBaseWidth:document.querySelector('.fixture').clientWidth - 32,
modalCssWidth:getComputedStyle(modal).width,
modalMinWidth:getComputedStyle(modal).minWidth,
modalLeft:modal.getBoundingClientRect().left,
modalRight:modal.getBoundingClientRect().right,
pageGutter:getComputedStyle(document.documentElement).getPropertyValue('--aculta-page-gutter').trim()};"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text()
    driver.js("""const style=document.createElement('style');style.id='theme-candidate';
      style.textContent=arguments[0];document.head.append(style);return true;""", [baseline])

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        driver.js("window.scrollTo(0, 0)")
        time.sleep(0.1)
        before = driver.js(measure)
        before_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"baseline-{width}.png").write_bytes(before_png)
        driver.js("document.querySelector('#theme-candidate').textContent=arguments[0]", [candidate])
        driver.js("window.scrollTo(0, 0)")
        time.sleep(0.1)
        after = driver.js(measure)
        after_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"candidate-{width}.png").write_bytes(after_png)
        same_pixels = hashlib.sha256(before_png).hexdigest() == hashlib.sha256(after_png).hexdigest()
        gutter = float(after["pageGutter"].replace("px", ""))
        expected_modal_width = min(600, width - (2 * gutter))
        modal_fits = (
            abs(after["modalWidth"] - expected_modal_width) < 1
            and after["modalLeft"] >= gutter - 1
            and after["modalRight"] <= width - gutter + 1
        )
        baseline_exposes_min_width = (
            abs(float(before["modalMinWidth"].replace("px", "")) - 600) < 1
            and (before["documentWidth"] > width) == (width < 616)
        )
        no_overflow = after["documentWidth"] <= width
        record(f"{width}px: baseline representa minWidth:600 do Webform", baseline_exposes_min_width,
               {"baselineModalWidth": before["modalWidth"], "baselineMinWidth": before["modalMinWidth"],
                "baselineDocumentWidth": before["documentWidth"]})
        record(f"{width}px: modal cabe com o minWidth responsivo", actual_width == width and modal_fits and no_overflow,
               {"documentWidth": after["documentWidth"], "modalWidth": after["modalWidth"],
                "modalMinWidth": after["modalMinWidth"], "modalLeft": after["modalLeft"],
                "modalRight": after["modalRight"], "expectedWidth": expected_modal_width})
        if width > 650:
            record(f"{width}px: largura desktop dinâmica preservada", before == after and same_pixels,
                   {"modalWidth": after["modalWidth"], "sameScreenshot": same_pixels})
        compact_likert = width <= 768
        expected = (after["helpDisplay"] == ("inline" if compact_likert else "none")
                    and after["descriptionDisplay"] == ("block" if compact_likert else "none")
                    and abs(after["modalWidth"] - expected_modal_width) < 1)
        record(f"{width}px: Likert e modal respondem ao breakpoint", expected,
               {"likertCompact": compact_likert, "helpDisplay": after["helpDisplay"],
                "descriptionDisplay": after["descriptionDisplay"], "modalWidth": after["modalWidth"]})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
