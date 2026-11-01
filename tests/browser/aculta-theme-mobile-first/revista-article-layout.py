#!/usr/bin/env python3
"""Compare mobile-first article typography with the previous Revista CSS."""

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
CSS_PATH = "web/themes/custom/aculta420/css/components/revista-post.css"
BASELINE_REF = os.environ.get("REVISTA_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "REVISTA_ARTICLE_WIDTHS", "320,360,390,575,576,600,768,1024,1280,1440",
).split(",")]
results = []


def source_css(ref):
    process = subprocess.run(
        ["git", "show", f"{ref}:{CSS_PATH}"], cwd=ROOT, capture_output=True,
        text=True, check=True,
    )
    return process.stdout


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


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

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 1000},
        })
        time.sleep(0.15)

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

fixture = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<style>:root{--aculta-surface-muted:#eee;--aculta-border-strong:#555;--aculta-border-subtle:#aaa;
--aculta-text-muted:#444;--aculta-border-accent:#145c39;--bs-border-radius:.375rem}
*{box-sizing:border-box}body{margin:0;font:16px/1.5 sans-serif}
.node--view-mode-full.node--type-revista{width:100%;padding:0 1rem}
.field__items{min-width:0}</style></head><body>
<main class="node--view-mode-full node--type-revista"><h1>Publicação editorial de exemplo</h1>
<div class="field--name-field-summary">Resumo introdutório com conteúdo suficiente para conferir a largura interna e o espaço de leitura em telas pequenas.</div>
<div class="field--name-body"><p>Texto de abertura para testar leitura à esquerda em dispositivos móveis e texto justificado em larguras maiores. Este parágrafo mantém linhas longas o bastante para tornar visíveis os espaços entre palavras.</p>
<h2>Intertítulo do artigo</h2><p>Outro parágrafo editorial com uma URL longa https://exemplo.invalid/""" + ("caminho-longo-" * 12) + """/fim para confirmar a quebra em largura estreita.</p></div>
<div class="field--name-field-editorial-category"><span class="field__label">Categoria</span><div class="field__items"><span class="field__item">Cultura e território</span></div></div>
<div class="field--name-field-tags"><span class="field__label">Assuntos</span><div class="field__items"><span class="field__item">Memória comunitária</span><span class="field__item">Comunicação popular</span></div></div>
</main></body></html>"""

measure = """return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
bodyAlign:getComputedStyle(document.querySelector('.field--name-body')).textAlign,
summaryPadding:getComputedStyle(document.querySelector('.field--name-field-summary')).padding,
categoryJustify:getComputedStyle(document.querySelector('.field--name-field-editorial-category')).justifyContent,
categoryTextAlign:getComputedStyle(document.querySelector('.field--name-field-editorial-category')).textAlign,
order:[...document.querySelector('main').children].map(el=>el.className||el.tagName)};"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text(encoding="utf-8")
    driver.js("const style=document.createElement('style');style.id='revista-css';style.textContent=arguments[0];document.head.append(style)", [baseline])

    for width in WIDTHS:
        viewport.set(width)
        before = driver.js(measure)
        driver.js("document.querySelector('#revista-css').textContent=arguments[0]", [candidate])
        time.sleep(0.1)
        after = driver.js(measure)
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"article-{width}.png").write_bytes(base64.b64decode(screenshot))
        mobile = width < 576
        expected = {
            "bodyAlign": "start" if mobile else "justify",
            "summaryPadding": "16px" if mobile else "20px 24px",
            "categoryJustify": "flex-start" if mobile else "flex-end",
            "categoryTextAlign": "start" if mobile else "end",
        }
        actual = {key: after[key] for key in expected}
        record(f"{width}px: tipografia/editorial mobile first", actual == expected,
               {"expected": expected, "actual": actual})
        record(f"{width}px: sem overflow e sem reordenar conteúdo",
               after["scrollWidth"] <= width and after["order"] == before["order"],
               {"scrollWidth": after["scrollWidth"], "order": after["order"]})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
