#!/usr/bin/env python3
"""Compare the editorial photo gallery before and after the SDC grid cutover."""

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
GALLERY_PATH = os.environ.get("GALLERY_PATH")
OUT = Path(os.environ["SUITE_OUT"])
PHASE = os.environ.get("PHASE", "after")
if not BASE or not GALLERY_PATH:
    raise SystemExit("Defina ACULTA_BASE e GALLERY_PATH para a Revista de desenvolvimento.")
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
    driver.get(f"{BASE.rstrip('/')}/{GALLERY_PATH.lstrip('/')}")
    for _ in range(150):
        if driver.js('return document.readyState === "complete" && document.querySelectorAll(".photoswipe-gallery a.photoswipe").length >= 63'):
            break
        time.sleep(0.1)
    driver.js(f'document.documentElement.setAttribute("data-theme", "{mode}"); return true')
    metrics = driver.js('''
      const root = document.querySelector('.photoswipe-gallery');
      const links = [...(root?.querySelectorAll('a.photoswipe') || [])];
      const images = links.map(link => link.querySelector('img'));
      const grid = root?.querySelector('.layout-grid');
      const carousel = root?.querySelector('vvjs-slideshow');
      const thumbs = root?.querySelector('[data-aculta-album-thumbs]');
      const rects = images.map(image => { const r = image.getBoundingClientRect(); return {x:r.x,y:r.y,width:r.width,height:r.height}; });
      const columns = new Set(rects.filter(rect => rect.width > 0).map(rect => Math.round(rect.x)));
      return {
        location: location.href,
        width: innerWidth,
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        main: document.querySelectorAll('main').length,
        h1: document.querySelectorAll('main h1').length,
        photoswipe_links: links.length,
        images: images.length,
        missing_alt: images.filter(image => image && !image.hasAttribute('alt')).length,
        image_loading: [...new Set(images.map(image => image?.getAttribute('loading') || 'none'))],
        grid: Boolean(grid),
        grid_display: grid ? getComputedStyle(grid).display : null,
        carousel: Boolean(carousel),
        thumbnails: thumbs ? thumbs.querySelectorAll('[data-aculta-album-thumb]').length : 0,
        columns: columns.size,
        min_image_width: rects.length ? Math.min(...rects.map(rect => rect.width)) : 0,
        max_image_width: rects.length ? Math.max(...rects.map(rect => rect.width)) : 0
      };
    ''')
    driver.js("document.querySelector('.photoswipe-gallery')?.scrollIntoView({block:'center'}); return true")
    focus_visible = False
    for index in range(150):
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": f"gallery-{index}", "actions": [{"type": "keyDown", "value": "\ue004"}, {"type": "keyUp", "value": "\ue004"}]}]})
        focus_visible = driver.js('return Boolean(document.activeElement?.closest(".photoswipe-gallery") && document.activeElement.matches(":focus-visible"))')
        if focus_visible:
            break
    metrics["focus_visible"] = focus_visible
    expected = (
        metrics["carousel"] and metrics["thumbnails"] == 63 and not metrics["grid"]
        if PHASE == "before"
        else metrics["grid"] and metrics["grid_display"] == "grid" and not metrics["carousel"] and metrics["thumbnails"] == 0
    )
    passed = (
        metrics["overflow"] == 0
        and metrics["main"] == 1
        and metrics["h1"] == 1
        and metrics["photoswipe_links"] == 63
        and metrics["images"] == 63
        and metrics["missing_alt"] == 0
        and "lazy" in metrics["image_loading"]
        and expected
        and metrics["focus_visible"]
    )
    result = {"phase": PHASE, "mode": mode, "viewport_css_px": width, "passed": passed, **metrics}
    print(f"{'PASS' if passed else 'FAIL'} {PHASE} {mode} {width}px: {json.dumps(metrics, ensure_ascii=False)}", flush=True)
    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{PHASE}-{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))
    return result


try:
    results = [capture(mode, width) for mode in ("light", "dark") for width in (320, 390, 768, 1024, 1440)]
    if PHASE == "after":
        driver.get(f"{BASE.rstrip('/')}/{GALLERY_PATH.lstrip('/')}")
        for _ in range(150):
            if driver.js('return document.querySelectorAll(".photoswipe-gallery a.photoswipe").length === 63 && document.querySelector(".photoswipe-gallery")?.getAttribute("data-once")?.includes("photoswipe")'):
                break
            time.sleep(0.1)
        driver.js("document.querySelector('.photoswipe-gallery a.photoswipe')?.click(); return true")
        for _ in range(60):
            if driver.js('return Boolean(document.querySelector(".pswp--open"))'):
                break
            time.sleep(0.1)
        opened = driver.js('return Boolean(document.querySelector(".pswp--open"))')
        if opened:
            time.sleep(1)
            driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "gallery-escape", "actions": [{"type": "keyDown", "value": "\ue00c"}, {"type": "keyUp", "value": "\ue00c"}]}]})
            for _ in range(50):
                if not driver.js('return Boolean(document.querySelector(".pswp--open"))'):
                    break
                time.sleep(0.1)
        closed = not driver.js('return Boolean(document.querySelector(".pswp--open"))')
        results.append({"phase": PHASE, "photoswipe_keyboard_open": opened, "photoswipe_escape_close": closed, "passed": opened and closed})
        print(f"{'PASS' if opened and closed else 'FAIL'} PhotoSwipe keyboard open/close", flush=True)
    (OUT / "result.json").write_text(json.dumps(results, ensure_ascii=False, indent=2))
    if not all(item["passed"] for item in results):
        raise SystemExit(1)
finally:
    socket.close()
    driver.close()
