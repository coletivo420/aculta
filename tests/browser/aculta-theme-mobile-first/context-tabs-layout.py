#!/usr/bin/env python3
"""Check compact mobile-first gaps for both Context Tabs render shapes."""

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

CSS_PATH = "web/themes/custom/aculta420/components/patterns/context-tabs/context-tabs.css"
BASELINE_REF = os.environ.get("CONTEXT_TABS_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "CONTEXT_TABS_WIDTHS", "320,352,360,368,369,390,575,576,599,600,768,1024,1280,1440",
).split(",")]
OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    suffix = f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""
    print(f"{'PASS' if passed else 'FAIL'} {name}{suffix}", flush=True)


def source_css(ref):
    process = subprocess.run(
        ["git", "show", f"{ref}:{CSS_PATH}"],
        cwd=ROOT,
        capture_output=True,
        text=True,
        check=True,
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
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.15)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com Firefox ESR visível e DISPLAY=:0.")

fixture = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<style>*{box-sizing:border-box}:root{--aculta-border-default:#777;
--aculta-text-secondary:#333;--aculta-text-primary:#111;--aculta-text-accent:#145c39;
--aculta-focus-ring:#145c39}body{margin:0;font:16px sans-serif}.fixture{width:100%;padding:8px}
a{overflow-wrap:anywhere}</style></head><body><main class="fixture">
<nav class="aculta-context-tabs" aria-label="Seções">
  <div class="aculta-context-tabs__items" data-shape="flat">
    <a href="#visao" aria-current="page">Visão geral</a><a href="#historico">Histórico longo</a><a href="#discussao">Discussão</a>
  </div>
</nav>
<nav class="aculta-context-tabs" aria-label="Outras seções">
  <div class="aculta-context-tabs__items" data-shape="list"><ul>
    <li><a href="#wiki" aria-current="page">Wiki</a></li><li><a href="#cursos">Cursos e atividades</a></li><li><a href="#revista">Revista</a></li>
  </ul></div>
</nav></main></body></html>"""

measure = """return [...document.querySelectorAll('[data-shape]')].map(wrapper => {
  const content = wrapper.dataset.shape === 'list' ? wrapper.querySelector('ul') : wrapper;
  const style = getComputedStyle(content);
  return {
    shape: wrapper.dataset.shape,
    gap: style.gap,
    width: Math.round(content.getBoundingClientRect().width),
    scrollWidth: content.scrollWidth,
    clientWidth: content.clientWidth,
    labels: [...wrapper.querySelectorAll('a')].map(link => link.textContent.trim()),
    currentCount: wrapper.querySelectorAll('a[aria-current="page"]').length,
    shortTarget: [...wrapper.querySelectorAll('a')].every(link => link.getBoundingClientRect().height >= 44)
  };
});"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text()
    driver.js("""const style=document.createElement('style');style.id='context-tabs-css';
      style.textContent=arguments[0];document.head.append(style);return true;""", [baseline])

    for width in WIDTHS:
        actual = viewport.set(width)
        baseline_metrics = driver.js(measure)
        baseline_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"baseline-{width}.png").write_bytes(baseline_png)
        driver.js("document.querySelector('#context-tabs-css').textContent=arguments[0]", [candidate])
        candidate_metrics = driver.js(measure)
        screenshot = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"candidate-{width}.png").write_bytes(screenshot)
        expected_gap = "2px 6px" if width <= 368 else "4px 12px"
        page_width = driver.js("return document.documentElement.scrollWidth")
        record(f"{width}px: viewport efetivo", actual == width, {"measured": actual})
        record(f"{width}px: duas estruturas usam o gap mobile-first esperado",
               all(item["gap"] == expected_gap for item in candidate_metrics),
               {"expected": expected_gap, "items": candidate_metrics})
        record(f"{width}px: ordem, seleção e alvos permanecem válidos",
               all(item["currentCount"] == 1 and item["shortTarget"] for item in candidate_metrics)
               and candidate_metrics[0]["labels"] == baseline_metrics[0]["labels"]
               and candidate_metrics[1]["labels"] == baseline_metrics[1]["labels"],
               {"baseline": baseline_metrics, "candidate": candidate_metrics})
        record(f"{width}px: navegação sem overflow", page_width <= width
               and all(item["scrollWidth"] <= item["clientWidth"] for item in candidate_metrics),
               {"documentWidth": page_width, "items": candidate_metrics})
        record(f"{width}px: captura registrada", len(screenshot) > 0,
               {"sha256": hashlib.sha256(screenshot).hexdigest()})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
