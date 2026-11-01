#!/usr/bin/env python3
"""Compare institutional layout before/after its mobile-first CSS migration."""

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
CSS_PATH = "web/themes/custom/aculta420/css/components/institutional.css"
BASELINE_REF = os.environ.get("INSTITUTIONAL_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "INSTITUTIONAL_LAYOUT_WIDTHS",
    "320,360,390,575,576,599,600,767,768,1024,1280,1440",
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
<style>:root{--aculta-space-section:1.5rem;--aculta-interactive-text:#fff;
--aculta-interactive-bg:#145c39;--aculta-interactive-border:#145c39;
--aculta-text-primary:#111;--aculta-border-strong:#333;--aculta-border-default:#777;
--aculta-border-accent:#145c39;--aculta-surface-raised:#fff;--bs-border-radius:4px;
--aculta-interactive-active-bg:#145c39;--aculta-interactive-hover-text:#fff;
--aculta-interactive-hover-bg:#1d382b;--aculta-motion-fast:0ms;
--aculta-ease-standard:linear}body{margin:0;font:16px sans-serif}.btn{display:inline-block;
padding:8px 12px;text-align:center}.fixture{box-sizing:border-box;width:100%;padding:16px}
.aculta-areas>section{min-height:60px}.aculta-institution-details{width:100%}</style></head>
<body><main class="aculta-main fixture">
<div class="aculta-actions"><a class="btn" href="#one">Ação principal</a><a class="btn" href="#two">Ação secundária</a></div>
<div class="aculta-areas"><section>Área um</section><section>Área dois</section></div>
<dl class="aculta-institution-details"><div><dt>Natureza jurídica</dt><dd>Associação cultural sem fins lucrativos</dd></div>
<div><dt>Endereço</dt><dd>Rua de exemplo, 123, bairro central, município</dd></div></dl>
</main></body></html>"""
fixture = fixture.replace(
    "</style>",
    (ROOT / "web/themes/custom/aculta420/components/patterns/content-grid/content-grid.css").read_text()
    + "</style>",
)
fixture = fixture.replace(
    '<dl class="aculta-institution-details">',
    '<div class="aculta-project-list"><div class="view-content aculta-content-grid aculta-content-grid--2">'
    '<section>Projeto um</section><section>Projeto dois</section></div></div>'
    '<dl class="aculta-institution-details">',
)

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
        baseline_metrics = driver.js("""const actions=document.querySelector('.aculta-actions');
          const button=actions.querySelector('.btn');const areas=document.querySelector('.aculta-areas');
          const projects=document.querySelector('.aculta-project-list .view-content');
          const details=document.querySelector('.aculta-institution-details > div');
          const a=getComputedStyle(actions),b=getComputedStyle(button),c=getComputedStyle(areas),p=getComputedStyle(projects),d=getComputedStyle(details);
          return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
            actions:{direction:a.flexDirection,alignItems:a.alignItems,gap:a.gap,buttonText:b.textAlign},
            areas:{columns:c.gridTemplateColumns,gap:c.gap},
            projects:{columns:p.gridTemplateColumns,gap:p.gap},
            details:{columns:d.gridTemplateColumns,gap:d.gap}};""")
        baseline_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"baseline-{width}.png").write_bytes(baseline_png)
        driver.js("document.querySelector('#theme-candidate').textContent=arguments[0]", [candidate])
        candidate_metrics = driver.js("""const actions=document.querySelector('.aculta-actions');
          const button=actions.querySelector('.btn');const areas=document.querySelector('.aculta-areas');
          const projects=document.querySelector('.aculta-project-list .view-content');
          const details=document.querySelector('.aculta-institution-details > div');
          const a=getComputedStyle(actions),b=getComputedStyle(button),c=getComputedStyle(areas),p=getComputedStyle(projects),d=getComputedStyle(details);
          return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
            actions:{direction:a.flexDirection,alignItems:a.alignItems,gap:a.gap,buttonText:b.textAlign},
            areas:{columns:c.gridTemplateColumns,gap:c.gap},
            projects:{columns:p.gridTemplateColumns,gap:p.gap},
            details:{columns:d.gridTemplateColumns,gap:d.gap}};""")
        candidate_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"candidate-{width}.png").write_bytes(candidate_png)
        no_overflow = baseline_metrics["documentWidth"] <= width and candidate_metrics["documentWidth"] <= width
        equal_layout = baseline_metrics == candidate_metrics
        mobile_expectation = True
        if width < 576:
            mobile_expectation = candidate_metrics["actions"]["direction"] == "column"
        elif width >= 576:
            mobile_expectation = candidate_metrics["actions"]["direction"] == "row"
        if width < 768:
            mobile_expectation = mobile_expectation and len(candidate_metrics["areas"]["columns"].split()) == 1
            mobile_expectation = mobile_expectation and len(candidate_metrics["details"]["columns"].split()) == 1
            mobile_expectation = mobile_expectation and len(candidate_metrics["projects"]["columns"].split()) == 1
        else:
            mobile_expectation = mobile_expectation and len(candidate_metrics["areas"]["columns"].split()) == 2
            mobile_expectation = mobile_expectation and len(candidate_metrics["details"]["columns"].split()) == 2
            mobile_expectation = mobile_expectation and len(candidate_metrics["projects"]["columns"].split()) == 2
        same_pixels = hashlib.sha256(baseline_png).hexdigest() == hashlib.sha256(candidate_png).hexdigest()
        record(f"{width}px: geometria computada preservada", actual_width == width and equal_layout and no_overflow,
               {"documentWidth": candidate_metrics["documentWidth"], "actions": candidate_metrics["actions"],
                "areaColumns": candidate_metrics["areas"]["columns"],
                "projectColumns": candidate_metrics["projects"]["columns"],
                "detailColumns": candidate_metrics["details"]["columns"]})
        record(f"{width}px: mobile-first esperado e captura idêntica", mobile_expectation and same_pixels,
               {"expectedLayout": mobile_expectation, "sameScreenshot": same_pixels})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
