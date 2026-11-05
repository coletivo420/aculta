#!/usr/bin/env python3
"""Capture the ACULTA420 V2 footer and shell regions in visible Firefox.

Reads the development server home (*.aculta.toca.net.br). Does not authenticate
and does not write to the Runtime.
"""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

HOST = os.environ.get("ACULTA_DEV_HOST", "aculta.toca.net.br")
BASE = f"https://{HOST}"
WIDTHS = (320, 390, 1440)


def set_viewport(driver, width, height=900):
    node = r'''const ws=new WebSocket(process.argv[1]);ws.onopen=()=>ws.send(JSON.stringify({id:1,method:'browsingContext.getTree',params:{}}));ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id===1)ws.send(JSON.stringify({id:2,method:'browsingContext.setViewport',params:{context:m.result.contexts[0].context,viewport:{width:Number(process.argv[2]),height:Number(process.argv[3])}}}));else if(m.id===2){if(m.error){console.error(m.error.message);process.exitCode=1;}ws.close();}};'''
    subprocess.run(["node", "-e", node, driver.websocket_url, str(width), str(height)], check=True)


def inspect(driver):
    return driver.js("""return (()=>{const d=document.documentElement;
      const footer=document.querySelector('.site-footer, footer');
      const body=document.body;
      return {width:innerWidth,documentWidth:d.scrollWidth,theme:d.getAttribute('data-theme'),
        institution:!!document.querySelector('.aculta-institution-bar'),
        domainHeader:!!document.querySelector('.page__header, header[role="banner"]'),
        footer:!!footer,
        footerText:(footer?.innerText||'').trim().slice(0,120),
        highlighted:!!document.querySelector('.page__highlighted'),
        help:!!document.querySelector('.page__help'),
        breadcrumb:!!document.querySelector('.page__breadcrumb'),
        sidebar:!!document.querySelector('.page__sidebar'),
        bodyText:body.innerText.trim().length};})();""")


records = []
driver = Driver(bidi=True)
try:
    driver.get(f"{BASE}/")
    time.sleep(0.5)
    for mode in ("light", "dark"):
        driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
        for width in WIDTHS:
            set_viewport(driver, width)
            time.sleep(0.2)
            metrics = inspect(driver)
            metrics.update(mode=mode, passed=(
                metrics["institution"] and metrics["domainHeader"]
                and metrics["footer"] and len(metrics["footerText"]) > 0
                and metrics["documentWidth"] <= width
            ))
            records.append(metrics)
            print(f"{'PASS' if metrics['passed'] else 'FAIL'} {mode} {width}px", flush=True)
            (OUT / f"{mode}-{width}.png").write_bytes(base64.b64decode(
                driver.req("GET", f"/session/{driver.session}/screenshot")
            ))
finally:
    driver.close()

(OUT / "results.json").write_text(json.dumps({"host": HOST, "matrix": records}, ensure_ascii=False, indent=2))
if not all(item["passed"] for item in records):
    raise SystemExit("Falha na captura do rodapé/regions do shell.")
print("ACULTA420 V2 FOOTER: PASS", flush=True)
