#!/usr/bin/env python3
"""Smoke visual do painel e exportações do ACULTA QR Code."""

import base64
import json
import os
from pathlib import Path
import sys
import tempfile
import time
from urllib.parse import quote

TEST_ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(TEST_ROOT / "tests/browser/aculta-cpf-checkout"))
import wdlib  # noqa: E402


def require_env(name):
    value = os.environ.get(name)
    if not value:
        raise SystemExit(f"Defina {name}.")
    return value


def main():
    display = os.environ.get("DISPLAY")
    if display != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
    output = Path(require_env("SUITE_OUT"))
    output.mkdir(mode=0o700, parents=True, exist_ok=True)
    entity_id = require_env("ACULTA_QR_ID")
    code = require_env("ACULTA_QR_CODE")
    base = "https://aculta.toca.net.br"
    login = require_env("ACULTA_TEST_LOGIN_URL")
    if not login.startswith("https://conta.aculta.toca.net.br/"):
        raise SystemExit("ACULTA_TEST_LOGIN_URL deve apontar para o domínio de Conta de teste.")

    original_mkdtemp = tempfile.mkdtemp

    def suite_mkdtemp(prefix=None, suffix=None, dir=None):
        return original_mkdtemp(prefix=prefix, suffix=suffix, dir=output)

    tempfile.mkdtemp = suite_mkdtemp
    results = []
    driver = None
    try:
        driver = wdlib.Driver(prefs={"browser.download.folderList": 2})
        driver.get(login)
        driver.get(base + "/painel-administrativo/aculta/codigos-qr")
        time.sleep(2)
        panel = driver.js("return document.body.innerText")
        results.append(("painel carregado", "Códigos QR" in panel or "códigos QR" in panel))
        results.append(("fixture aparece no painel", "[TESTE] QR Code de homologação" in panel))

        for width in (1280, 390, 320):
            driver.req(
                "POST",
                f"/session/{driver.session}/window/rect",
                {"width": width, "height": 1000},
            )
            time.sleep(0.5)
            dimensions = driver.js(
                "return {viewport: innerWidth, document: document.documentElement.scrollWidth}"
            )
            ok = dimensions["document"] <= dimensions["viewport"]
            results.append((f"sem overflow em {width}px", ok))
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (output / f"panel-{width}.png").write_bytes(base64.b64decode(screenshot))

        preview_url = base + "/painel-administrativo/aculta/codigos-qr/previa?url=" + quote(
            "https://example.invalid/qa-qr", safe=""
        )
        driver.get(preview_url)
        time.sleep(1)
        preview = driver.js("return {type: document.contentType, text: document.documentElement.outerHTML}")
        results.append(("prévia SVG servida", preview["type"] == "image/svg+xml" and "<svg" in preview["text"]))

        driver.get(base + "/painel-administrativo/aculta/codigos-qr")
        formats = driver.js(
            """const formats = ['svg', 'png', 'webp', 'pdf'];
            return Promise.all(formats.map(async format => {
              const response = await fetch(`/painel-administrativo/aculta/codigos-qr/${arguments[0]}/baixar/${format}`);
              const body = await response.arrayBuffer();
              return {format, status: response.status, type: response.headers.get('content-type'),
                cache: response.headers.get('cache-control'), bytes: body.byteLength};
            }));""",
            [entity_id],
        )
        for item in formats:
            expected = {
                "svg": "image/svg+xml",
                "png": "image/png",
                "webp": "image/webp",
                "pdf": "application/pdf",
            }[item["format"]]
            ok = item["status"] == 200 and item["type"].startswith(expected) and item["bytes"] > 100
            results.append((f"download {item['format']}", ok))
            if not ok:
                print(f"DIAGNOSTIC {item['format']}: {json.dumps(item, ensure_ascii=False)}")

        try:
            driver.get("https://links.aculta.toca.net.br/qr/" + code)
        except wdlib.WDError:
            # The reserved example.invalid host must fail DNS after following
            # the public redirect; the WebDriver still exposes its neterror URL.
            pass
        time.sleep(1)
        redirected = "example.invalid%2Fqa-qr" in driver.url() or driver.url().startswith(
            "https://example.invalid/qa-qr"
        )
        results.append(("redirecionamento público usa o destino da fixture", redirected))
    finally:
        if driver:
            driver.close()
        tempfile.mkdtemp = original_mkdtemp

    (output / "summary.json").write_text(
        json.dumps([{"name": name, "passed": passed} for name, passed in results], ensure_ascii=False, indent=2)
        + "\n",
        encoding="utf-8",
    )
    for name, passed in results:
        print(("PASS" if passed else "FAIL") + " " + name)
    failed = sum(not passed for _, passed in results)
    print(f"Resultado: {len(results) - failed}/{len(results)} ok")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
