#!/usr/bin/env python3
"""Verify the UI Styles Block integration on the public transparency page."""

import base64
import json
import os
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[4]
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("ACULTA_BASE")
OUT = Path(os.environ["SUITE_OUT"])
if not BASE:
    raise SystemExit("Defina ACULTA_BASE para o host do Runtime de desenvolvimento.")
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute em Firefox ESR visível com DISPLAY=:0.")
OUT.mkdir(parents=True, exist_ok=True)
results = []
driver = Driver(bidi=True)
socket = connect(driver.websocket_url)
command_id = 0


def bidi(method, params=None):
    global command_id
    command_id += 1
    socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
    while True:
        message = json.loads(socket.recv())
        if message.get("id") == command_id:
            if "error" in message:
                raise RuntimeError(f"WebDriver BiDi {method}: {message['error']}")
            return message.get("result", {})


def record(mode, width, metrics):
    required_classes = {"aculta-surface", "aculta-surface--tone-raised", "aculta-surface--spacing-normal"}
    classes = set(metrics["classes"].split())
    passed = (
        metrics["width"] == width
        and metrics["overflow"] == 0
        and metrics["h1"] == 1
        and metrics["main"] == 1
        and metrics["rows"] == 3
        and required_classes <= classes
        and ((mode == "light" and metrics["background_luminance"] > 0.8)
             or (mode == "dark" and metrics["background_luminance"] < 0.2))
        and metrics["contrast_ratio"] >= 4.5
        and metrics["padding"] == ("16px" if width < 768 else "24px")
        and metrics["focus_visible"]
    )
    result = {"mode": mode, "viewport_css_px": width, "passed": passed, **metrics}
    results.append(result)
    print(f"{'PASS' if passed else 'FAIL'} {mode} {width}px: {json.dumps(metrics, ensure_ascii=False)}", flush=True)
    png = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{mode}-{width}.png").write_bytes(base64.b64decode(png))


try:
    context = bidi("browsingContext.getTree")["contexts"][0]["context"]
    for mode in ("light", "dark"):
        for width in (320, 390, 768, 1024, 1440):
            bidi("browsingContext.setViewport", {"context": context, "viewport": {"width": width, "height": 950}})
            driver.get(f"{BASE.rstrip('/')}/transparencia")
            for _ in range(100):
                if driver.js('return document.readyState === "complete" && Boolean(document.querySelector("main #block-aculta420-v2-aculta-documents"))'):
                    break
                time.sleep(.1)
            driver.js(f'document.documentElement.setAttribute("data-theme", "{mode}"); return true')
            focused = False
            for _ in range(45):
                driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "ui-styles-tab", "actions": [{"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"}]}]})
                if driver.js('return Boolean(document.activeElement?.closest("main") && document.activeElement.matches(":focus-visible"))'):
                    focused = True
                    break
            metrics = driver.js('''const b=document.querySelector('#block-aculta420-v2-aculta-documents');const s=getComputedStyle(b);const rgb=x=>x.match(/[\\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});const fg=rgb(s.color),bg=rgb(s.backgroundColor);const lum=c=>.2126*c[0]+.7152*c[1]+.0722*c[2];const l1=lum(fg),l2=lum(bg);return {width:innerWidth,overflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,classes:b.className,background:s.backgroundColor,background_luminance:l2,color:s.color,contrast_ratio:(Math.max(l1,l2)+.05)/(Math.min(l1,l2)+.05),padding:s.padding,rows:b.querySelectorAll('.views-row').length,h1:document.querySelectorAll('main h1').length,main:document.querySelectorAll('main').length,focus_visible:arguments[0]};''', [focused])
            record(mode, width, metrics)
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2))
    if not all(item["passed"] for item in results):
        raise SystemExit(1)
finally:
    socket.close()
    driver.close()
