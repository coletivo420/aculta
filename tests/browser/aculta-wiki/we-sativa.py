#!/usr/bin/env python3
"""Read-only visual smoke for WE SATIVA and its native Wiki authoring link."""

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
from wdlib import Driver, WDError  # noqa: E402

PAGE = os.environ.get(
    "WIKI_STUDIO_PAGE", "https://conta.aculta.toca.net.br/studio/we-sativa"
)
LOGIN_URI = os.environ.get("WIKI_STUDIO_LOGIN_URI", "https://conta.aculta.toca.net.br")
USER = os.environ.get("WIKI_STUDIO_USER", "teste.comum")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-wiki-we-sativa"))
OUT.mkdir(parents=True, exist_ok=True)
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

def one_time_login():
    drush_env = os.environ.copy()
    # Drush's interactive launcher blocks when X11 is inherited. Generate the
    # one-time URL in the CLI subprocess; Firefox itself still uses DISPLAY=:0.
    drush_env.pop("DISPLAY", None)
    result = subprocess.run(
        [
            "php", "vendor/drush/drush/drush.php", "uli",
            f"--name={USER}", f"--uri={LOGIN_URI}",
        ],
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
    end = time.time() + timeout
    while time.time() < end:
        if driver.js(script):
            return True
        time.sleep(0.25)
    return False


try:
    driver.get(login_url)
    time.sleep(1.5)
    driver.get(PAGE)
    page_loaded = wait_for(
        "[...document.querySelectorAll('h1')].some((el) => el.innerText.includes('WE SATIVA'))"
    )
    check("workspace WE SATIVA abre autenticado", page_loaded)
    if not page_loaded:
        diagnostic = driver.js("""return {
          path: location.pathname,
          title: document.title,
          headings: [...document.querySelectorAll('h1,h2')].map((el) => el.innerText.trim()).slice(0, 6),
          messages: [...document.querySelectorAll('[role="alert"], .messages')]
            .filter((el) => el.getClientRects().length)
            .map((el) => el.innerText.trim().slice(0, 120)).slice(0, 4),
        };""")
        check("rota do workspace", False, json.dumps(diagnostic, ensure_ascii=False))
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / "workspace-unavailable.png").write_bytes(base64.b64decode(screenshot))
        (OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
    else:
        metrics = driver.js("""return (() => {
          const main = document.querySelector('main, #main-content');
          const text = main?.innerText || '';
          const links = [...(main?.querySelectorAll('a') || [])];
          const create = links.find((a) => a.textContent.trim() === 'Criar verbete');
          const heading = [...(main?.querySelectorAll('h1') || [])]
            .find((el) => el.innerText.includes('WE SATIVA'));
          return {
            hasMain: !!main,
            heading: !!heading,
            workspaceClass: !!main?.querySelector('.we-sativa__workspace'),
            hasEntries: text.includes('Meus verbetes'),
            hasProposals: text.includes('Minhas propostas'),
            createHref: create?.href || '',
            unnamedLinks: links.filter((a) => !(a.innerText || '').trim()
              && !(a.getAttribute('aria-label') || '').trim()
              && !(a.getAttribute('title') || '').trim()).length,
          };
        })();""")
        check("página contextual tem main e h1 WE SATIVA", metrics["hasMain"] and metrics["heading"])
        check("área do workspace recebe token visual do tema", metrics["workspaceClass"])
        check("seções Meus verbetes e Minhas propostas presentes",
              metrics["hasEntries"] and metrics["hasProposals"])
        create_href = metrics["createHref"]
        check("ação Criar verbete aponta para a rota nativa", "/node/add/wiki_entry" in create_href,
              urlparse(create_href).path if create_href else "ausente")
        check("links de conteúdo têm nome acessível", metrics["unnamedLinks"] == 0,
              f"sem nome={metrics['unnamedLinks']}")

        for width, name in WIDTHS:
            actual = set_width(width)
            measure = driver.js("""return {
              viewport: window.innerWidth,
              document: document.documentElement.scrollWidth,
              body: document.body.scrollWidth,
              createVisible: [...document.querySelectorAll('main a')]
                .some((a) => a.textContent.trim() === 'Criar verbete'
                  && a.getClientRects().length > 0),
            };""")
            check(f"{name}: viewport solicitado", actual == width,
                  f"esperado={width}, medido={actual}")
            check(f"{name}: sem overflow horizontal",
                  measure["document"] <= width and measure["body"] <= width,
                  f"viewport={width}, document={measure['document']}, body={measure['body']}")
            check(f"{name}: criar verbete permanece visível", measure["createVisible"])
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"{name}.png").write_bytes(base64.b64decode(screenshot))

        if create_href:
            driver.get(create_href)
            returned = wait_for(
                "[...document.querySelectorAll('a')].some((a) => "
                "a.innerText.includes('Voltar para Meu Trabalho WE SATIVA'))"
            )
            check("formulário nativo oferece retorno autorizado ao workspace", returned)
            if returned:
                return_link = driver.js("""(() => {
                  const a = [...document.querySelectorAll('a')].find((el) =>
                    el.innerText.includes('Voltar para Meu Trabalho WE SATIVA'));
                  return {path: new URL(a.href).pathname, outlined: a.classList.contains('btn-outline-primary')};
                })();""")
                check("retorno leva ao workspace e recebe estilo do tema",
                      return_link["path"].endswith("/studio/we-sativa") and return_link["outlined"],
                      return_link["path"])
        (OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
finally:
    bidi.close()
    driver.close()

failed = [result for result in results if not result["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências em {OUT}")
sys.exit(1 if failed else 0)
