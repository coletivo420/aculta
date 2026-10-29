#!/usr/bin/env python3
"""Read-only Firefox check for the authenticated Account shell's responsive navigation."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", str(HERE.parents[2])))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("ACCOUNT_BASE_URL", "https://conta.aculta.toca.net.br").rstrip("/")
URI = os.environ.get("ACCOUNT_DRUSH_URI", BASE)
UID = os.environ.get("ACCOUNT_TEST_UID", "5")
OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT para guardar PNG e JSON fora do repositório.")
OUT = Path(OUT_VALUE)
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))

WIDTHS = [1440, 1280, 1024, 768, 767, 600, 599, 390, 360, 320]
PATH = "/configuracoes"
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


def login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    proc = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", f"--uid={UID}", f"--uri={URI}"],
        cwd=PROJECT, check=True, capture_output=True, text=True, timeout=30, env=env,
    )
    return proc.stdout.strip().splitlines()[-1]


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
    time.sleep(0.2)
    return int(driver.js("return window.innerWidth"))


CSS_CANDIDATE = None
if os.environ.get("ACCOUNT_CSS_OVERRIDE") == "1":
    css_path = HERE.parents[2] / "web/themes/custom/aculta420/css/components/portal-account.css"
    CSS_CANDIDATE = css_path.read_text()


def apply_candidate_css():
    if CSS_CANDIDATE is not None:
        driver.js("""const style = document.createElement('style');
          style.dataset.accountCssCandidate = 'true';
          style.textContent = arguments[0];
          document.head.append(style);
          return true;""", [CSS_CANDIDATE])


try:
    driver.get(login_url())
    driver.get(f"{BASE}{PATH}")
    time.sleep(1.2)
    apply_candidate_css()
    time.sleep(0.2)
    initial = driver.js("""return {
      path: location.pathname,
      hasAccount: !!document.querySelector('[data-portal-account-layout]'),
      desktopLinks: document.querySelectorAll('.portal-account__menu-desktop a').length,
      mobileLinks: document.querySelectorAll('.portal-account__accordion .portal-account__item').length
        + document.querySelectorAll('.portal-account__accordion .portal-account__logout-row').length,
      institutionBarCount: document.querySelectorAll('.aculta-institution-bar').length
    };""")
    check("menu autenticado da Conta renderizou", initial["hasAccount"] and initial["path"] == PATH,
          initial["path"])
    check("menu desktop e sanfona mantêm as mesmas seções",
          initial["desktopLinks"] > 0 and initial["desktopLinks"] == initial["mobileLinks"],
          f"desktop={initial['desktopLinks']}, mobile={initial['mobileLinks']}")
    check("Institution Bar preservada", initial["institutionBarCount"] == 1,
          f"count={initial['institutionBarCount']}")

    for width in WIDTHS:
        actual = set_width(width)
        driver.get(f"{BASE}{PATH}?aculta_check={int(time.time())}-{width}")
        time.sleep(0.7)
        apply_candidate_css()
        metrics = driver.js("""return (() => {
          const layout = document.querySelector('.portal-account__layout');
          const nav = document.querySelector('.portal-account__nav');
          const desktop = document.querySelector('.portal-account__menu-desktop');
          const accordion = document.querySelector('.portal-account__accordion');
          return {
            width: window.innerWidth,
            document: document.documentElement.scrollWidth,
            columns: getComputedStyle(layout).gridTemplateColumns.trim().split(/\\s+/).length,
            navPosition: getComputedStyle(nav).position,
            desktopMenu: getComputedStyle(desktop).display,
            accordion: getComputedStyle(accordion).display,
            sections: document.querySelectorAll('.portal-account__menu-desktop a').length
          };
        })();""")
        mobile = width < 768
        check(f"{width}px: viewport configurado", actual == width, f"medido={actual}")
        check(f"{width}px: sem overflow", metrics["document"] <= width,
              f"viewport={width}, document={metrics['document']}")
        check(f"{width}px: menu contextual correto",
              metrics["sections"] > 0
              and metrics["desktopMenu"] == ("none" if mobile else "block")
              and metrics["accordion"] == ("block" if mobile else "none")
              and metrics["columns"] == (1 if mobile else 2)
              and metrics["navPosition"] == ("static" if mobile else "sticky"),
              json.dumps(metrics, ensure_ascii=False))
        image = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"account-shell-{width}.png").write_bytes(base64.b64decode(image))

    summary = {"base": BASE, "path": PATH, "results": results}
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    bidi.close()
    driver.close()

failed = [item for item in results if not item["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
