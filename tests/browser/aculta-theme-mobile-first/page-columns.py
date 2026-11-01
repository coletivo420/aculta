#!/usr/bin/env python3
"""Compare responsive columns layout baseline with mobile-first candidate CSS."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import quote

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
CSS_PATH = "web/themes/custom/aculta420/components/layouts/page-columns/page-columns.css"
BASELINE_REF = os.environ.get("PAGE_COLUMNS_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "PAGE_COLUMNS_WIDTHS", "320,360,390,599,600,767,768,1024,1280,1440",
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
        from websockets.sync.client import connect

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
<style>*{box-sizing:border-box}body{margin:0;font:16px sans-serif}.fixture{width:100%;padding:1rem}
.aculta-page-columns{width:100%;gap:1rem}.aculta-page-columns>*{min-height:5rem;padding:.5rem;background:#eee}</style>
</head><body><main>
<div class="aculta-page-columns aculta-page-columns--1"><div>Principal</div></div>
<div class="aculta-page-columns aculta-page-columns--2"><div>Principal</div><div>Secundária</div></div>
<div class="aculta-page-columns aculta-page-columns--3"><div>Principal</div><div>Secundária</div><div>Complementar</div></div>
</main></body></html>"""

measure = """return [...document.querySelectorAll('.aculta-page-columns')].map(grid => ({
  variant: [...grid.classList].find(name => name.startsWith('aculta-page-columns--')),
  columns: getComputedStyle(grid).gridTemplateColumns.trim().split(/\\s+/).length,
  childOrder: [...grid.children].map(child => child.textContent.trim()),
  overflow: grid.scrollWidth > grid.clientWidth,
}));"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text()
    driver.js("""const style=document.createElement('style');style.id='page-columns-candidate';
      style.textContent=arguments[0];document.head.append(style);return true;""", [baseline])

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        driver.js("document.querySelector('#page-columns-candidate').textContent=arguments[0]", [baseline])
        time.sleep(0.1)
        before = driver.js(measure)
        before_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"baseline-{width}.png").write_bytes(before_png)
        driver.js("document.querySelector('#page-columns-candidate').textContent=arguments[0]", [candidate])
        time.sleep(0.1)
        after = driver.js(measure)
        after_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"candidate-{width}.png").write_bytes(after_png)

        record(f"{width}px viewport", actual_width == width, {"measured": actual_width})
        for before_grid, after_grid, expected_count in zip(before, after, [1, 1 if width < 768 else 2, 1 if width < 768 else 3]):
            record(f"{width}px {after_grid['variant']} mobile-first columns",
                   after_grid["columns"] == expected_count and not after_grid["overflow"]
                   and (width < 768 or after_grid["columns"] == before_grid["columns"]),
                   {"baseline": before_grid["columns"], "candidate": after_grid["columns"],
                    "expected": expected_count, "overflow": after_grid["overflow"]})
            record(f"{width}px {after_grid['variant']} DOM order",
                   after_grid["childOrder"] == before_grid["childOrder"])

    (OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
finally:
    viewport.close()
    driver.close()

failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
