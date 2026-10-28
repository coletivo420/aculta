#!/usr/bin/env python3
"""Visible Firefox smoke for the public map at desktop, mobile, and dark mode."""

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

OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-maps-mp7"))
URL = os.environ.get("MAP_URL", "https://eventos.aculta.toca.net.br/mapa")


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
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, width, height):
        self.command("browsingContext.setViewport", {
            "context": self.context, "viewport": {"width": width, "height": height},
        })
        time.sleep(0.5)

    def close(self):
        self.socket.close()


def capture(driver, name):
    payload = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / name).write_bytes(base64.b64decode(payload))


def metrics(driver):
    return driver.js("""return {
      title: document.title,
      viewport: {width: innerWidth, height: innerHeight},
      document: {width: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth},
      map: (() => { const el = document.querySelector('.aculta-map'); return el ? {
        width: el.clientWidth, height: el.clientHeight,
        background: getComputedStyle(el).backgroundColor,
        leaflet: el.classList.contains('leaflet-container') || !!el.querySelector('.leaflet-container'),
        markers: el.querySelectorAll('.leaflet-marker-icon').length,
        provider: Object.values(drupalSettings.acultaMaps || {})[0]?.provider || null,
        configuredMarkers: Object.values(drupalSettings.acultaMaps || {})[0]?.markers?.length || 0,
      } : null; })(),
      privacyLink: [...document.querySelectorAll('a')].some(a => a.getAttribute('href')?.includes('/sobre/politica-de-privacidade')),
      routeLinks: [...document.querySelectorAll('a')].filter(a => a.textContent.trim() === 'Ver rota').map(a => ({target:a.target, rel:a.rel, class:a.className, margin:getComputedStyle(a).marginInlineStart})),
      darkPreference: matchMedia('(prefers-color-scheme: dark)').matches,
      theme: document.documentElement.getAttribute('data-bs-theme'),
      bodyText: document.body.innerText.slice(0, 1200),
    };""")


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("DISPLAY=:0 é obrigatório para Firefox ESR visível.")
    OUT.mkdir(parents=True, exist_ok=True)
    results = {"url": URL, "environment": "test server", "screenshots": []}
    driver = Driver(prefs={"ui.systemUsesDarkTheme": 1}, bidi=True)
    viewport = Viewport(driver)
    try:
        driver.get(URL)
        time.sleep(5)
        if not driver.exists(".aculta-map"):
            raise RuntimeError(f"Mapa público não encontrado em {driver.url()}")

        for label, width, height in [("1280", 1280, 1000), ("320", 320, 900)]:
            viewport.set(width, height)
            results[label] = metrics(driver)
            filename = f"map-{label}.png"
            capture(driver, filename)
            results["screenshots"].append(filename)

        driver.js("document.documentElement.setAttribute('data-bs-theme', 'dark');")
        viewport.set(1280, 1000)
        results["dark"] = metrics(driver)
        filename = "map-dark-1280.png"
        capture(driver, filename)
        results["screenshots"].append(filename)
    finally:
        viewport.close()
        driver.close()

    result_file = OUT / "map-results.json"
    result_file.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(results, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
