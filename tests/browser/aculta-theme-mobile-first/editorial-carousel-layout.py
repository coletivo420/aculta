#!/usr/bin/env python3
"""Compare the editorial carousel before/after its mobile-first CSS migration."""

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
CSS_PATH = "web/themes/custom/aculta420/css/components/editorial-carousel.css"
BASELINE_REF = os.environ.get("EDITORIAL_CAROUSEL_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "EDITORIAL_CAROUSEL_WIDTHS", "320,360,390,575,576,599,600,768,769,1024,1280,1440",
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
<style>:root{--aculta-text-primary:#111;--aculta-surface-muted:#eee;
--aculta-border-strong:#333;--aculta-focus-ring:#145c39;--aculta-interactive-hover-text:#fff;
--aculta-interactive-hover-bg:#145c39}*{box-sizing:border-box}
body{margin:0;font:16px sans-serif}.view-home-editorial-highlights{width:100%;padding:12px}
.vvjb{width:100%}.vvjb-carousel-wrapper{margin:auto}.vvjb-item{height:100px;background:#ddd}
.vvjb-button,.vvjb-play-pause-button{width:40px;height:40px}</style></head><body>
<section class="view-home-editorial-highlights"><div class="vvjb"><div class="vvjb-carousel-wrapper">
<div class="vvjb-item">Destaque editorial 1</div><div class="vvjb-item">Destaque editorial 2</div></div>
<div class="vvjb-carousel-controls"><button class="vvjb-button">Anterior</button></div></div></section></body></html>"""

measure = """const wrapper=document.querySelector('.vvjb-carousel-wrapper');
const item=document.querySelector('.vvjb-item');
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
wrapperWidth:wrapper.getBoundingClientRect().width,
wrapperMax:getComputedStyle(wrapper).maxWidth,
itemWidth:item.getBoundingClientRect().width,
itemRule:getComputedStyle(item).width,
controlsWidth:document.querySelector('.vvjb-carousel-controls').getBoundingClientRect().width};"""

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
        same_pixels = hashlib.sha256(before_png).hexdigest() == hashlib.sha256(after_png).hexdigest()
        record(f"{width}px: geometria e captura preservadas", actual_width == width and before == after and no_overflow and same_pixels,
               {"documentWidth": after["documentWidth"], "wrapperMax": after["wrapperMax"],
                "itemWidth": after["itemWidth"], "sameScreenshot": same_pixels})
        compact = width <= 768
        expected_width = after["wrapperWidth"] if compact else min(370, after["wrapperWidth"])
        expected = after["wrapperMax"] == ("390px" if compact else "764px") and after["itemWidth"] == expected_width
        record(f"{width}px: aprimoramento mobile-first correto", expected,
               {"expectedWrapperMax": "390px" if compact else "764px", "actualWrapperMax": after["wrapperMax"],
                "expectedItemWidth": expected_width, "actualItemWidth": after["itemWidth"]})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
