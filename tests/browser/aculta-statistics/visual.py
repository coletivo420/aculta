#!/usr/bin/env python3
"""Authenticated Firefox ESR visual review of ACULTA Statistics dashboards."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import urlsplit

from websockets.sync.client import connect

PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3]))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = "https://aculta.toca.net.br"
ACCOUNT = "https://conta.aculta.toca.net.br"
EXPECTED_PROJECT = Path("/home/piradopirata/projetos/aculta").resolve()
UID = 1
OUT = Path(os.environ["SUITE_OUT"])
PREVIEW_CSS = os.environ.get("STATISTICS_CSS_PREVIEW")
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
AREAS = ["", "vendas", "email", "lms", "links", "forum", "midia", "wiki"]
results = []


def check(name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""), flush=True)


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if PROJECT.resolve() != EXPECTED_PROJECT:
    raise SystemExit("ACULTA_PROJECT_ROOT deve apontar para o checkout do servidor de desenvolvimento.")
if not OUT.is_absolute() or OUT.resolve().is_relative_to(PROJECT.resolve()):
    raise SystemExit("SUITE_OUT deve ser absoluto e ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)
os.environ["ACULTA_FF_PROFILE_ROOT"] = str(OUT / "firefox-profiles")
preview_css = Path(PREVIEW_CSS).read_text() if PREVIEW_CSS else None

drush_environment = os.environ.copy()
drush_environment.pop("DISPLAY", None)
login = subprocess.run(
    [
        "vendor/bin/drush", "--root=web", "uli",
        f"--uid={UID}", f"--uri={ACCOUNT}",
    ],
    cwd=PROJECT,
    env=drush_environment,
    check=True,
    capture_output=True,
    text=True,
    timeout=90,
).stdout.strip().splitlines()[-1]
login_parts = urlsplit(login)
if login_parts.scheme != "https" or login_parts.hostname != "conta.aculta.toca.net.br":
    raise SystemExit("O link único não pertence ao host de Conta do ambiente de desenvolvimento.")

driver = None
bidi = None
bidi_id = 0


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
          document.querySelector('#admin-toolbar .admin-toolbar__close-button')?.click();""")
        time.sleep(0.2)
    return int(driver.js("return window.innerWidth"))


def screenshot(name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"statistics-{name}.png").write_bytes(base64.b64decode(data))


def press_tab():
    key = "\ue004"
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "statistics-keyboard",
            "actions": [{"type": "keyDown", "value": key}, {"type": "keyUp", "value": key}],
        }],
    })


try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)
    driver.get(login)
    check("login administrativo concluiu", driver.js("return document.readyState === 'complete'"))

    route_results = []
    for area in AREAS:
        path = "/painel-administrativo/aculta/estatisticas"
        if area:
            path += "/area/" + area
        driver.get(BASE + path)
        if preview_css:
            driver.js("""const style = document.createElement('style');
              style.dataset.statisticsCssPreview = 'true';
              style.textContent = arguments[0];
              document.head.append(style);""", [preview_css])
        page = driver.js("""return {
          path: location.pathname,
          h1: document.querySelector('main h1')?.innerText || '',
          title: document.title,
          nav: !!document.querySelector('main [data-portal-ui="statistics-areas"]'),
          overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        };""")
        passed = page["path"] == path and "Estatística" in (page["h1"] + page["title"]) and page["nav"]
        route_results.append(passed)
        check(f"rota de painel acessível ({area or 'todas'})", passed,
              f"h1={page['h1']!r}, nav={page['nav']}, overflow={page['overflow']}")
        if area == "":
            expected = driver.js("""return [...new Map(
              [...document.querySelectorAll('main a[href]')].map(link => [link.href, link.href])
            ).values()];""")
            focused = set()
            focus_visible = False
            for _ in range(80):
                press_tab()
                item = driver.js("""const element = document.activeElement;
                  if (!element || !document.querySelector('main')?.contains(element)) return null;
                  return {href: element.href, link: element.matches('a[href]'), visible: element.matches(':focus-visible')};""")
                if item and item["link"]:
                    focused.add(item["href"])
                    focus_visible = focus_visible or item["visible"]
                if len(focused.intersection(expected)) == len(expected):
                    break
            check("links do painel alcançáveis por Tab com indicador de foco",
                  bool(expected) and len(focused.intersection(expected)) == len(expected) and focus_visible,
                  f"links {len(focused.intersection(expected))}/{len(expected)}, focus-visible={focus_visible}")
            driver.js("if (document.activeElement?.blur) document.activeElement.blur();")

        for width, label in WIDTHS:
            actual = set_width(width)
            metrics = driver.js("""const main = document.querySelector('main');
              const heading = main?.querySelector('h1');
              const rect = heading?.getBoundingClientRect();
              return {
                documentWidth: document.documentElement.scrollWidth,
                headingVisible: !!rect && rect.left >= 0 && rect.right <= innerWidth,
                mainPresent: !!main,
                offenders: [...document.querySelectorAll('body *')].map(element => {
                  const box = element.getBoundingClientRect();
                  return {element, box};
                }).filter(item => item.box.width > 0 && item.box.right > innerWidth + 1)
                  .slice(0, 8).map(({element, box}) => ({
                    tag: element.tagName.toLowerCase(), id: element.id,
                    className: typeof element.className === 'string' ? element.className : '',
                    name: element.getAttribute('name'),
                    left: Math.round(box.left), right: Math.round(box.right),
                    width: Math.round(box.width), scrollWidth: element.scrollWidth,
                  })),
              };""")
            screenshot(f"{area or 'todas'}-{label}")
            check(f"{area or 'todas'} sem overflow em {width}px",
                  actual == width and metrics["documentWidth"] <= width and metrics["headingVisible"] and metrics["mainPresent"],
                  str(metrics))
finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
