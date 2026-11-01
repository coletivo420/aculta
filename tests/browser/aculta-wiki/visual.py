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
CSS_ROOT = Path(os.environ.get("WIKI_CSS_ROOT", str(PROJECT)))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver, WDError  # noqa: E402

BASE = os.environ.get("WIKI_BASE_URL", "https://wiki420.aculta.toca.net.br").rstrip("/")
HOST = urlparse(BASE).hostname
OUT = Path(os.environ.get("SUITE_OUT", "/home/piradopirata/aculta-suite-out/aculta-wiki-visual"))
OUT.mkdir(parents=True, exist_ok=True)
WIDTHS = [(1440, "wide-1440"), (1280, "desktop"), (1024, "tablet-1024"),
          (768, "tablet-768"), (767, "tablet-767"), (640, "zoom-200-equiv"),
          (600, "tablet-600"), (576, "tablet-576"), (575, "tablet-575"),
          (390, "mobile-390"), (360, "mobile-360"),
          (320, "mobile-320")]
PAGES = [
    ("home", "/"),
    ("listing", "/wiki/verbetes"),
    ("search", "/wiki/busca?q=maconha"),
]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


prefs = {"ui.systemUsesDarkTheme": 1} if os.environ.get("WIKI_DARK") == "1" else None
driver = Driver(prefs=prefs, bidi=True)
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
    separator = "&" if "?" in path else "?"
    url = BASE + path + separator + "aculta_check=" + str(int(time.time()))
    try:
        driver.get(url)
    except WDError as error:
        check(f"{name}/{width_name}: navegação", False, error)
        return
    time.sleep(1.2)
    if os.environ.get("WIKI_CSS_OVERRIDE") == "1":
        candidate_css = "\n".join([
            (CSS_ROOT / "web/themes/custom/aculta420/css/components/wiki.css").read_text(),
            (CSS_ROOT / "web/themes/custom/aculta420/components/patterns/wiki-entry/wiki-entry.css").read_text(),
        ])
        css_loaded = driver.req("POST", session + "/execute/async", {
            "script": "const done = arguments[arguments.length - 1]; const style = document.createElement('style'); style.id = 'aculta-wiki-css-candidate'; style.textContent = arguments[0]; document.head.appendChild(style); requestAnimationFrame(() => done(!!style.sheet && style.sheet.cssRules.length > 0));",
            "args": [candidate_css],
        })
        check(f"{name}/{width_name}: CSS candidata do tema injetada", css_loaded is True)
    actual_width = set_width(expected_width)
    time.sleep(0.4)
    metrics = driver.js("""return (() => {
      const main = (document.querySelector('main') || document.querySelector('#main-content'));
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
        headerBrandBasis: getComputedStyle(document.querySelector('.aculta-header--wiki .aculta-branding')).flexBasis,
        headerSearchGap: getComputedStyle(document.querySelector('.aculta-wiki-header-search form')).gap,
        searchFormColumns: (() => { const form = document.querySelector('.aculta-wiki-search, .aculta-wiki-search-results form'); return form ? getComputedStyle(form).gridTemplateColumns.split(' ').length : null; })(),
        contentGridColumns: (() => { const grid = document.querySelector('.aculta-wiki-home .view-wiki-categories .view-content, .aculta-wiki-home .view-wiki-entries .view-content, .aculta-wiki-search-results .view-content'); return grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : null; })(),
        wikiArticleColumns: (() => { const grid = document.querySelector('.aculta-wiki-article__layout'); return grid ? getComputedStyle(grid).gridTemplateColumns.trim().split(/\\s+/).length : null; })(),
        wikiAsideRow: (() => { const aside = document.querySelector('.aculta-wiki-article__aside'); return aside ? getComputedStyle(aside).gridRowStart : null; })(),
        wikiMainRect: (() => { const main = document.querySelector('.aculta-wiki-article__main'); if (!main) return null; const r = main.getBoundingClientRect(); return {top: r.top, left: r.left}; })(),
        wikiAsideRect: (() => { const aside = document.querySelector('.aculta-wiki-article__aside'); if (!aside) return null; const r = aside.getBoundingClientRect(); return {top: r.top, left: r.left}; })(),
        wikiMainBeforeAside: (() => { const main = document.querySelector('.aculta-wiki-article__main'); const aside = document.querySelector('.aculta-wiki-article__aside'); return !!main && !!aside && !!(main.compareDocumentPosition(aside) & Node.DOCUMENT_POSITION_FOLLOWING); })(),
        wikiTabsMarginBottom: (() => { const tabs = document.querySelector('.aculta-wiki-article__tabs'); return tabs ? getComputedStyle(tabs).marginBottom : null; })(),
        overflowingElements,
        errors: visibleErrors,
      };
    })();""")
    check(f"{name}/{width_name}: main e h1", metrics["hasMain"] and bool(metrics["heading"]), metrics["heading"][:100])
    check(f"{name}/{width_name}: viewport solicitado", metrics["viewport"] == expected_width,
          f"esperado={expected_width} medido={metrics['viewport']}")
    expected_basis = "112px" if expected_width < 576 else "136px" if expected_width < 768 else "192px"
    expected_gap = "6px" if expected_width < 576 else "8px"
    expected_columns = 1 if expected_width < 768 else 2
    check(f"{name}/{width_name}: layout mobile-first", metrics["headerBrandBasis"] == expected_basis
          and metrics["headerSearchGap"] == expected_gap
          and metrics["searchFormColumns"] in (None, expected_columns)
          and metrics["contentGridColumns"] in (None, expected_columns),
          f"brand={metrics['headerBrandBasis']} searchGap={metrics['headerSearchGap']} formColumns={metrics['searchFormColumns']} contentColumns={metrics['contentGridColumns']}")
    if name == "entry":
        expected_article_columns = 1 if expected_width < 768 else 2
        expected_tabs_margin = "16px" if expected_width < 768 else "20px"
        mobile_order = expected_width >= 768 or (
            metrics["wikiMainRect"] and metrics["wikiAsideRect"]
            and metrics["wikiMainRect"]["top"] <= metrics["wikiAsideRect"]["top"] + 1
        )
        desktop_order = expected_width < 768 or (
            metrics["wikiMainRect"] and metrics["wikiAsideRect"]
            and metrics["wikiAsideRect"]["left"] > metrics["wikiMainRect"]["left"]
        )
        check(f"{name}/{width_name}: composição do verbete",
              metrics["wikiArticleColumns"] == expected_article_columns
              and metrics["wikiAsideRow"] == "auto"
              and metrics["wikiMainBeforeAside"] and mobile_order and desktop_order
              and metrics["wikiTabsMarginBottom"] == expected_tabs_margin,
              f"columns={metrics['wikiArticleColumns']} asideRow={metrics['wikiAsideRow']} main={metrics['wikiMainRect']} aside={metrics['wikiAsideRect']} DOM-order={metrics['wikiMainBeforeAside']} tabsMargin={metrics['wikiTabsMarginBottom']}")
    check(f"{name}/{width_name}: sem overflow horizontal", metrics["documentWidth"] <= metrics["viewport"],
          f"viewport={metrics['viewport']} document={metrics['documentWidth']} root={metrics['rootClientWidth']} body={metrics['bodyWidth']} elements={metrics['overflowingElements']}")
    check(f"{name}/{width_name}: sem erro visível", not metrics["errors"], metrics["errors"])
    if os.environ.get("WIKI_SKIP_SCREENSHOTS") != "1":
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

    # A published entry is the primary public artifact; discover its canonical
    # URL from the server-rendered search instead of hardcoding a slug.
    driver.get(BASE + "/wiki/busca?q=maconha&aculta_check=" + str(int(time.time())))
    time.sleep(0.8)
    entry_path = driver.js(
        "return document.querySelector('main a[href*=\"/verbete/\"]')?.getAttribute('href') || ''"
    )
    check("verbete: link canônico presente na busca", bool(entry_path), entry_path)
    if entry_path:
        summary["pages"]["entry"] = {}
        for width, width_name in WIDTHS:
            summary["pages"]["entry"][width_name] = inspect_page(
                "entry", entry_path, width_name, width,
            )
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    bidi.close()
    driver.close()

failed = [result for result in results if not result["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
