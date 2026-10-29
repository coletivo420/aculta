#!/usr/bin/env python3
"""Read-only Firefox check for the real public Forum author-card consumer."""

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
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("FORUM_BASE_URL", "https://growroom420.aculta.toca.net.br").rstrip("/")
OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT para guardar PNG e JSON fora do repositório.")
OUT = Path(OUT_VALUE)
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))

DEFAULT_WIDTHS = [1440, 1280, 1024, 768, 600, 576, 575, 390, 360, 320]
WIDTHS = [int(value) for value in os.environ.get(
    "AUTHOR_CARD_WIDTHS", ",".join(map(str, DEFAULT_WIDTHS))
).split(",")]
PATH = "/discussoes/novas"
HOST = urlparse(BASE).hostname
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(bidi=True)
bidi = connect(driver.websocket_url)
tree_id = 1
bidi.send(json.dumps({"id": tree_id, "method": "browsingContext.getTree", "params": {}}))
while True:
    response = json.loads(bidi.recv())
    if response.get("id") == tree_id:
        context = response["result"]["contexts"][0]["context"]
        break
bidi_id = tree_id


def set_width(width):
    global bidi_id
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
    time.sleep(0.25)
    return int(driver.js("return window.innerWidth"))


try:
    driver.get(f"{BASE}{PATH}?aculta_check={int(time.time())}")
    time.sleep(1.2)
    initial = driver.js("""return {
      title: document.title,
      count: document.querySelectorAll(
        '.aculta-forum-topic-list-row__topic .aculta-author-card--author_forum'
      ).length,
      path: location.pathname
    };""")
    check("rota pública de tópicos carregou", initial["path"] == PATH and bool(initial["title"]), initial["path"])
    check("consumer real de autoria do Fórum presente", initial["count"] > 0, f"cards={initial['count']}")

    for width in WIDTHS:
        actual = set_width(width)
        metrics = driver.js("""return (() => {
          const cards = [...document.querySelectorAll(
            '.aculta-forum-topic-list-row__topic .aculta-author-card--author_forum'
          )];
          const alignments = [...new Set(cards.map((card) => getComputedStyle(card).alignItems))];
          return {
            width: window.innerWidth,
            document: document.documentElement.scrollWidth,
            cards: cards.length,
            alignments,
            overflowing: [...document.querySelectorAll('body *')]
              .map((el) => ({el, rect: el.getBoundingClientRect(), style: getComputedStyle(el)}))
              .filter(({el, rect, style}) => el.getClientRects().length
                && style.position !== 'fixed' && style.visibility !== 'hidden'
                && rect.right > window.innerWidth)
              .sort((a, b) => b.rect.right - a.rect.right).slice(0, 4)
              .map(({el, rect}) => ({tag: el.tagName, className: String(el.className).slice(0, 100),
                right: Math.round(rect.right * 10) / 10})),
            scrolling: [...document.querySelectorAll('body *')]
              .filter((el) => el.getClientRects().length
                && el.getBoundingClientRect().width > 0
                && el.scrollWidth > el.getBoundingClientRect().width + 1)
              .sort((a, b) => b.scrollWidth - b.getBoundingClientRect().width
                - (a.scrollWidth - a.getBoundingClientRect().width))
              .slice(0, 4)
              .map((el) => ({tag: el.tagName, className: String(el.className).slice(0, 100),
                client: el.clientWidth, scroll: el.scrollWidth,
                whiteSpace: getComputedStyle(el).whiteSpace})),
            firstAvatarWidth: cards[0]?.querySelector('.aculta-author-card__avatar')
              ? Math.round(cards[0].querySelector('.aculta-author-card__avatar').getBoundingClientRect().width)
              : null,
          };
        })();""")
        expected_alignment = "flex-start" if width < 576 else "center"
        check(f"{width}px: viewport configurado", actual == width, f"medido={actual}")
        check(f"{width}px: sem overflow", metrics["document"] <= width,
              f"viewport={width}, document={metrics['document']}, elements={metrics['overflowing']}, scrolling={metrics['scrolling']}")
        check(f"{width}px: cartões usam alinhamento responsivo",
              metrics["cards"] > 0 and metrics["alignments"] == [expected_alignment],
              f"cards={metrics['cards']}, alinhamentos={metrics['alignments']}, avatar={metrics['firstAvatarWidth']}px")
        image = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"author-card-{width}.png").write_bytes(base64.b64decode(image))

    summary = {"base": BASE, "path": PATH, "host": HOST, "results": results}
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    bidi.close()
    driver.close()

failed = [item for item in results if not item["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
