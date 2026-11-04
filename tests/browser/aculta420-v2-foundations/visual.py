#!/usr/bin/env python3
"""Visible Firefox check for ACULTA420 V2 foundations and responsive reflow."""

import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlparse, urlunparse

ROOT = Path(__file__).resolve().parents[3]
os.umask(0o077)
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402


class Viewport:
    """Control a CSS viewport using Firefox's WebDriver BiDi endpoint."""

    def __init__(self, driver):
        from websockets.sync.client import connect

        self.socket = connect(driver.websocket_url)
        self.sequence = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.sequence += 1
        command_id = self.sequence
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width, height=960):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": height},
        })

    def close(self):
        self.socket.close()


OUT_VALUE = os.environ.get("SUITE_OUT")
PURPOSES_JSON = os.environ.get("PURPOSES_JSON")
BASE_URL = os.environ.get("BASE_URL")
BASELINE_REF = os.environ.get("BASELINE_REF", "HEAD")
if not OUT_VALUE or not (PURPOSES_JSON or BASE_URL):
    raise SystemExit("Defina SUITE_OUT e PURPOSES_JSON (ou BASE_URL para somente MAIN).")
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("A revisão visual exige Firefox ESR visível em DISPLAY=:0.")
OUT = Path(OUT_VALUE).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.chmod(OUT, 0o700)
WIDTHS = (320, 360, 390, 599, 600, 768, 1024, 1280, 1440)
if PURPOSES_JSON:
    PURPOSES = json.loads(PURPOSES_JSON)
else:
    PURPOSES = [{"purpose": "main", "url": BASE_URL}]
if not isinstance(PURPOSES, list) or not PURPOSES:
    raise SystemExit("PURPOSES_JSON deve ser uma lista JSON não vazia.")
for item in PURPOSES:
    parsed = urlparse(item.get("url", ""))
    if not item.get("purpose") or parsed.scheme not in ("http", "https") or not parsed.netloc or parsed.query or parsed.fragment:
        raise SystemExit("Cada purpose exige nome e URL http(s) sem query nem fragmento.")


def baseline_css():
    files = (
        "src/styles/tokens.css",
        "src/styles/base.css",
        "src/styles/layout.css",
    )
    chunks = []
    for relative_path in files:
        result = subprocess.run(
            ["git", "show", f"{BASELINE_REF}:web/themes/custom/aculta420_v2/{relative_path}"],
            cwd=ROOT,
            check=True,
            capture_output=True,
            text=True,
        )
        chunks.append(result.stdout)
    return "\n".join(chunks)


BASELINE_CSS = baseline_css()
RESULTS = []
driver = Driver(bidi=True, browser="firefox")
viewport = Viewport(driver)
try:
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"],
        text=True,
    ).splitlines()
    if not windows:
        raise RuntimeError("Firefox ESR window was not found for real browser zoom.")
    firefox_window = windows[-1]
    subprocess.run(["xdotool", "windowactivate", "--sync", firefox_window], check=True)
    subprocess.run(["xdotool", "key", "ctrl+0"], check=True)
    time.sleep(0.25)
    baseline_dpr = driver.js("return devicePixelRatio")

    for item in PURPOSES:
        purpose = item["purpose"]
        requested_url = urlunparse((*urlparse(item["url"])[:3], "", "", ""))
        subprocess.run(["xdotool", "key", "ctrl+0"], check=True)
        driver.get(requested_url)
        for _ in range(60):
            if driver.js("return document.readyState === 'complete'"):
                break
            time.sleep(0.25)
        original_font_link = driver.js("""return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
          .find(link => new URL(link.href).hostname === 'fonts.googleapis.com')?.href || '';""")

        for mode in ("light", "dark"):
            driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
            for state in ("before", "after"):
                if state == "before":
                    driver.js("""document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                      if (new URL(link.href).hostname === 'fonts.googleapis.com') link.remove();
                    });
                    const style = document.createElement('style');
                    style.dataset.acultaBaseline = 'true';
                    style.textContent = arguments[0];
                    document.head.append(style);
                    return true;""", [BASELINE_CSS])
                    time.sleep(0.2)
                else:
                    driver.js("document.querySelector('[data-aculta-baseline]')?.remove(); return true")
                    if original_font_link:
                        driver.js("""const link = document.createElement('link');
                          link.rel = 'stylesheet'; link.href = arguments[0]; document.head.append(link); return true;""", [original_font_link])
                        driver.js("return document.fonts.ready.then(() => true)")
                for width in WIDTHS:
                    viewport.set(width)
                    time.sleep(0.45)
                    result = driver.js("""return {
                      mode: document.documentElement.getAttribute('data-theme'),
                      canvas: getComputedStyle(document.documentElement).getPropertyValue('--color-canvas').trim(),
                      bodyBackground: getComputedStyle(document.body).backgroundColor,
                      viewport: innerWidth,
                      documentWidth: document.documentElement.scrollWidth,
                      bodyWidth: document.body.scrollWidth,
                      horizontalOverflow: document.documentElement.scrollWidth > innerWidth + 1
                        || document.body.scrollWidth > innerWidth + 1,
                      overflowElements: Array.from(document.querySelectorAll('body *')).map(element => {
                        const rect = element.getBoundingClientRect();
                        return {tag: element.tagName, id: element.id,
                          className: typeof element.className === 'string' ? element.className : '',
                          right: Math.round(rect.right), scrollWidth: element.scrollWidth,
                          clientWidth: element.clientWidth};
                      }).filter(element => element.right > innerWidth + 1
                        || element.scrollWidth > element.clientWidth + 1).slice(0, 40),
                      widestElements: Array.from(document.querySelectorAll('body *')).map(element => {
                        const rect = element.getBoundingClientRect();
                        return {tag: element.tagName, id: element.id,
                          className: typeof element.className === 'string' ? element.className : '',
                          left: Math.round(rect.left), right: Math.round(rect.right),
                          width: Math.round(rect.width), scrollWidth: element.scrollWidth,
                          clientWidth: element.clientWidth};
                      }).filter(element => element.scrollWidth > innerWidth + 1
                        || element.right > innerWidth + 1).sort((a, b) => b.scrollWidth - a.scrollWidth).slice(0, 20),
                      hasThemeStyles: Boolean(getComputedStyle(document.documentElement).getPropertyValue('--color-canvas').trim()),
                    };""")
                    result.update({
                        "purpose": purpose,
                        "url": requested_url,
                        "finalUrl": driver.js("return location.href"),
                        "authState": "anonymous",
                        "modeExpected": mode,
                        "widthExpected": width,
                        "state": state,
                        "baselineRef": BASELINE_REF,
                        "responseStatus": driver.js("return performance.getEntriesByType('navigation')[0]?.responseStatus || null"),
                    })
                    RESULTS.append(result)
                    print(f"{purpose}/{state}/{mode}/{width}px status={result['responseStatus']} "
                          f"doc={result['documentWidth']} overflow={result['horizontalOverflow']} "
                          f"body={result['bodyBackground']}", flush=True)
                    if width in (320, 1280):
                        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                        (OUT / f"{purpose}-{state}-{mode}-{width}.png").write_bytes(base64.b64decode(screenshot))

            driver.js("document.querySelector('[data-aculta-baseline]')?.remove(); return true")
            if original_font_link:
                driver.js("""const link = document.createElement('link');
                  link.rel = 'stylesheet'; link.href = arguments[0]; document.head.append(link); return true;""", [original_font_link])

        viewport.set(1280)
        driver.js("document.documentElement.setAttribute('data-theme', 'light'); document.activeElement?.blur(); document.body.focus(); return true")
        driver.req("POST", f"/session/{driver.session}/actions", {
            "actions": [{"type": "key", "id": "foundation-tab", "actions": [
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
            ]}],
        })
        focus = driver.js("""const element = document.activeElement;
          const style = element ? getComputedStyle(element) : null;
          return {tag: element?.tagName || '', label: element?.getAttribute('aria-label') || element?.textContent?.trim() || '',
            outlineStyle: style?.outlineStyle || '', outlineWidth: style?.outlineWidth || ''};""")
        focus_result = {"type": "keyboard-focus", "purpose": purpose, "url": requested_url,
                        "finalUrl": driver.js("return location.href"), "mode": "light",
                        "authState": "anonymous",
                        "viewport": driver.js("return innerWidth"),
                        "zoomRatio": driver.js("return devicePixelRatio") / baseline_dpr, **focus}
        RESULTS.append(focus_result)
        print(f"{purpose} keyboard focus: {focus_result}", flush=True)
        driver.req("POST", f"/session/{driver.session}/actions", {
            "actions": [{"type": "key", "id": "foundation-intermediate-tab", "actions": [
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
            ]}],
        })
        driver.req("POST", f"/session/{driver.session}/actions", {
            "actions": [{"type": "key", "id": "foundation-shift-tab", "actions": [
                {"type": "keyDown", "value": "\ue008"},
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue008"},
            ]}],
        })
        reverse_focus = driver.js("""const element = document.activeElement;
          const style = element ? getComputedStyle(element) : null;
          return {type: 'keyboard-shift-tab', purpose: arguments[0], finalUrl: location.href,
            mode: document.documentElement.getAttribute('data-theme'), viewport: innerWidth,
            authState: 'anonymous', zoomRatio: devicePixelRatio / arguments[1], tag: element?.tagName || '',
            outlineStyle: style?.outlineStyle || '', outlineWidth: style?.outlineWidth || ''};""",
            [purpose, baseline_dpr])
        RESULTS.append(reverse_focus)
        print(f"{purpose} reverse keyboard focus: {reverse_focus}", flush=True)

        viewport.set(640)
        driver.js("document.documentElement.setAttribute('data-theme', 'dark'); return true")
        subprocess.run(["xdotool", "windowactivate", "--sync", firefox_window], check=True)
        subprocess.run(["xdotool", "key", "ctrl+0"], check=True)
        time.sleep(0.4)
        baseline_dpr = driver.js("return devicePixelRatio")
        for _ in range(16):
            subprocess.run(["xdotool", "key", "ctrl+plus"], check=True)
            time.sleep(0.15)
            if driver.js("return devicePixelRatio") >= baseline_dpr * 2:
                break
        zoom_result = driver.js("""return {type: 'zoom-200', purpose: arguments[0], url: arguments[1],
          finalUrl: location.href, authState: 'anonymous',
          baselineDpr: arguments[2], dpr: devicePixelRatio, zoomRatio: devicePixelRatio / arguments[2],
          viewport: innerWidth, documentWidth: document.documentElement.scrollWidth,
          horizontalOverflow: document.documentElement.scrollWidth > innerWidth + 1
            || document.body.scrollWidth > innerWidth + 1};""", [purpose, requested_url, baseline_dpr])
        RESULTS.append(zoom_result)
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"{purpose}-after-dark-zoom-200.png").write_bytes(base64.b64decode(screenshot))
        print(f"{purpose} native browser zoom: {zoom_result}", flush=True)
        subprocess.run(["xdotool", "key", "ctrl+0"], check=True)
        time.sleep(0.25)
        viewport.set(1280)
        for _ in range(32):
            subprocess.run(["xdotool", "key", "ctrl+plus"], check=True)
            time.sleep(0.15)
            if driver.js("return devicePixelRatio") >= baseline_dpr * 4:
                break
        zoom_400 = driver.js("""return {type: 'zoom-400', purpose: arguments[0], url: arguments[1],
          finalUrl: location.href, authState: 'anonymous', baselineDpr: arguments[2], dpr: devicePixelRatio,
          zoomRatio: devicePixelRatio / arguments[2], viewport: innerWidth,
          documentWidth: document.documentElement.scrollWidth,
          horizontalOverflow: document.documentElement.scrollWidth > innerWidth + 1
            || document.body.scrollWidth > innerWidth + 1};""", [purpose, requested_url, baseline_dpr])
        RESULTS.append(zoom_400)
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"{purpose}-after-dark-zoom-400.png").write_bytes(base64.b64decode(screenshot))
        print(f"{purpose} native browser zoom 400%: {zoom_400}", flush=True)
finally:
    viewport.close()
    driver.close()

for preference, prefs in (
    ("no-js", {"javascript.enabled": False}),
    ("reduced-motion", {"ui.prefersReducedMotion": 1}),
):
    preference_driver = Driver(prefs=prefs, bidi=True, browser="firefox")
    preference_viewport = Viewport(preference_driver)
    try:
        for item in PURPOSES:
            purpose = item["purpose"]
            requested_url = urlunparse((*urlparse(item["url"])[:3], "", "", ""))
            preference_driver.get(requested_url)
            for _ in range(60):
                if preference_driver.js("return document.readyState === 'complete'"):
                    break
                time.sleep(0.25)
            mode = preference_driver.js("return document.documentElement.getAttribute('data-theme') || 'light'")
            if preference == "reduced-motion":
                preference_driver.js("document.documentElement.setAttribute('data-theme', 'light')")
                modes = ("light", "dark")
            else:
                modes = (mode,)
            for expected_mode in modes:
                if preference == "reduced-motion":
                    preference_driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [expected_mode])
                for width in WIDTHS:
                    preference_viewport.set(width)
                    time.sleep(0.2)
                    result = preference_driver.js("""const root = document.documentElement;
                      const body = document.body;
                      const probe = document.querySelector('a, button, input, p, h1') || body;
                      const style = getComputedStyle(probe);
                      return {type: arguments[0], purpose: arguments[1], url: arguments[2],
                        finalUrl: location.href, authState: 'anonymous', mode: root.getAttribute('data-theme') || 'light',
                        modeExpected: arguments[3], viewport: innerWidth, widthExpected: arguments[4],
                        documentWidth: root.scrollWidth,
                        horizontalOverflow: root.scrollWidth > innerWidth + 1 || body.scrollWidth > innerWidth + 1,
                        reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
                        transitionDuration: style.transitionDuration,
                        transitionDurationSeconds: parseFloat(style.transitionDuration)
                          * (style.transitionDuration.endsWith('ms') ? 0.001 : 1),
                        responseStatus: performance.getEntriesByType('navigation')[0]?.responseStatus || null};""",
                        [preference, purpose, requested_url, expected_mode, width])
                    RESULTS.append(result)
                    if width == 320 and expected_mode == modes[0]:
                        print(f"{purpose} {preference}: {result}", flush=True)
                        screenshot = preference_driver.req("GET", f"/session/{preference_driver.session}/screenshot")
                        (OUT / f"{purpose}-{preference}-{expected_mode}-320.png").write_bytes(base64.b64decode(screenshot))
    finally:
        preference_viewport.close()
        preference_driver.close()

(OUT / "results.json").write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2) + "\n")
bad = [item for item in RESULTS if "horizontalOverflow" in item and item["horizontalOverflow"]]
bad_modes = [item for item in RESULTS if "modeExpected" in item
             and item.get("mode") != item.get("modeExpected")]
bad_widths = [item for item in RESULTS if "widthExpected" in item and item["viewport"] != item["widthExpected"]]
focus_items = [item for item in RESULTS if item.get("type") == "keyboard-focus"]
focus_bad = not focus_items or any(item["tag"] in ("", "BODY") or item["outlineStyle"] == "none"
                                   or item["outlineWidth"] == "0px" for item in focus_items)
reverse_focus_items = [item for item in RESULTS if item.get("type") == "keyboard-shift-tab"]
reverse_focus_bad = not reverse_focus_items or any(item["tag"] in ("", "BODY")
                                                   or item["outlineStyle"] == "none"
                                                   or item["outlineWidth"] == "0px" for item in reverse_focus_items)
zoom_200_items = [item for item in RESULTS if item.get("type") == "zoom-200"]
zoom_400_items = [item for item in RESULTS if item.get("type") == "zoom-400"]
zoom_bad = (not zoom_200_items or not zoom_400_items
            or any(abs(item["zoomRatio"] - 2) > 0.05 or item["horizontalOverflow"] for item in zoom_200_items)
            or any(abs(item["zoomRatio"] - 4) > 0.1 or item["horizontalOverflow"] for item in zoom_400_items))
no_js_items = [item for item in RESULTS if item.get("type") == "no-js"]
no_js_bad = not no_js_items or any(item["horizontalOverflow"] or item.get("responseStatus") != 200 for item in no_js_items)
motion_items = [item for item in RESULTS if item.get("type") == "reduced-motion"]
motion_bad = not motion_items or any(not item["reducedMotion"] or item["horizontalOverflow"]
                                     or item["transitionDurationSeconds"] > 0.00002
                                     or item.get("responseStatus") != 200 for item in motion_items)
http_bad = [item for item in RESULTS if item.get("responseStatus") is not None
            and not 200 <= item["responseStatus"] < 400]
if http_bad:
    print("HTTP failures prevent visual validation for: " + ", ".join(
        sorted({f"{item['purpose']}={item['responseStatus']}" for item in http_bad})), flush=True)
checked = sum("horizontalOverflow" in item for item in RESULTS if item.get("state") == "after")
before_checked = sum("horizontalOverflow" in item for item in RESULTS if item.get("state") == "before")
print(f"{checked - len(bad)}/{checked} light/dark reflow checks passed; "
      f"baseline_before={before_checked} checks; "
      f"keyboard_focus={'FAIL' if focus_bad else 'PASS'}; "
      f"shift_tab={'FAIL' if reverse_focus_bad else 'PASS'}; "
      f"http={'FAIL' if http_bad else 'PASS'}; "
      f"zoom_200_400={'FAIL' if zoom_bad else 'PASS'}; "
      f"no_js={'FAIL' if no_js_bad else 'PASS'}; reduced_motion={'FAIL' if motion_bad else 'PASS'}; evidence: {OUT}")
sys.exit(1 if bad or bad_modes or bad_widths or focus_bad or reverse_focus_bad or zoom_bad
         or no_js_bad or motion_bad or http_bad else 0)
