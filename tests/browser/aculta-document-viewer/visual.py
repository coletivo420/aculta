#!/usr/bin/env python3
"""Visual smoke for the shared PDF Media formatter on Firefox ESR."""

import base64
import json
import os
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3]))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

URL = os.environ["ACULTA_TEST_URL"]
EXPECTED_TITLE = os.environ.get("ACULTA_EXPECTED_TITLE", "")
OUT = Path(os.environ["SUITE_OUT"])
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
results = []


def check(name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if not OUT.is_absolute():
    raise SystemExit("SUITE_OUT deve ser um caminho absoluto fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)

driver = None
bidi = None
bidi_id = 0


def wait_for(script, timeout=35):
    end = time.monotonic() + timeout
    while time.monotonic() < end:
        try:
            if driver.js("return " + script):
                return True
        except Exception:
            pass
        time.sleep(0.25)
    return False


def set_width(width):
    global bidi_id
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({"id": command_id, "method": "browsingContext.getTree", "params": {}}))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    context = response["result"]["contexts"][0]["context"]
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({
        "id": command_id,
        "method": "browsingContext.setViewport",
        "params": {"context": context, "viewport": {"width": width, "height": 900}},
    }))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    time.sleep(0.6)
    return int(driver.js("return window.innerWidth"))


def screenshot(name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"document-viewer-{name}.png").write_bytes(base64.b64decode(data))


def press_tab():
    key = "\ue004"
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "document-viewer-keyboard",
            "actions": [{"type": "keyDown", "value": key}, {"type": "keyUp", "value": key}],
        }],
    })


try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)
    driver.get(URL)
    loaded = wait_for("document.readyState === 'complete' && !!document.querySelector('[data-aculta-pdf-viewer=\"true\"]')")
    check("página incorpora o viewer PDF", loaded)
    title = driver.js("return document.querySelector('main h1')?.innerText.trim() || ''") if loaded else ""
    check("título da página de teste está visível", bool(title) and (not EXPECTED_TITLE or title == EXPECTED_TITLE), title)

    viewer = driver.js("""const viewer = document.querySelector('[data-aculta-pdf-viewer="true"]');
      return {
        title: document.querySelector('main h1')?.innerText || '',
        controls: viewer ? [...viewer.querySelectorAll('[data-aculta-pdf-control]')]
          .map(control => control.getAttribute('data-aculta-pdf-control')) : [],
        canvas: Boolean(viewer?.querySelector('canvas')),
      };""") if loaded else {"title": "", "controls": [], "canvas": False}
    expected = {"page", "zoom-in", "zoom-reset", "search-input", "fullscreen"}
    check("controles de página, zoom, busca e tela cheia estão presentes",
          expected.issubset(set(viewer["controls"])), str(viewer["controls"]))
    check("canvas do documento renderizado", viewer["canvas"])

    viewer_controls = driver.js("""const viewer = document.querySelector('[data-aculta-pdf-viewer="true"]');
      return viewer ? [...viewer.querySelectorAll('button:not([disabled]), a[href], input:not([disabled])')]
        .filter(element => element.getClientRects().length).length : 0;""")
    focused = 0
    focus_visible = False
    for _ in range(max(40, viewer_controls * 4)):
        press_tab()
        active = driver.js("""const viewer = document.querySelector('[data-aculta-pdf-viewer="true"]');
          const element = document.activeElement;
          return viewer?.contains(element) ? {visible: element.matches(':focus-visible')} : null;""")
        if active:
            focused += 1
            focus_visible = focus_visible or active["visible"]
        if focused >= viewer_controls:
            break
    check("controles do viewer participam do teclado com foco visível",
          viewer_controls > 0 and focused > 0 and focus_visible,
          f"controles={viewer_controls}, focos no viewer={focused}, focus-visible={focus_visible}")

    for width, label in WIDTHS:
        actual = set_width(width)
        driver.js("window.scrollTo(0, 0)")
        time.sleep(0.25)
        metrics = driver.js("""const viewer = document.querySelector('[data-aculta-pdf-viewer="true"]');
          const rect = viewer?.getBoundingClientRect();
          return {
            viewport: window.innerWidth,
            document: document.documentElement.scrollWidth,
            viewerVisible: Boolean(rect && rect.width > 0 && rect.left >= 0 && rect.right <= window.innerWidth),
            canvasVisible: Boolean(viewer?.querySelector('canvas')?.getClientRects().length),
          };""")
        screenshot(label)
        check(f"viewer sem overflow em {width}px",
              actual == width and metrics["document"] <= width and metrics["viewerVisible"]
              and metrics["canvasVisible"], str(metrics))
finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
