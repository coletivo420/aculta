#!/usr/bin/env python3
"""Validate Drupal authentication route selectors and mobile-first layout in Firefox."""

import base64
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
CSS_PATH = "web/themes/custom/aculta420/css/components/auth.css"
BASELINE_REF = os.environ.get("AUTH_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "AUTH_WIDTHS", "320,360,390,575,576,599,600,768,1024,1280,1440",
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
<style>:root{--aculta-surface-page:#fff;--aculta-surface-raised:#f4f4f4;
--aculta-border-accent:#145c39;--bs-border-radius:6px}*{box-sizing:border-box}
body{margin:0;font:16px sans-serif}.aculta-main{width:100%;padding:16px}
.row{display:flex;justify-content:center}.aculta-page-heading,.aculta-content{width:100%;
background:#f4f4f4;border-inline-start:4px solid #145c39}.aculta-page-heading h1{margin:0}
.form-item{margin-block-end:1rem}.form-control{width:100%;min-height:44px}</style></head><body class="path-entrar">
<main class="aculta-main"><div class="aculta-page-heading"><h1>Entrar na conta ACULTA420</h1></div>
<div class="row"><section class="aculta-content"><form class="user-login-form">
<label class="form-item">Usuário ou endereço de e-mail<input class="form-control"></label>
<label class="form-item">Senha<input class="form-control"></label><div class="form-actions"><button>Entrar</button></div>
</form></section></div></main></body></html>"""

measure = """return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
headingWidth:document.querySelector('.aculta-page-heading').getBoundingClientRect().width,
headingPadding:getComputedStyle(document.querySelector('.aculta-page-heading')).padding,
headingMargin:getComputedStyle(document.querySelector('.aculta-page-heading')).marginBlockStart,
contentWidth:document.querySelector('.aculta-content').getBoundingClientRect().width,
contentPadding:getComputedStyle(document.querySelector('.aculta-content')).padding,
contentMargin:getComputedStyle(document.querySelector('.aculta-content')).margin};"""

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
        no_overflow = before["documentWidth"] <= width and after["documentWidth"] <= width
        expected_padding = "24px 20px 4px" if width < 576 else "32px 32px 4px"
        candidate_padding = after["headingPadding"] == expected_padding
        record(f"{width}px: layout real de autenticação sem overflow",
               actual_width == width and no_overflow and candidate_padding,
               {"documentWidth": after["documentWidth"], "baselineHeadingPadding": before["headingPadding"],
                "candidateHeadingPadding": after["headingPadding"], "contentPadding": after["contentPadding"]})
        expected = candidate_padding
        record(f"{width}px: aprimoramento mobile-first correto", expected,
               {"expectedMobilePadding": width < 576, "actual": after["headingPadding"]})

    for selector in ("body.path-entrar", "body.path-criar-conta", "body.path-recuperar-senha"):
        record(f"CSS contempla a classe de rota {selector}", selector in candidate)
    for selector in ("body.path-entrar", "body.path-criar-conta", "body.path-recuperar-senha"):
        record(f"baseline não dependia da classe de rota real {selector}", selector not in baseline)
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
