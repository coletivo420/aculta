#!/usr/bin/env python3
"""Visual smoke test read-only para as páginas públicas da ACULTA Wiki.

Executar no Firefox ESR visível, conforme README.md desta pasta.
"""

import base64
import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import urlparse
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver, WDError  # noqa: E402

BASE = os.environ.get("WIKI_BASE_URL", "https://wiki420.aculta.toca.net.br").rstrip("/")
HOST = urlparse(BASE).hostname
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-wiki-visual"))
OUT.mkdir(parents=True, exist_ok=True)
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
PAGES = [
    ("home", "/"),
    ("listing", "/wiki/verbetes"),
    ("search", "/wiki/busca?q=maconha"),
]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


driver = Driver(bidi=True)
session = f"/session/{driver.session}"
bidi = connect(driver.websocket_url)
bidi_id = 0


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


context = bidi_command("browsingContext.getTree")["contexts"][0]["context"]


def set_width(target):
    bidi_command("browsingContext.setViewport", {
        "context": context,
        "viewport": {"width": target, "height": 920},
    })
    time.sleep(0.2)
    return int(driver.js("return window.innerWidth"))


def inspect_page(name, path, width_name, expected_width):
    url = BASE + path
    try:
        driver.get(url)
    except WDError as error:
        check(f"{name}/{width_name}: navegação", False, error)
        return
    time.sleep(1.2)
    if os.environ.get("WIKI_CSS_OVERRIDE") == "1":
        css_loaded = driver.req("POST", session + "/execute/async", {
            "script": "const done = arguments[arguments.length - 1]; const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = '/themes/custom/aculta420/css/components/wiki.css?visual=' + Date.now(); link.onload = () => done(true); link.onerror = () => done(false); document.head.appendChild(link);",
            "args": [],
        })
        check(f"{name}/{width_name}: CSS atual do tema carregado", css_loaded is True)
    actual_width = set_width(expected_width)
    time.sleep(0.4)
    metrics = driver.js("""return (() => {
      const main = document.querySelector('main, #main-content');
      const heading = main?.querySelector('h1');
      const visibleErrors = [...document.querySelectorAll('.messages--error, [role="alert"]')]
        .filter((el) => el.getClientRects().length)
        .map((el) => el.innerText.trim().slice(0, 120));
      const overflowingElements = [...document.querySelectorAll('body *')]
        .map((el) => ({el, rect: el.getBoundingClientRect(), style: getComputedStyle(el)}))
        .filter(({el, rect, style}) => el.getClientRects().length
          && style.position !== 'fixed' && style.visibility !== 'hidden'
          && rect.right > window.innerWidth + 1)
        .sort((a, b) => b.rect.right - a.rect.right).slice(0, 5)
        .map(({el, rect, style}) => ({tag: el.tagName, className: String(el.className).slice(0, 100),
          right: Math.round(rect.right), position: style.position, display: style.display}));
      return {
        title: document.title,
        heading: heading?.innerText.trim() || '',
        hasMain: !!main,
        viewport: window.innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        rootClientWidth: document.documentElement.clientWidth,
        bodyWidth: document.body.scrollWidth,
        overflowingElements,
        errors: visibleErrors,
      };
    })();""")
    check(f"{name}/{width_name}: main e h1", metrics["hasMain"] and bool(metrics["heading"]), metrics["heading"][:100])
    check(f"{name}/{width_name}: viewport solicitado", metrics["viewport"] == expected_width,
          f"esperado={expected_width} medido={metrics['viewport']}")
    check(f"{name}/{width_name}: sem overflow horizontal", metrics["documentWidth"] <= metrics["viewport"],
          f"viewport={metrics['viewport']} document={metrics['documentWidth']} root={metrics['rootClientWidth']} body={metrics['bodyWidth']} elements={metrics['overflowingElements']}")
    check(f"{name}/{width_name}: sem erro visível", not metrics["errors"], metrics["errors"])
    screenshot = driver.req("GET", session + "/screenshot")
    (OUT / f"{name}-{width_name}.png").write_bytes(base64.b64decode(screenshot))
    metrics["requestedWidth"] = expected_width
    metrics["url"] = driver.url()
    return metrics


try:
    summary = {"base": BASE, "pages": {}}
    for name, path in PAGES:
        print(f"== {name}: {BASE + path}")
        summary["pages"][name] = {}
        for width, width_name in WIDTHS:
            summary["pages"][name][width_name] = inspect_page(name, path, width_name, width)

    response = driver.req("POST", session + "/execute/async", {
        "script": "fetch('/wiki', {method: 'GET', cache: 'no-store'}).then((r) => arguments[arguments.length - 1]({status: r.status, redirected: r.redirected, url: r.url}));",
        "args": [],
    })
    check("/wiki direto segue fechado como caminho interno", response["status"] == 404, response)
    summary["internalPath"] = response
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    bidi.close()
    driver.close()

failed = [result for result in results if not result["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
