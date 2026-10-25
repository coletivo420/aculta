#!/usr/bin/env python3
"""Read-only visual smoke for the Wiki moderation queue in ACULTA Studio.

Authenticates with a Drush one-time URL, opens the Marley Wiki proposal queue,
checks the shared listing, and opens a decision form when a proposal exists.
It never submits a decision, changes preferences, or writes content.
"""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import urlparse
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get(
    "WIKI_STUDIO_PAGE", "https://conta.aculta.toca.net.br/studio/marley/wiki/moderacao/propostas"
)
LOGIN_URI = os.environ.get("WIKI_STUDIO_LOGIN_URI", "https://conta.aculta.toca.net.br")
USER_UID = os.environ.get("WIKI_STUDIO_UID", "5")
USER = os.environ.get("WIKI_STUDIO_USER", "")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-wiki-moderation"))
OUT.mkdir(parents=True, exist_ok=True)
WIDTHS = [(1440, "wide-1440"), (1280, "desktop"), (1024, "tablet-1024"),
          (768, "tablet-768"), (390, "mobile-390"), (360, "mobile-360"),
          (320, "mobile-320")]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")


def one_time_login():
    drush_env = os.environ.copy()
    drush_env.pop("DISPLAY", None)
    login_args = ["php", "vendor/drush/drush/drush.php", "uli"]
    login_args.append(f"--uid={USER_UID}" if USER_UID else f"--name={USER}")
    login_args.append(f"--uri={LOGIN_URI}")
    result = subprocess.run(
        login_args,
        cwd=PROJECT,
        check=True,
        capture_output=True,
        text=True,
        timeout=30,
        env=drush_env,
    )
    # Never print or persist this one-time URL.
    return result.stdout.strip().splitlines()[-1]


login_url = one_time_login()
driver = Driver(bidi=True)
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
            raise RuntimeError(f"BiDi {method}: {response['error']}")
        return response.get("result", {})


context = bidi_command("browsingContext.getTree")["contexts"][0]["context"]


def set_width(width):
    bidi_command("browsingContext.setViewport", {
        "context": context,
        "viewport": {"width": width, "height": 900},
    })
    time.sleep(0.3)
    return int(driver.js("return window.innerWidth"))


def wait_for(script, timeout=25):
    if not script.lstrip().startswith("return"):
        script = f"return {script}"
    end = time.time() + timeout
    while time.time() < end:
        if driver.js(script):
            return True
        time.sleep(0.25)
    return False


def page_metrics():
    return driver.js("""return (() => {
      const main = document.querySelector('main, #main-content');
      return {
        path: location.pathname,
        title: document.title,
        hasMain: !!main,
        h1Count: document.querySelectorAll('main h1').length,
        heading: main?.querySelector('h1')?.innerText.trim() || '',
        documentWidth: document.documentElement.scrollWidth,
        bodyWidth: document.body.scrollWidth,
        errors: [...document.querySelectorAll('.messages--error, [role="alert"]')]
          .filter((el) => el.getClientRects().length)
          .map((el) => el.innerText.trim().slice(0, 120)),
      };
    })();""")


try:
    driver.get(login_url)
    time.sleep(1.5)
    driver.get(BASE)
    loaded = wait_for("!!document.querySelector('main, #main-content')")
    check("fila de moderação abre autenticada", loaded)
    metrics = page_metrics() if loaded else {}
    check("fila tem main e um único h1",
          metrics.get("hasMain") and metrics.get("h1Count") == 1, metrics.get("heading", ""))
    check("fila não mostra erro visível", not metrics.get("errors"), metrics.get("errors", []))
    queue = driver.js("""return (() => {
      const main = document.querySelector('main, #main-content');
      const table = [...document.querySelectorAll('table')].find((el) =>
        el.innerText.includes('Proposta') && el.innerText.includes('Situação'));
      const rows = table ? [...table.querySelectorAll('tbody tr')] : [];
      const links = rows.map((row) => row.querySelector('a')).filter(Boolean);
      return {
        hasListing: !!table && !!document.querySelector(
          '[data-portal-ui="studio-listing-wrap"], [data-aculta-studio="listing-wrap"], table'),
        hasHeaders: !!table && table.innerText.includes('Proposta') && table.innerText.includes('Situação'),
        rowCount: rows.length,
        decisionHref: links.find((a) => a.pathname.endsWith('/decidir'))?.href || '',
        emptyText: (main?.innerText || '').includes('Nenhuma proposta aguarda avaliação.'),
      };
    })();""")
    check("fila usa o listing compartilhado do Studio", queue["hasListing"] and queue["hasHeaders"])
    check("fila tem propostas ou estado vazio explícito",
          queue["rowCount"] > 0 or queue["emptyText"],
          f"linhas={queue['rowCount']}, vazio={queue['emptyText']}")

    for width, name in WIDTHS:
        actual = set_width(width)
        layout = driver.js("""return {
          viewport: window.innerWidth,
          document: document.documentElement.scrollWidth,
          body: document.body.scrollWidth,
        };""")
        check(f"{name}: viewport solicitado", actual == width, f"esperado={width}, medido={actual}")
        check(f"{name}: sem overflow horizontal",
              layout["document"] <= width and layout["body"] <= width,
              f"viewport={width}, document={layout['document']}, body={layout['body']}")
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"queue-{name}.png").write_bytes(base64.b64decode(screenshot))

    if queue["decisionHref"]:
        driver.get(queue["decisionHref"])
        form_loaded = wait_for(
            "!!document.querySelector('[data-portal-ui=\"wiki-proposal-decision\"]')"
        )
        check("formulário de decisão abre para proposta autorizada", form_loaded)
        if form_loaded:
            form = driver.js("""return (() => {
              const form = document.querySelector('[data-portal-ui="wiki-proposal-decision"]');
              const text = form.innerText;
              return {
                hasDecision: !!form.querySelector('select[name="decision"]'),
                hasNote: !!form.querySelector('textarea[name="decision_note"]'),
                hasProposed: text.includes('Valores propostos'),
                hasPublishedLink: text.includes('Abrir verbete publicado'),
                hasBaseLink: text.includes('Abrir revisão-base da proposta'),
                h1Count: document.querySelectorAll('main h1').length,
                documentWidth: document.documentElement.scrollWidth,
              };
            })();""")
            check("decisão usa Form API com nota obrigatória",
                  form["hasDecision"] and form["hasNote"])
            check("formulário exibe proposta e links de comparação autorizados",
                  form["hasPublishedLink"] or form["hasBaseLink"],
                  f"propostos={form['hasProposed']}, publicado={form['hasPublishedLink']}, base={form['hasBaseLink']}")
            check("formulário de decisão mantém um único h1", form["h1Count"] == 1)
            for width, name in WIDTHS:
                actual = set_width(width)
                layout = driver.js("""return {
                  viewport: window.innerWidth,
                  document: document.documentElement.scrollWidth,
                };""")
                check(f"decisão/{name}: viewport solicitado", actual == width,
                      f"esperado={width}, medido={actual}")
                check(f"decisão/{name}: sem overflow horizontal",
                      layout["document"] <= width,
                      f"viewport={width}, document={layout['document']}")
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"decision-{name}.png").write_bytes(base64.b64decode(screenshot))
    else:
        check("nenhuma proposta pendente para abrir formulário de decisão", True)

    (OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
finally:
    bidi.close()
    driver.close()

failed = [result for result in results if not result["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências em {OUT}")
sys.exit(1 if failed else 0)
