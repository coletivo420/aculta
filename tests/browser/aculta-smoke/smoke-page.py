#!/usr/bin/env python3
"""Read-only browser smoke: load URLs, capture screenshots, inspect basic layout."""

import json
import os
from pathlib import Path
import sys
import time
from urllib.parse import urlparse, urlunparse

ROOT = Path(__file__).resolve().parents[3]
os.umask(0o077)
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT fora do Git.")
OUT = Path(OUT_VALUE).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.chmod(OUT, 0o700)
BROWSER = os.environ.get("BROWSER", "firefox").lower()
HEADLESS = os.environ.get("HEADLESS") == "1"
if not HEADLESS and (BROWSER != "firefox" or os.environ.get("DISPLAY") != ":0"):
    raise SystemExit("Modo visível requer Firefox ESR e DISPLAY=:0; use HEADLESS=1 no automatizado.")

urls = sys.argv[1:]
if not urls:
    raise SystemExit("Uso: smoke-page.py URL [URL ...]")
for url in urls:
    parts = urlparse(url)
    if parts.scheme not in ("http", "https") or not parts.hostname:
        raise SystemExit(f"URL inválida: {url}")


def safe_url(value):
    parsed = urlparse(value)
    return urlunparse((parsed.scheme, parsed.netloc, parsed.path, "", "", ""))

driver = Driver(headless=HEADLESS, browser=BROWSER)
results = []
try:
    for index, url in enumerate(urls, start=1):
        started = time.monotonic()
        driver.get(url)
        for _ in range(60):
            if driver.js("return document.readyState === 'complete'"):
                break
            time.sleep(0.25)
        result = driver.js("""return {url:location.href,title:document.title,readyState:document.readyState,
status:performance.getEntriesByType('navigation')[0]?.responseStatus||null,
viewport:{width:innerWidth,height:innerHeight},documentWidth:document.documentElement.scrollWidth,
horizontalOverflow:document.documentElement.scrollWidth>innerWidth,
landmarks:{main:document.querySelectorAll('main,[role=main]').length,nav:document.querySelectorAll('nav,[role=navigation]').length},
bodyClasses:Array.from(document.body.classList)};""")
        result["durationSeconds"] = round(time.monotonic() - started, 3)
        result["url"] = safe_url(result["url"])
        result["requestedUrl"] = safe_url(url)
        shot = driver.req("GET", f"/session/{driver.session}/screenshot")
        import base64
        (OUT / f"page-{index:02d}.png").write_bytes(base64.b64decode(shot))
        results.append(result)
        print(json.dumps(result, ensure_ascii=False), flush=True)
finally:
    driver.close()

(OUT / "results.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
sys.exit(1 if any(
    item["readyState"] != "complete"
    or (item["status"] is not None and not 200 <= item["status"] < 400)
    for item in results
) else 0)
