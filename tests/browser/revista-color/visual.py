#!/usr/bin/env python3
"""Visible Firefox smoke for the closed editorial category palette."""

import base64
import json
import os
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-category-color"))
URL = os.environ.get("CATEGORY_COLOR_URL", "https://aculta.toca.net.br/")
TOKENS = ["red", "green", "yellow", "green-dark"]


class Viewport:
    """Sets a real CSS viewport through WebDriver BiDi."""

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
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, width, height):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": height},
        })
        time.sleep(0.5)

    def close(self):
        self.socket.close()


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("DISPLAY=:0 é obrigatório para Firefox ESR visível.")
    OUT.mkdir(parents=True, exist_ok=True)
    driver = Driver(prefs={"ui.systemUsesDarkTheme": 1}, bidi=True)
    viewport = Viewport(driver)
    results = {"url": URL, "environment": "test server", "tokens": TOKENS}
    try:
        driver.get(URL)
        time.sleep(3)
        driver.js("""(() => {
          const panel = document.createElement('section');
          panel.className = 'card e8-category-test';
          panel.setAttribute('aria-label', 'Amostras visuais de categoria');
          panel.style.cssText = 'margin:2rem auto;padding:1rem;max-width:34rem;display:flex;flex-wrap:wrap;gap:.75rem';
          for (const token of ['red', 'green', 'yellow', 'green-dark']) {
            const field = document.createElement('div');
            field.className = 'field--name-field-editorial-category';
            const label = document.createElement('a');
            label.href = '#';
            label.className = `aculta-category aculta-category--${token}`;
            label.textContent = `Categoria ${token}`;
            field.append(label);
            panel.append(field);
          }
          document.querySelector('main')?.prepend(panel) || document.body.prepend(panel);
        })();""")
        for label, width, height in [("1280", 1280, 900), ("320", 320, 900)]:
            viewport.set(width, height)
            result = driver.js("""return {
              viewport: innerWidth,
              documentWidth: document.documentElement.clientWidth,
              scrollWidth: document.documentElement.scrollWidth,
              labels: [...document.querySelectorAll('.e8-category-test .aculta-category')].map(el => ({
                text: el.textContent,
                color: getComputedStyle(el).color,
                background: getComputedStyle(el).backgroundColor,
                display: getComputedStyle(el).display,
              })),
            };""")
            if result["scrollWidth"] > result["documentWidth"] or len(result["labels"]) != 4:
                raise RuntimeError(f"Layout check failed at {width}px: {result}")
            results[label] = result
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"category-{label}.png").write_bytes(base64.b64decode(screenshot))
    finally:
        viewport.close()
        driver.close()
    (OUT / "results.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(results, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
