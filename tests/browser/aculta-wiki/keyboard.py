#!/usr/bin/env python3
"""Read-only keyboard smoke test for public ACULTA Wiki pages."""

import base64
import json
import os
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("WIKI_BASE_URL", "https://wiki420.aculta.toca.net.br").rstrip("/")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-wiki-keyboard"))
OUT.mkdir(parents=True, exist_ok=True)
SIZES = [(1280, "desktop"), (320, "mobile-320")]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


driver = Driver(bidi=True)
session = f"/session/{driver.session}"
bidi = connect(driver.websocket_url)
bidi_id = 0
context = None


def bidi_command(method, params=None):
    global bidi_id
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") != command_id:
            continue
        if "error" in response:
            raise RuntimeError(f"BiDi {method}: {response['error']}: {response.get('message', '')}")
        return response.get("result", {})


def press_tab():
    driver.req("POST", session + "/actions", {
        "actions": [{
            "type": "key",
            "id": "wiki-keyboard",
            "actions": [
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
            ],
        }],
    })


def focus_state():
    return driver.js("""return (() => {
      const el = document.activeElement;
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      const style = getComputedStyle(el);
      const labels = [...(el.labels || [])].map((label) => label.innerText.trim()).filter(Boolean);
      const outlineVisible = style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0;
      const shadowVisible = style.boxShadow !== 'none';
      return {
        tag: el.tagName,
        type: el.getAttribute('type') || '',
        name: el.getAttribute('name') || '',
        text: (el.innerText || el.value || '').trim().slice(0, 100),
        accessibleName: el.getAttribute('aria-label') || labels.join(' ') || el.getAttribute('title') || el.getAttribute('placeholder') || '',
        focusVisible: el.matches(':focus-visible'),
        indicator: outlineVisible || shadowVisible,
        rect: {left: Math.round(rect.left), top: Math.round(rect.top), right: Math.round(rect.right), bottom: Math.round(rect.bottom)},
        viewport: {width: window.innerWidth, height: window.innerHeight},
        visible: rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden',
        withinViewport: rect.left >= 0 && rect.right <= window.innerWidth,
      };
    })();""")


try:
    context = bidi_command("browsingContext.getTree")["contexts"][0]["context"]
    summary = {"base": BASE, "tested_url": BASE + "/wiki/busca", "sizes": {}}
    for width, name in SIZES:
        driver.get(BASE + "/wiki/busca")
        time.sleep(1)
        if os.environ.get("WIKI_CSS_OVERRIDE") == "1":
            loaded = driver.req("POST", session + "/execute/async", {
                "script": "const done = arguments[arguments.length - 1]; const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = '/themes/custom/aculta420/css/components/wiki.css?keyboard=' + Date.now(); link.onload = () => done(true); link.onerror = () => done(false); document.head.appendChild(link);",
                "args": [],
            })
            check(f"{name}: CSS candidato carregado", loaded is True)
        bidi_command("browsingContext.setViewport", {
            "context": context,
            "viewport": {"width": width, "height": 920},
        })
        time.sleep(0.3)
        check(f"{name}: largura efetiva", driver.js("return window.innerWidth") == width)

        stops = []
        search = None
        for step in range(1, 45):
            press_tab()
            state = focus_state()
            if not state or state["tag"] == "BODY":
                check(f"{name}: sequência de foco não se perde", False, f"tab={step} state={state}")
                break
            stops.append(state)
            check(
                f"{name}: foco visível no tab {step}",
                state["visible"] and state["focusVisible"] and state["indicator"],
                f"{state['tag']} {state['accessibleName'][:70]}",
            )
            if state["tag"] == "INPUT" and (state["name"] == "q" or state["type"] == "search"):
                search = state
                break

        check(
            f"{name}: campo de busca alcançável por Tab",
            search is not None and search["withinViewport"],
            search or "não encontrado em 44 tabs",
        )
        if search is not None:
            driver.type('input[name="q"], input[type="search"]', "maconha")
            press_tab()
            submit = focus_state()
            is_submit = submit is not None and (
                submit["tag"] == "BUTTON"
                or (submit["tag"] == "INPUT" and submit["type"] == "submit")
            )
            check(
                f"{name}: submit da busca alcançável por Tab",
                is_submit and submit["focusVisible"] and submit["indicator"] and submit["visible"] and submit["withinViewport"],
                submit or "submit não recebeu foco",
            )
            screenshot = driver.req("GET", session + "/screenshot")
            (OUT / f"keyboard-search-submit-{name}.png").write_bytes(base64.b64decode(screenshot))
        summary["sizes"][name] = {"width": width, "focus_stops": stops, "search": search}

    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    if context:
        try:
            driver.req("DELETE", session + "/actions")
        except Exception:
            pass
    bidi.close()
    driver.close()

failed = [result for result in results if not result["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
