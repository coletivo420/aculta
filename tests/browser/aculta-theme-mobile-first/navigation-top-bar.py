#!/usr/bin/env python3
"""Compare Drupal Navigation top-bar baseline and mobile-first theme CSS."""

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
CSS_PATH = "web/themes/custom/aculta420/css/drupal-bootstrap.css"
BASELINE_REF = os.environ.get("TOP_BAR_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "TOP_BAR_WIDTHS", "320,360,390,575,576,599,600,767,768,1024,1280,1440",
).split(",")]
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    suffix = f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""
    print(f"{'PASS' if passed else 'FAIL'} {name}{suffix}", flush=True)


def source_css(ref):
    return subprocess.run(
        ["git", "show", f"{ref}:{CSS_PATH}"], cwd=ROOT,
        capture_output=True, text=True, check=True,
    ).stdout


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
        time.sleep(0.12)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

fixture = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<style>
*{box-sizing:border-box}body{margin:0;font:16px sans-serif}
.top-bar{width:100%;padding:1rem;background:#eee}
.top-bar__content{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;
  justify-content:center;gap:16px;width:100%}
.top-bar__title{min-width:0;overflow-wrap:anywhere}
.top-bar__context{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:20px}
.top-bar__actions{display:flex;justify-content:end;gap:.5rem}
.top-bar__actions a{white-space:nowrap;padding:.5rem}
</style></head><body>
<header class="top-bar" data-drupal-admin-styles><div class="top-bar__content">
<div class="top-bar__title">Administração</div>
<div class="top-bar__context"><span>Contexto administrativo com título extenso para testar quebras em telas estreitas</span></div>
<nav class="top-bar__actions" aria-label="Ações administrativas">
<a href="#one">Configurações</a><a href="#two">Editar</a><a href="#three">Mais ações</a>
</nav></div></header><main style="height:100vh">Conteúdo</main>
</body></html>"""

measure = """return (() => {
 const bar=document.querySelector('.top-bar'), content=document.querySelector('.top-bar__content');
 const context=document.querySelector('.top-bar__context'), actions=document.querySelector('.top-bar__actions');
 const rect=(el)=>{const r=el.getBoundingClientRect();return {top:Math.round(r.top),left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),height:Math.round(r.height)}};
 return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
   columns:getComputedStyle(content).gridTemplateColumns.split(/\\s+/).length,
   align:getComputedStyle(content).alignItems,gap:getComputedStyle(content).gap,
   contextJustify:getComputedStyle(context).justifyContent,
   contextMinWidth:getComputedStyle(context).minWidth,
   contextChild:[getComputedStyle(context.firstElementChild).minWidth,
     getComputedStyle(context.firstElementChild).maxWidth,getComputedStyle(context.firstElementChild).overflowWrap],
   actionsJustify:getComputedStyle(actions).justifyContent,actionsWrap:getComputedStyle(actions).flexWrap,
   actionsMinWidth:getComputedStyle(actions).minWidth,actionsMaxWidth:getComputedStyle(actions).maxWidth,
   actionsGap:getComputedStyle(actions).gap,
   regions:[...content.children].map(rect),
   regionOrder:[...content.children].map(el=>el.className),
   actionOrder:[...actions.children].map(a=>a.textContent.trim()),
   bar:rect(bar),overflow:document.documentElement.scrollWidth>innerWidth};
})();"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text()
    driver.js("""const style=document.createElement('style');style.id='top-bar-theme';
      document.head.append(style);return true;""")

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        driver.js("document.querySelector('#top-bar-theme').textContent=arguments[0]", [baseline])
        time.sleep(0.08)
        before = driver.js(measure)
        (OUT / f"baseline-{width}.png").write_bytes(
            base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")),
        )
        driver.js("document.querySelector('#top-bar-theme').textContent=arguments[0]", [candidate])
        time.sleep(0.08)
        after = driver.js(measure)
        (OUT / f"candidate-{width}.png").write_bytes(
            base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")),
        )

        mobile = width < 768
        record(f"{width}px viewport", actual_width == width, {"measured": actual_width})
        record(f"{width}px layout", after["columns"] == (1 if mobile else 3)
               and after["overflow"] is False,
               {"baselineColumns": before["columns"], "candidateColumns": after["columns"],
                "documentWidth": after["documentWidth"], "overflow": after["overflow"]})
        record(f"{width}px DOM/action order", after["regionOrder"] == before["regionOrder"]
               and after["actionOrder"] == before["actionOrder"]
               and (not mobile or [item["top"] for item in after["regions"]]
                    == sorted(item["top"] for item in after["regions"])))
        if not mobile:
            record(f"{width}px desktop parity", after["columns"] == before["columns"]
                   and after["align"] == before["align"]
                   and after["gap"] == before["gap"]
                   and after["contextJustify"] == before["contextJustify"]
                   and after["contextMinWidth"] == before["contextMinWidth"]
                   and after["contextChild"] == before["contextChild"]
                   and after["actionsJustify"] == before["actionsJustify"]
                   and after["actionsWrap"] == before["actionsWrap"]
                   and after["actionsMinWidth"] == before["actionsMinWidth"]
                   and after["actionsMaxWidth"] == before["actionsMaxWidth"]
                   and after["actionsGap"] == before["actionsGap"],
                   {"baseline": before, "candidate": after})

    (OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
finally:
    viewport.close()
    driver.close()

failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
