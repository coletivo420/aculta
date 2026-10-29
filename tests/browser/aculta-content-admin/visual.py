#!/usr/bin/env python3
"""Authenticated Firefox ESR review of the content moderation dashboard."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3]))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = "https://aculta.toca.net.br"
ACCOUNT = "https://conta.aculta.toca.net.br"
UID = int(os.environ.get("SUITE_UID", "5"))
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
        "php", "vendor/drush/drush/drush.php", "uli",
        f"--uid={UID}", f"--uri={ACCOUNT}",
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


def set_width(width):
    global bidi_id
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({"id": command_id, "method": "browsingContext.getTree", "params": {}}))
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
        "params": {"context": context, "viewport": {"width": width, "height": 900}},
    }))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    time.sleep(0.3)
    if width <= 767:
        driver.js("""localStorage.setItem('Drupal.navigation.sidebarExpanded', 'false');
          const toolbar = document.querySelector('#admin-toolbar');
          if (document.documentElement.dataset.adminToolbar === 'expanded') {
            toolbar?.querySelector('.admin-toolbar__close-button')?.click();
          }""")
        time.sleep(0.2)
    return int(driver.js("return window.innerWidth"))


def screenshot(name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"content-admin-{name}.png").write_bytes(base64.b64decode(data))


def press_tab():
    key = "\ue004"
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "content-admin-keyboard",
            "actions": [
                {"type": "keyDown", "value": key},
                {"type": "keyUp", "value": key},
            ],
        }],
    })


try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)
    driver.get(login)
    check("login temporário concluiu", wait_for("document.readyState === 'complete'"))

    dashboard_path = "/painel-administrativo/colecoes-moderaveis"
    driver.get(BASE + dashboard_path)
    loaded = wait_for("!!document.querySelector('main h1')")
    page = driver.js("""return {
      path: location.pathname,
      h1: document.querySelector('main h1')?.innerText || '',
      links: [...document.querySelectorAll('main .item-list a[href]')].map(link => ({
        text: link.innerText.trim(),
        href: link.pathname,
      })),
    };""") if loaded else {"path": "", "h1": "", "links": []}
    check("painel autenticado abriu", loaded and page["path"] == dashboard_path
          and page["h1"] == "Conteúdo que posso moderar")
    labels = [item["text"] for item in page["links"]]
    check("painel mostra somente coleções de Fórum acessíveis à conta",
          len(labels) > 0 and all(label.startswith(("Fórum", "Fóruns")) for label in labels)
          and not any("FAQ" in label for label in labels), str(labels))
    expected_links = driver.js("""return [...new Map(
      [...document.querySelectorAll('main .item-list a[href]')].map(link => {
        const key = link.innerText.trim() + '|' + link.href;
        return [key, {key, text: link.innerText.trim()}];
      })
    ).values()];""")
    focused = set()
    focus_visible = False
    for _ in range(max(60, len(expected_links) * 4)):
        press_tab()
        active = driver.js("""const element = document.activeElement;
          if (!element || !document.querySelector('main')?.contains(element)) return null;
          return {
            key: element.innerText.trim() + '|' + element.href,
            link: element.matches('a[href]'),
            visible: element.matches(':focus-visible'),
          };""")
        if active and active["link"]:
            focused.add(active["key"])
            focus_visible = focus_visible or active["visible"]
        if len(focused.intersection(link["key"] for link in expected_links)) == len(expected_links):
            break
    reached = focused.intersection(link["key"] for link in expected_links)
    check("coleções acessíveis percorrem Tab com indicador de foco",
          bool(expected_links) and len(reached) == len(expected_links) and focus_visible,
          f"links {len(reached)}/{len(expected_links)}, focus-visible={focus_visible}")
    driver.js("if (document.activeElement?.blur) document.activeElement.blur();")

    for width, label in WIDTHS:
        actual = set_width(width)
        metrics = driver.js("""return {
          viewport: window.innerWidth,
          document: document.documentElement.scrollWidth,
          h1Visible: (() => {
            const title = document.querySelector('main h1');
            if (!title) return false;
            const rect = title.getBoundingClientRect();
            return rect.left >= 0 && rect.right <= window.innerWidth;
          })(),
          linksVisible: [...document.querySelectorAll('main .item-list a[href]')].every(link => {
            const rect = link.getBoundingClientRect();
            return rect.left >= 0 && rect.right <= window.innerWidth;
          }),
        };""")
        screenshot(label)
        check(f"painel sem overflow em {width}px",
              actual == width and metrics["document"] <= width and metrics["h1Visible"]
              and metrics["linksVisible"], str(metrics))
finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
