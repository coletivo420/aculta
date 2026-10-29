#!/usr/bin/env python3
"""Read-only Firefox ESR review of Gallery dialog and public albums."""

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
FORUM = "https://growroom420.aculta.toca.net.br"
ACCOUNT = "https://conta.aculta.toca.net.br"
NODE_ID = os.environ["SUITE_NODE_ID"]
OUT = Path(os.environ["SUITE_OUT"])
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
ALBUM = "/albuns/marcha-da-maconha-de-goiania-2025-registros-antiproibicionistas"
THEME_CSS = os.environ.get("SUITE_THEME_CSS")
THEME_CSS_CONTENT = Path(THEME_CSS).read_text() if THEME_CSS else None
results = []


def check(name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if not OUT.is_absolute():
    raise SystemExit("SUITE_OUT deve ser absoluto e fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)
os.environ["ACULTA_FF_PROFILE_ROOT"] = str(OUT / "firefox-profiles")
env = os.environ.copy()
env.pop("DISPLAY", None)
login = os.environ.get("SUITE_LOGIN_URL")
if not login:
    login = subprocess.run(
        [
            "php", "vendor/drush/drush/drush.php", "uli", "--uid=1",
            f"--uri={ACCOUNT}",
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


def wait_for(script, timeout=30):
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
    time.sleep(0.3)
    driver.js("""[...document.querySelectorAll('button.admin-toolbar__close-button')]
      .filter(button => button.getClientRects().length)
      .forEach(button => button.click());""")
    time.sleep(0.2)
    return int(driver.js("return window.innerWidth"))


def screenshot(name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{name}.png").write_bytes(base64.b64decode(data))


def navigate(url):
    driver.get(url)
    if THEME_CSS_CONTENT:
        driver.js("""const style = document.createElement('style');
          style.dataset.suiteThemeOverride = 'true';
          style.textContent = arguments[0];
          document.head.append(style);""", [THEME_CSS_CONTENT])


try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)
    navigate(login)
    check("login de uso único concluiu", wait_for("document.readyState === 'complete'"))

    if os.environ.get("SUITE_SKIP_EDITOR") != "1":
        navigate(f"{BASE}/node/{NODE_ID}/edit")
        editor_open = wait_for("!!document.querySelector('.ck-editor')")
        check("formulário descartável da Revista abriu", editor_open,
              driver.js("return location.pathname"))
        button = driver.js("""return [...document.querySelectorAll('button')]
      .map(button => ({
        label: button.getAttribute('aria-label') || button.title || button.innerText,
        className: button.className,
      }))
      .find(item => (item.label + ' ' + item.className).toLowerCase().includes('galeria')) || null;""")
        check("ação Criar galeria está disponível no editor", bool(button), button or "")
        if button:
            driver.js("""const button = [...document.querySelectorAll('button')].find(item =>
          ((item.getAttribute('aria-label') || item.title || item.innerText) +
           ' ' + item.className).toLowerCase().includes('galeria'));
          button?.click();""")
            dialog_open = wait_for("!!document.querySelector('#drupal-modal form input[name=\"title\"]')")
            form = driver.js("""return {
          modal: !!document.querySelector('#drupal-modal'),
          title: !!document.querySelector('#drupal-modal input[name="title"]'),
          images: !!document.querySelector('#drupal-modal [name^="images"]'),
          upload: !!document.querySelector('#drupal-modal input[type="file"]'),
          submit: !!document.querySelector('#drupal-modal form [type="submit"]'),
        };""") if dialog_open else {}
            check("diálogo mostra título, imagens, upload e ação", dialog_open and
              all(form.get(key) for key in ("title", "images", "upload", "submit")),
              str(form))
            for width, label in WIDTHS:
                actual = set_width(width)
                metrics = driver.js("""return {
              viewport: window.innerWidth,
              document: document.documentElement.scrollWidth,
              modal: (() => {
                const modal = document.querySelector('#drupal-modal');
                if (!modal) return 0;
                return Math.round(modal.getBoundingClientRect().width);
              })(),
              topBarActionsVisible: (() => {
                const actions = document.querySelector('.top-bar__actions');
                return !actions || actions.getBoundingClientRect().right <= window.innerWidth;
              })(),
              overflow: [...document.body.querySelectorAll('*')].map(element => {
                const rect = element.getBoundingClientRect();
                return {element, rect};
              }).filter(({element, rect}) => rect.width > 0 &&
                rect.right > window.innerWidth + 1 &&
                getComputedStyle(element).visibility !== 'hidden')
                .slice(0, 8).map(({element, rect}) => ({
                  tag: element.tagName,
                  className: String(element.className || '').slice(0, 100),
                  right: Math.round(rect.right),
                  text: (element.innerText || '').trim().slice(0, 70),
                })),
            };""")
                screenshot(f"dialog-{label}")
                check(f"diálogo sem overflow em {width}px",
                  actual == width and metrics["document"] <= width and
                  metrics["modal"] <= width and metrics["topBarActionsVisible"],
                  f"viewport={actual}, document={metrics['document']}, modal={metrics['modal']}, "
                  f"topBarActionsVisible={metrics['topBarActionsVisible']}")

    for host, label in ((BASE, "main"), (FORUM, "forum")):
        navigate(host + ALBUM)
        loaded = wait_for("!!document.querySelector('main h1') && !!document.querySelector('main img')")
        page = driver.js("""return {
          path: location.pathname,
          title: document.querySelector('main h1')?.innerText || '',
          images: document.querySelectorAll('main img').length,
        };""") if loaded else {}
        check(f"álbum público abriu em {label}", loaded and page.get("images", 0) > 0,
              f"{page.get('title', '')}; imagens={page.get('images', 0)}")
        for width, width_label in WIDTHS:
            actual = set_width(width)
            metrics = driver.js("""return {
              viewport: window.innerWidth,
              document: document.documentElement.scrollWidth,
              h1: (() => {
                const title = document.querySelector('main h1');
                if (!title) return false;
                const rect = title.getBoundingClientRect();
                return rect.left >= 0 && rect.right <= window.innerWidth;
              })(),
              images: document.querySelectorAll('main img').length,
              topBarActionsVisible: (() => {
                const actions = document.querySelector('.top-bar__actions');
                return !actions || actions.getBoundingClientRect().right <= window.innerWidth;
              })(),
              overflow: [...document.body.querySelectorAll('*')].map(element => {
                const rect = element.getBoundingClientRect();
                return {element, rect};
              }).filter(({element, rect}) => rect.width > 0 &&
                rect.right > window.innerWidth + 1 &&
                getComputedStyle(element).visibility !== 'hidden')
                .slice(0, 8).map(({element, rect}) => ({
                  tag: element.tagName,
                  className: String(element.className || '').slice(0, 100),
                  right: Math.round(rect.right),
                  text: (element.innerText || '').trim().slice(0, 70),
                })),
            };""")
            screenshot(f"album-{label}-{width_label}")
            check(f"álbum {label} reflow em {width}px",
                  actual == width and metrics["document"] <= width and metrics["h1"] and
                  metrics["images"] > 0 and metrics["topBarActionsVisible"],
                  f"viewport={actual}, document={metrics['document']}, h1={metrics['h1']}, "
                  f"images={metrics['images']}, topBarActionsVisible={metrics['topBarActionsVisible']}; "
                  f"overflow={metrics['overflow']}")
finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
