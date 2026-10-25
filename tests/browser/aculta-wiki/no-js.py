#!/usr/bin/env python3
"""Read-only no-JavaScript check for the public Wiki pages.

Loads the public Wiki in Firefox ESR with JavaScript disabled and inspects the
server-rendered HTML source. It makes no writes and does not authenticate.
"""

import os
import re
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver, WDError  # noqa: E402

BASE = os.environ.get("WIKI_BASE_URL", "https://wiki420.aculta.toca.net.br").rstrip("/")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-wiki-no-js"))
OUT.mkdir(parents=True, exist_ok=True)
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(prefs={"javascript.enabled": False})
try:
    # A unique query arg avoids serving a page-cache entry rendered before the
    # current code was checked out; the script still performs only GETs.
    stamp = str(int(time.time()))
    for name, path in [
        ("home", f"/?aculta_check={stamp}"),
        ("listing", f"/wiki/verbetes?aculta_check={stamp}"),
        ("search", f"/wiki/busca?q=maconha&aculta_check={stamp}"),
    ]:
        try:
            driver.get(BASE + path)
        except WDError as error:
            check(f"{name}: navegação", False, error)
            continue
        source = driver.req("GET", f"/session/{driver.session}/source")
        html = source if isinstance(source, str) else str(source)
        check(f"{name}: HTML servido sem JavaScript", bool(html.strip()))
        check(f"{name}: um único h1 no HTML", html.count("<h1") == 1,
              f"encontrados={html.count('<h1')}")
        (OUT / f"{name}.html").write_text(html, encoding="utf-8")

    search_source = (OUT / "search.html").read_text(encoding="utf-8")
    check("busca: formulário nativo com campo q",
          'name="q"' in search_source and "<form" in search_source)
    check("busca: resultados renderizados no servidor",
          "verbetes encontrados" in search_source or "verbete encontrado" in search_source,
          "contagem de resultados presente" if "encontrados" in search_source else "sem contagem")
    check("busca: resultados trazem links para verbetes",
          search_source.count("/verbete/") >= 1,
          f"links={search_source.count('/verbete/')}")

    listing_source = (OUT / "listing.html").read_text(encoding="utf-8")
    check("listagem: conteúdo principal presente", "<main" in listing_source)
    check("listagem: nenhuma mensagem de erro visível",
          "messages--error" not in listing_source)

    # The canonical entry page is the primary public artifact.
    match = re.search(r'href="(/verbete/[^"]+)"', search_source)
    if match:
        driver.get(BASE + match.group(1) + "?aculta_check=" + stamp)
        entry_source = driver.req("GET", f"/session/{driver.session}/source")
        entry_html = entry_source if isinstance(entry_source, str) else str(entry_source)
        check("verbete: HTML servido sem JavaScript", bool(entry_html.strip()))
        check("verbete: um único h1 no HTML", entry_html.count("<h1") == 1,
              f"encontrados={entry_html.count('<h1')}")
        heading = re.search(r"<h1[^>]*>(.*?)</h1>", entry_html, re.S)
        heading_text = re.sub(r"<[^>]+>", "", heading.group(1)).strip() if heading else ""
        check("verbete: h1 contém o título", bool(heading_text), heading_text)
        check("verbete: conteúdo principal presente", "<main" in entry_html)
        check("verbete: sem mensagem de erro visível",
              "messages--error" not in entry_html
              and "erro inesperado" not in entry_html.lower())
        (OUT / "entry.html").write_text(entry_html, encoding="utf-8")
    else:
        check("verbete: link canônico encontrado na busca", False)
finally:
    driver.close()

failed = [result for result in results if not result["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências em {OUT}")
sys.exit(1 if failed else 0)
