#!/usr/bin/env python3
"""Verify the Revista news View style using its existing revista-listing SDC."""

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
PHASE = os.environ.get("PHASE", "after")
if not BASE:
    raise SystemExit("Defina ACULTA_BASE para a origem HTTPS do Runtime de desenvolvimento.")
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute em Firefox ESR visível com DISPLAY=:0.")
if PHASE not in {"before", "after"}:
    raise SystemExit("PHASE deve ser before ou after.")
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


def capture(mode, width):
    context = bidi("browsingContext.getTree")["contexts"][0]["context"]
    bidi("browsingContext.setViewport", {"context": context, "viewport": {"width": width, "height": 1000}})
    driver.get(f"{BASE.rstrip('/')}/noticias")
    for _ in range(100):
        ready = driver.js('return document.readyState === "complete" && Boolean(document.querySelector("#block-aculta420-v2-aculta-news"))')
        if ready:
            break
        time.sleep(0.1)
    driver.js(f'document.documentElement.setAttribute("data-theme", "{mode}"); return true')
    metrics = driver.js('''
      const block=document.querySelector('#block-aculta420-v2-aculta-news');
      const section=block.querySelector('.revista-listing');
      const items=block.querySelector('.revista-listing__items');
      const rows=section?[...section.querySelectorAll('.node--view-mode-teaser')]:[...block.querySelectorAll('.views-row')];
      const first=rows[0];
      const card=first?.querySelector('.revista-teaser')||first?.querySelector('article');
      const title=card?.querySelector('.revista-teaser__title a, h2 a, h3 a');
      const rgb=x=>{const m=x.match(/[\\d.]+/g);return m&&m.length>=3?m.slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4}):[0,0,0]};
      const lum=c=>.2126*c[0]+.7152*c[1]+.0722*c[2];
      const fg=title?rgb(getComputedStyle(title).color):[0,0,0];
      let bg=[1,1,1];let bgNode=card;while(bgNode){const value=getComputedStyle(bgNode).backgroundColor;if(value&&value!=='rgba(0, 0, 0, 0)'&&value!=='transparent'){bg=rgb(value);break}bgNode=bgNode.parentElement}
      const l1=lum(fg),l2=lum(bg);
      const grid=items?getComputedStyle(items):null;
      const cols=grid?grid.gridTemplateColumns.split(' ').length:0;
      const firstStyle=first?getComputedStyle(first):null;
      const visibleH1=[...document.querySelectorAll('main h1')].filter(el=>{const style=getComputedStyle(el);return style.display!=='none'&&style.visibility!=='hidden'&&el.getClientRects().length>0}).length;
      return {
        width:innerWidth,
        overflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,
        h1:document.querySelectorAll('main h1').length,
        visible_h1:visibleH1,
        main:document.querySelectorAll('main').length,
        rows:rows.length,
        listing:Boolean(section),
        listing_title:section?.querySelector('.revista-listing__title')?.textContent.trim()||null,
        columns:cols,
        first_column:firstStyle?.gridColumn||null,
        first_title:title?.textContent.trim()||null,
        text_color:title?getComputedStyle(title).color:null,
        background:card?getComputedStyle(card).backgroundColor:null,
        background_luminance:l2,
        contrast_ratio:(Math.max(l1,l2)+.05)/(Math.min(l1,l2)+.05),
        focus_visible:Boolean(document.activeElement?.closest('#block-aculta420-v2-aculta-news')&&document.activeElement.matches(':focus-visible'))
      };
    ''')
    focused = False
    for index in range(90):
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": f"revista-news-{index}", "actions": [{"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"}]}]})
        focused = driver.js('return Boolean(document.activeElement?.closest("#block-aculta420-v2-aculta-news") && document.activeElement.matches(":focus-visible"))')
        if focused:
            break
    metrics["focus_visible"] = focused
    driver.js("window.scrollTo(0, 0); return true")
    ok = (
        metrics["width"] == width
        and metrics["overflow"] == 0
        and metrics["h1"] == 1
        and metrics["visible_h1"] == 1
        and metrics["main"] == 1
        and metrics["rows"] == 4
        and metrics["contrast_ratio"] >= 4.5
        and metrics["focus_visible"]
        and (PHASE == "before" or metrics["listing"])
        and (PHASE == "before" or metrics["columns"] == (1 if width < 600 else 2 if width < 992 else 3))
        and (PHASE == "before" or (width >= 992 and metrics["first_column"] == "1 / -1") or width < 992)
    )
    item = {"phase": PHASE, "mode": mode, "viewport_css_px": width, "passed": ok, **metrics}
    results.append(item)
    print(f"{'PASS' if ok else 'FAIL'} {PHASE} {mode} {width}px: {json.dumps(metrics, ensure_ascii=False)}", flush=True)
    shot = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{PHASE}-{mode}-{width}.png").write_bytes(base64.b64decode(shot))


try:
    for mode in ("light", "dark"):
        for width in (320, 390, 768, 1024, 1440):
            capture(mode, width)
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2))
    if not all(item["passed"] for item in results):
        raise SystemExit(1)
finally:
    socket.close()
    driver.close()
