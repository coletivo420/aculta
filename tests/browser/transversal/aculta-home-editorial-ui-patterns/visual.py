#!/usr/bin/env python3
"""Compare the Home editorial highlights before and after the UI Patterns grid."""

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
    raise SystemExit("Defina ACULTA_BASE para a origem MAIN de desenvolvimento.")
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute em Firefox ESR visível com DISPLAY=:0.")
if PHASE not in {"before", "after"}:
    raise SystemExit("PHASE deve ser before ou after.")
OUT.mkdir(parents=True, exist_ok=True)

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
    bidi("browsingContext.setViewport", {"context": context, "viewport": {"width": width, "height": 900}})
    driver.get(f"{BASE.rstrip('/')}/")
    for _ in range(100):
        if driver.js('return document.readyState === "complete" && Boolean(document.querySelector("#block-aculta420-v2-aculta-home-editorial-highlights"))'):
            break
        time.sleep(0.1)
    driver.js(f'document.documentElement.setAttribute("data-theme", "{mode}"); return true')
    metrics = driver.js('''
      const block = document.querySelector('#block-aculta420-v2-aculta-home-editorial-highlights');
      const cards = [...(block?.querySelectorAll('article[data-history-node-id]') || [])];
      const links = cards.map(card => card.querySelector('h2 a'));
      const grid = block?.querySelector('.layout-grid');
      const carousel = block?.querySelector('vvjb-carousel');
      const backgroundRgb = element => {
        let current = element;
        while (current) {
          const value = getComputedStyle(current).backgroundColor;
          const parts = value.match(/[\\d.]+/g)?.map(Number);
          if (parts?.length >= 3 && (parts.length < 4 || parts[3] > 0)) return parts.slice(0, 3);
          current = current.parentElement;
        }
        return [255, 255, 255];
      };
      const linear = channel => { channel /= 255; return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4; };
      const luminance = rgb => .2126 * linear(rgb[0]) + .7152 * linear(rgb[1]) + .0722 * linear(rgb[2]);
      const contrast = link => {
        const fg = getComputedStyle(link).color.match(/[\\d.]+/g)?.map(Number) || [0, 0, 0];
        const l1 = luminance(fg), l2 = luminance(backgroundRgb(link));
        return (Math.max(l1, l2) + .05) / (Math.min(l1, l2) + .05);
      };
      const rects = cards.map(card => { const r = card.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; });
      return {
        location: location.href,
        width: innerWidth,
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        main: document.querySelectorAll('main').length,
        h1: document.querySelectorAll('main h1').length,
        cards: cards.length,
        ids: cards.map(card => card.getAttribute('data-history-node-id')),
        titles: links.map(link => link?.textContent.trim()),
        carousel: Boolean(carousel),
        grid: Boolean(grid),
        gridDisplay: grid ? getComputedStyle(grid).display : null,
        contrast: links.filter(Boolean).map(contrast),
        rects
      };
    ''')
    focus_visible = False
    for index in range(90):
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": f"home-editorial-{index}", "actions": [{"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"}]}]})
        focus_visible = driver.js('return Boolean(document.activeElement?.closest("#block-aculta420-v2-aculta-home-editorial-highlights") && document.activeElement.matches(":focus-visible"))')
        if focus_visible:
            break
    metrics["focus_visible"] = focus_visible
    driver.js("document.querySelector('#block-aculta420-v2-aculta-home-editorial-highlights')?.scrollIntoView({block:'center'}); return true")
    expected_presentation = metrics["carousel"] if PHASE == "before" else (metrics["grid"] and not metrics["carousel"] and metrics["gridDisplay"] == "grid")
    passed = (
        metrics["overflow"] == 0
        and metrics["main"] == 1
        and metrics["h1"] == 1
        and metrics["cards"] == 3
        and metrics["ids"] == ["14", "15", "16"]
        and expected_presentation
        and metrics["focus_visible"]
        and len(metrics["contrast"]) == 3
        and min(metrics["contrast"]) >= 4.5
    )
    result = {"phase": PHASE, "mode": mode, "viewport_css_px": width, "passed": passed, **metrics}
    print(f"{'PASS' if passed else 'FAIL'} {PHASE} {mode} {width}px: {json.dumps(metrics, ensure_ascii=False)}", flush=True)
    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{PHASE}-{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))
    return result


try:
    results = [capture(mode, width) for mode in ("light", "dark") for width in (320, 390, 768, 1024, 1440)]
    (OUT / "result.json").write_text(json.dumps(results, ensure_ascii=False, indent=2))
    if not all(item["passed"] for item in results):
        raise SystemExit(1)
finally:
    socket.close()
    driver.close()
