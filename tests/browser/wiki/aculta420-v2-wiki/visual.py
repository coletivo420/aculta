#!/usr/bin/env python3
"""Read-only Firefox visual check for ACULTA420 V2 Wiki pages."""

import base64
import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[3]
sys.path.insert(0, str(PROJECT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver, WDError  # noqa: E402

BASE = os.environ.get("WIKI_BASE_URL", "").rstrip("/")
if not BASE or not urlparse(BASE).scheme or not urlparse(BASE).hostname:
    raise SystemExit("Defina WIKI_BASE_URL com a URL do Runtime de teste.")
OUT_VALUE = os.environ.get("SUITE_OUT", "")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT fora do repositório.")
OUT = Path(OUT_VALUE)
OUT.mkdir(parents=True, exist_ok=True)
WIDTHS = [(1440, "1440"), (768, "768"), (767, "767"), (390, "390"), (320, "320")]
PAGES = [("home", "/"), ("listing", "/wiki/verbetes")]
RESULTS = []


def check(name, passed, detail=""):
    RESULTS.append({"name": name, "ok": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


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


def set_width(width):
    bidi_command("browsingContext.setViewport", {
        "context": context,
        "viewport": {"width": width, "height": 920},
    })
    time.sleep(0.2)
    return driver.js("return window.innerWidth")


def press_key(key):
    driver.req("POST", session + "/actions", {
        "actions": [{
            "type": "key",
            "id": "aculta-wiki-keyboard",
            "actions": [{"type": "keyDown", "value": key}, {"type": "keyUp", "value": key}],
        }],
    })


def press_shift_tab():
    driver.req("POST", session + "/actions", {
        "actions": [{
            "type": "key",
            "id": "aculta-wiki-shift-tab",
            "actions": [
                {"type": "keyDown", "value": "\ue008"},
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue008"},
            ],
        }],
    })


def inspect_page(name, path, width, width_name, mode):
    separator = "&" if "?" in path else "?"
    url = f"{BASE}{path}{separator}aculta_check={int(time.time())}"
    try:
        driver.get(url)
    except WDError as error:
        check(f"{name}/{width_name}/{mode}: navegação", False, error)
        return None
    time.sleep(0.6)
    actual_width = set_width(width)
    if mode == "dark":
        driver.js("document.documentElement.dataset.theme = 'dark'")
    time.sleep(0.15)
    metrics = driver.js("""return (() => {
      const main = document.querySelector('main, #main-content');
      const visible = (el) => !!el && el.getClientRects().length > 0;
      const headings = [...(main || document).querySelectorAll('h1')].filter(visible);
      const article = document.querySelector('.aculta-wiki-article__layout');
      const gridColumns = article ? getComputedStyle(article).gridTemplateColumns.trim().split(/\\s+/).length : null;
      const errors = [...document.querySelectorAll('.messages--error, [role="alert"]')]
        .filter(visible).map((el) => (el.innerText || '').trim().slice(0, 120));
      return {
        viewport: innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        bodyWidth: document.body.scrollWidth,
        h1: headings.map((el) => el.innerText.trim()),
        institutionBar: visible(document.querySelector('.aculta-institution-bar')),
        navigation: visible(document.querySelector('.navigation-bar')),
        theme: document.documentElement.dataset.theme || '',
        article: !!article,
        articleColumns: gridColumns,
        titleBlock: (() => { const e = document.querySelector('.page__title'); return e ? getComputedStyle(e).display : null; })(),
        articleOrder: (() => { const a = document.querySelector('.aculta-wiki-article__main'); const b = document.querySelector('.aculta-wiki-article__aside'); if (!a || !b) return null; const ar = a.getBoundingClientRect(); const br = b.getBoundingClientRect(); return {dom: !!(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING), mainTop: ar.top, asideTop: br.top, mainLeft: ar.left, asideLeft: br.left}; })(),
        formColumns: (() => { const e = document.querySelector('.aculta-wiki-search, .aculta-wiki-search-results form'); return e ? getComputedStyle(e).gridTemplateColumns.trim().split(/\\s+/).length : null; })(),
        listingGrid: !!document.querySelector('.aculta-wiki-list > .layout-grid'),
        listingColumns: (() => { const e = document.querySelector('.aculta-wiki-list > .layout-grid') || document.querySelector('.aculta-wiki-list'); return e ? getComputedStyle(e).gridTemplateColumns.trim().split(/\\s+/).length : null; })(),
        listingHeaderBeforeGrid: (() => { const h = document.querySelector('.aculta-wiki-list > header'); const g = document.querySelector('.aculta-wiki-list > .layout-grid'); return h && g ? !!(h.compareDocumentPosition(g) & Node.DOCUMENT_POSITION_FOLLOWING) : null; })(),
        listingRows: document.querySelectorAll('.aculta-wiki-list .aculta-wiki-card').length,
        listingCount: document.querySelector('.aculta-wiki-list > header')?.innerText.trim() || '',
        categoryGrid: (() => { const e = [...document.querySelectorAll('.aculta-wiki-home .views-element-container')].find((container) => !!container.querySelector('.layout-grid > div > h2 a[href*=\"/taxonomy/term/\"]')); return !!e?.querySelector('.layout-grid'); })(),
        categoryColumns: (() => { const e = [...document.querySelectorAll('.aculta-wiki-home .views-element-container')].find((container) => !!container.querySelector('.layout-grid > div > h2 a[href*=\"/taxonomy/term/\"]')); const grid = e?.querySelector('.layout-grid'); return grid ? getComputedStyle(grid).gridTemplateColumns.trim().split(/\\s+/).length : null; })(),
        categoryRows: (() => { const e = [...document.querySelectorAll('.aculta-wiki-home .views-element-container')].find((container) => !!container.querySelector('.layout-grid > div > h2 a[href*=\"/taxonomy/term/\"]')); return e?.querySelector('.layout-grid')?.children.length || 0; })(),
        errors
      };
    })();""")
    prefix = f"{name}/{width_name}/{mode}"
    check(f"{prefix}: viewport", actual_width == width, f"requested={width} actual={actual_width}")
    check(f"{prefix}: um H1 visível", len(metrics["h1"]) == 1, metrics["h1"])
    check(f"{prefix}: shell preservado", metrics["institutionBar"] and metrics["navigation"], metrics)
    check(f"{prefix}: sem overflow horizontal", metrics["documentWidth"] <= width and metrics["bodyWidth"] <= width, metrics)
    check(f"{prefix}: sem mensagem de erro", not metrics["errors"], metrics["errors"])
    if metrics["article"]:
        expected_columns = 1 if width < 768 else 2
        title_hidden = metrics["titleBlock"] in (None, "none")
        order = metrics["articleOrder"]
        expected_order = not order or (order["dom"] and (order["asideTop"] >= order["mainTop"] if width < 768 else order["asideLeft"] > order["mainLeft"]))
        check(f"{prefix}: composição do artigo", metrics["articleColumns"] == expected_columns and expected_order and title_hidden, metrics)
    if metrics["formColumns"] is not None:
        check(f"{prefix}: formulário reflow", metrics["formColumns"] == (1 if width < 768 else 2), metrics["formColumns"])
    if metrics["listingColumns"] is not None:
        check(f"{prefix}: grade reflow", metrics["listingColumns"] == (1 if width < 768 else 2), metrics["listingColumns"])
        if name == "listing":
            check(f"{prefix}: linhas usam layout-grid", bool(metrics["listingGrid"]), metrics["listingGrid"])
            check(f"{prefix}: contagem precede a grade", metrics["listingHeaderBeforeGrid"], metrics["listingHeaderBeforeGrid"])
            check(f"{prefix}: seis verbetes SDC", metrics["listingRows"] == 6 and "6 verbetes publicados" in metrics["listingCount"], metrics)
    if name == "home":
        check(f"{prefix}: categorias usam layout-grid", metrics["categoryGrid"], metrics["categoryGrid"])
        check(f"{prefix}: grade de categorias reflow", metrics["categoryColumns"] == (1 if width < 768 else 2), metrics["categoryColumns"])
        check(f"{prefix}: 15 categorias preservadas", metrics["categoryRows"] == 15, metrics["categoryRows"])
    if name == "article" and mode == "light" and width in (320, 1440):
        press_key("\ue004")
        focus = driver.js("""const el = document.activeElement; const rect = el.getBoundingClientRect();
          return {tag: el.tagName, text: (el.innerText || el.value || '').trim(), focusVisible: el.matches(':focus-visible'),
            visible: rect.width > 0 && rect.height > 0, withinViewport: rect.left >= 0 && rect.right <= innerWidth};""")
        check(f"{prefix}: Tab alcança foco visível", focus["focusVisible"] and focus["visible"] and focus["withinViewport"], focus)
        driver.js("document.querySelector('.aculta-wiki-article a[href]')?.focus()")
        press_shift_tab()
        reverse_focus = driver.js("""const el = document.activeElement; const rect = el.getBoundingClientRect();
          return {articleLink: !!el.closest('.aculta-wiki-article'), focusVisible: el.matches(':focus-visible'),
            visible: rect.width > 0 && rect.height > 0, withinViewport: rect.left >= 0 && rect.right <= innerWidth};""")
        check(f"{prefix}: Shift+Tab retorna ao controle anterior", not reverse_focus["articleLink"] and reverse_focus["focusVisible"] and reverse_focus["visible"] and reverse_focus["withinViewport"], reverse_focus)
    if name == "home" and mode == "light" and width in (320, 1440):
        driver.js("document.querySelector('.aculta-wiki-home .layout-grid > div > h2 a[href*=\\\"/taxonomy/term/\\\"]')?.focus()")
        press_key("\ue004")
        category_focus = driver.js("""const el = document.activeElement; const rect = el.getBoundingClientRect();
          return {withinCategoryGrid: !!el.closest('.aculta-wiki-home .layout-grid'), focusVisible: el.matches(':focus-visible'),
            visible: rect.width > 0 && rect.height > 0, withinViewport: rect.left >= 0 && rect.right <= innerWidth};""")
        check(f"{prefix}: teclado percorre links de categoria com foco visível", category_focus["withinCategoryGrid"] and category_focus["focusVisible"] and category_focus["visible"] and category_focus["withinViewport"], category_focus)
    screenshot = driver.req("GET", session + "/screenshot")
    (OUT / f"{name}-{width_name}-{mode}.png").write_bytes(base64.b64decode(screenshot))
    metrics["url"] = driver.url()
    metrics["requested_width"] = width
    metrics["mode"] = mode
    return metrics


try:
    summary = {"base": BASE, "pages": {}, "results": RESULTS}
    for mode in ("light", "dark"):
        for name, path in PAGES:
            summary["pages"].setdefault(name, {})
            summary["pages"][name][mode] = {}
            for width, width_name in WIDTHS:
                summary["pages"][name][mode][width_name] = inspect_page(name, path, width, width_name, mode)

    driver.get(f"{BASE}/wiki/verbetes?aculta_check={int(time.time())}")
    time.sleep(0.7)
    entry_path = driver.js('return document.querySelector(\'main a[href*="/verbete/"]\')?.getAttribute("href") || ""')
    check("artigo: link canônico publicado encontrado", bool(entry_path), entry_path)
    if entry_path:
        summary["pages"]["article"] = {"light": {}, "dark": {}}
        for mode in ("light", "dark"):
            for width, width_name in WIDTHS:
                summary["pages"]["article"][mode][width_name] = inspect_page("article", entry_path, width, width_name, mode)

    summary["results"] = RESULTS
    summary["passed"] = sum(1 for result in RESULTS if result["ok"])
    summary["failed"] = [result for result in RESULTS if not result["ok"]]
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    bidi.close()
    driver.close()

failed = [result for result in RESULTS if not result["ok"]]
print(f"\n{len(RESULTS) - len(failed)}/{len(RESULTS)} checks passed; artifacts: {OUT}")
sys.exit(1 if failed else 0)
