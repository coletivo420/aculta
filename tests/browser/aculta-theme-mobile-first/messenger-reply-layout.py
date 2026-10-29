#!/usr/bin/env python3
"""Compare mobile-first Messenger drawer reply composer CSS in visible Firefox."""

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

CSS_PATH = "web/themes/custom/aculta420/css/components/messenger-drawer.css"
BASELINE_REF = os.environ.get("MESSENGER_REPLY_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "MESSENGER_REPLY_WIDTHS", "320,360,390,575,576,599,600,768,1024,1280,1440",
).split(",")]
OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    suffix = f" — {json.dumps(detail, ensure_ascii=False)}" if detail is not None else ""
    print(f"{'PASS' if passed else 'FAIL'} {name}{suffix}", flush=True)


def source_css(ref):
    return subprocess.run(
        ["git", "show", f"{ref}:{CSS_PATH}"], cwd=ROOT, capture_output=True,
        text=True, check=True,
    ).stdout


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
    raise SystemExit("Execute com Firefox ESR visível e DISPLAY=:0.")

fixture = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<style>
:root{--aculta-border-subtle:#777;--aculta-surface-raised:#fff}
*{box-sizing:border-box}body{margin:0;font:16px sans-serif}
.aculta-messenger-offcanvas{width:100%;height:100vh}
.aculta-utility-offcanvas__body{display:flex;flex-direction:column;width:100%;height:100%;}
.aculta-messenger-drawer-conversation{display:flex;flex-direction:column;min-width:0;width:100%;}
form{margin:0}textarea{display:block;width:100%;height:auto;min-height:2.75rem;padding:.375rem .75rem;
font:inherit;line-height:1.5;border:1px solid #777;border-radius:4px;resize:vertical}
.form-actions{display:flex;justify-content:flex-end}button{min-height:2.75rem}
</style></head><body>
<aside class="aculta-messenger-offcanvas"><div class="aculta-utility-offcanvas__body aculta-messenger-drawer-content--conversation">
<main class="aculta-messenger-drawer-conversation">
<div class="aculta-messenger-conversation-toolbar"><a class="aculta-messenger-back-link" href="#list">← Todas as conversas</a></div>
<div class="private-message-thread-full"><div class="field--name-private-messages">Histórico da conversa</div>
<form data-aculta-reply-form><div class="field--name-message"><label for="reply">Mensagem</label><textarea id="reply" rows="4" class="form-control"></textarea></div>
<div class="form-actions"><button type="button">Responder</button></div></form></div>
</main></div></aside></body></html>"""

measure = """return (() => {
  const body=document.querySelector('.aculta-messenger-drawer-content--conversation');
  const form=document.querySelector('[data-aculta-reply-form]');
  const field=form.querySelector('.field--name-message');
  const textarea=form.querySelector('textarea');
  const action=form.querySelector('button');
  const px=(value)=>Math.round(parseFloat(value)*100)/100;
  const bodyStyle=getComputedStyle(body),formStyle=getComputedStyle(form);
  const fieldStyle=getComputedStyle(field),textareaStyle=getComputedStyle(textarea);
  return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
    bodyPadding:bodyStyle.padding,formMarginTop:formStyle.marginBlockStart,
    formPadding:formStyle.padding,fieldMarginBottom:fieldStyle.marginBlockEnd,
    textareaHeight:px(textarea.getBoundingClientRect().height),textareaMinHeight:textareaStyle.minHeight,
    domOrder:textarea.compareDocumentPosition(action)&Node.DOCUMENT_POSITION_FOLLOWING ? 'message-before-action':'action-before-message',
    hasForm:form.matches('form[data-aculta-reply-form]'),buttonHeight:px(action.getBoundingClientRect().height)};
})();"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text()
    driver.js("""const style=document.createElement('style');style.id='messenger-css';
      style.textContent=arguments[0];document.head.append(style);return true;""", [baseline])

    for width in WIDTHS:
        actual = viewport.set(driver, width)
        baseline_metrics = driver.js(measure)
        baseline_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"baseline-{width}.png").write_bytes(baseline_png)
        driver.js("document.querySelector('#messenger-css').textContent=arguments[0]", [candidate])
        candidate_metrics = driver.js(measure)
        screenshot = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"candidate-{width}.png").write_bytes(screenshot)
        compact = width <= 575
        expected = {
            "bodyPadding": "5.6px" if compact else "8px",
            "formMarginTop": "5.6px" if compact else "8px",
            "formPadding": "8.8px 2.4px 4px" if compact else "12px 4px 8px",
            "fieldMarginBottom": "4px" if compact else "0px",
            "textareaMinHeight": "88px" if compact else "44px",
        }
        actual_expected = {key: candidate_metrics[key] for key in expected}
        record(f"{width}px: Firefox entrega o viewport solicitado", actual == width, {"actual": actual})
        record(f"{width}px: composição usa medidas {('compactas' if compact else 'amplas')} esperadas",
               actual_expected == expected, {"expected": expected, "actual": actual_expected})
        record(f"{width}px: candidato preserva o resultado visual da baseline",
               all(candidate_metrics[key] == baseline_metrics[key] for key in baseline_metrics if key != "width"),
               {"baseline": baseline_metrics, "candidate": candidate_metrics})
        record(f"{width}px: formulário, ordem e alvos continuam utilizáveis",
               candidate_metrics["hasForm"] and candidate_metrics["domOrder"] == "message-before-action"
               and candidate_metrics["buttonHeight"] >= 44,
               {key: candidate_metrics[key] for key in ("hasForm", "domOrder", "buttonHeight")})
        record(f"{width}px: sem overflow horizontal", candidate_metrics["documentWidth"] <= width,
               {"documentWidth": candidate_metrics["documentWidth"]})
        record(f"{width}px: captura baseline/candidata gravada",
               len(screenshot) > 0 and len(baseline_png) > 0,
               {"candidateSha256": hashlib.sha256(screenshot).hexdigest()})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
