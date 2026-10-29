#!/usr/bin/env python3
"""Read-only Firefox ESR review for the ACULTA Patcher admin screens."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

from websockets.sync.client import connect

PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3]))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE_URL = "https://aculta.toca.net.br"
LOGIN_URI = "https://conta.aculta.toca.net.br"
LIST_PATH = "/painel-administrativo/configuracoes/desenvolvimento/patches-aculta"
ADD_PATH = LIST_PATH + "/adicionar"
OUT = Path(os.environ["SUITE_OUT"])
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
results = []


def check(name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if not OUT.is_absolute():
    raise SystemExit("SUITE_OUT deve ser um caminho absoluto fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)

env = os.environ.copy()
env.pop("DISPLAY", None)
login = subprocess.run(
    [
        "php",
        "vendor/drush/drush/drush.php",
        "uli",
        "--uid=1",
        f"--uri={LOGIN_URI}",
    ],
    cwd=PROJECT,
    env=env,
    check=True,
    capture_output=True,
    text=True,
    timeout=30,
).stdout.strip().splitlines()[-1]

driver = None
bidi = None
bidi_id = 0


def wait_for(script, timeout=25):
    end = time.monotonic() + timeout
    while time.monotonic() < end:
        try:
            if driver.js("return " + script):
                return True
        except Exception:
            pass
        time.sleep(0.25)
    return False


def close_mobile_admin_menu(width):
    if width > 767:
        return
    visible = driver.js("""const button = document.querySelector('button.admin-toolbar__close-button');
      return !!button && button.getClientRects().length > 0;""")
    if visible:
        driver.js("document.querySelector('button.admin-toolbar__close-button')?.click()")
        time.sleep(0.25)


try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)

    def set_width(width):
        global bidi_id
        bidi_id += 1
        command_id = bidi_id
        bidi.send(json.dumps({
            "id": command_id,
            "method": "browsingContext.getTree",
            "params": {},
        }))
        while True:
            response = json.loads(bidi.recv())
            if response.get("id") == command_id:
                break
        context = response["result"]["contexts"][0]["context"]
        bidi_id += 1
        command_id = bidi_id
        bidi.send(json.dumps({
            "id": command_id,
            "method": "browsingContext.setViewport",
            "params": {
                "context": context,
                "viewport": {"width": width, "height": 900},
            },
        }))
        while True:
            response = json.loads(bidi.recv())
            if response.get("id") == command_id:
                break
        time.sleep(0.25)
        return int(driver.js("return window.innerWidth"))

    driver.get(login)
    if not wait_for("document.readyState === 'complete'"):
        check("login de uso único concluiu", False, "timeout")
    else:
        driver.get(BASE_URL + LIST_PATH)
        loaded = wait_for("!!document.querySelector('main h1')")
        page = driver.js("""return {
          title: document.title,
          path: location.pathname,
          h1: document.querySelector('main h1')?.innerText || '',
          h1Count: document.querySelectorAll('main h1').length,
          table: !!document.querySelector('main table'),
          patchRow: [...document.querySelectorAll('main tr')].some(row =>
            row.innerText.includes('H5P: bloquear tipos de conteúdo restritos no upload')),
          addLink: [...document.querySelectorAll('main a')].some(link =>
            link.pathname.endsWith('/adicionar')),
        };""") if loaded else {}
        check("listagem administrativa abriu", loaded and page.get("path") == LIST_PATH,
              page.get("h1", "sem h1"))
        check("listagem tem um título e uma tabela", page.get("h1Count") == 1 and page.get("table"))
        check("registro H5P ativo está visível", page.get("patchRow", False))
        check("link para adicionar registro está visível", page.get("addLink", False))

        for width, label in WIDTHS:
            actual = set_width(width)
            close_mobile_admin_menu(width)
            metrics = driver.js("""return {
              viewport: window.innerWidth,
              document: document.documentElement.scrollWidth,
              body: document.body.scrollWidth,
              mainTitleVisible: (() => {
                const title = document.querySelector('main h1');
                if (!title) return false;
                const rect = title.getBoundingClientRect();
                const topElement = document.elementFromPoint(rect.left + 2, rect.top + 2);
                return rect.left >= 0 && rect.right <= window.innerWidth &&
                  (topElement === title || title.contains(topElement));
              })(),
            };""")
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"list-{label}.png").write_bytes(base64.b64decode(screenshot))
            check(f"listagem visível e sem overflow em {width}px",
                  actual == width and metrics["document"] <= width and metrics["mainTitleVisible"],
                  f"viewport={actual}, document={metrics['document']}, titleVisible={metrics['mainTitleVisible']}")

        driver.get(BASE_URL + ADD_PATH)
        form_loaded = wait_for("!!document.querySelector('main form')")
        form = driver.js("""return {
          path: location.pathname,
          h1Count: document.querySelectorAll('main h1').length,
          label: !!document.querySelector('main input[name="label"]'),
          package: !!document.querySelector('main input[name="package"]'),
          version: !!document.querySelector('main input[name="version"]'),
          diff: !!document.querySelector('main textarea[name="patch"]'),
          submit: !!document.querySelector('main form [type="submit"]'),
        };""") if form_loaded else {}
        check("formulário de inclusão abriu sem envio", form_loaded and form.get("path") == ADD_PATH)
        check("formulário expõe rótulo, pacote, versão e diff",
              all(form.get(key, False) for key in ("label", "package", "version", "diff")))
        check("formulário tem um h1", form.get("h1Count") == 1)

        for width, label in WIDTHS:
            actual = set_width(width)
            close_mobile_admin_menu(width)
            metrics = driver.js("""return {
              document: document.documentElement.scrollWidth,
              mainTitleVisible: (() => {
                const title = document.querySelector('main h1');
                if (!title) return false;
                const rect = title.getBoundingClientRect();
                const topElement = document.elementFromPoint(rect.left + 2, rect.top + 2);
                return rect.left >= 0 && rect.right <= window.innerWidth &&
                  (topElement === title || title.contains(topElement));
              })(),
            };""")
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"form-{label}.png").write_bytes(base64.b64decode(screenshot))
            check(f"formulário visível e sem overflow em {width}px",
                  actual == width and metrics["document"] <= width and metrics["mainTitleVisible"],
                  f"viewport={actual}, document={metrics['document']}, titleVisible={metrics['mainTitleVisible']}")
finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
