#!/usr/bin/env python3
"""UI Library: seletor Claro/Escuro em todos os componentes, Firefox ESR visível.

Entra com uma conta administrativa de teste (uli), abre a biblioteca de componentes,
confere o seletor, muda para Escuro e para Claro, e captura 320 e 1440 px em cada modo.
Não envia formulários e não altera conteúdo. Capturas e manifest.json vão para OUT.
"""

import base64
import json
import os
import subprocess
import sys
import time
from datetime import date
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[4]
DRUPAL_ROOT = Path(os.environ.get("ACULTA_DRUPAL_ROOT", "/home/aculta")).resolve()
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ.get(
    "SUITE_OUT",
    ROOT / "tests/browser/capturas/botoes-modo-cor" / date.today().isoformat(),
)).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT.parent / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Use Firefox ESR visível em DISPLAY=:0.")

ADMIN_UID = os.environ.get("ACULTA_ADMIN_TEST_UID", "257")
DEPLOY = json.loads(
    (ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json").read_text()
)
TEST_HOSTS = {item["domain_id"]: item["test_host"] for item in DEPLOY["domain_transition"]["entries"]}
MAIN = f"https://{TEST_HOSTS['aculta_org']}"
ACCOUNT = f"https://{TEST_HOSTS['conta_aculta_org']}"
LIBRARY = f"{MAIN}/painel-administrativo/aparencia/ui/components"
WIDTHS = (320, 1440)
MODES = ("light", "dark")


def one_time_login_url():
    process = subprocess.run(
        ["php", str(DRUPAL_ROOT / "vendor/drush/drush/drush.php"), "uli", "/", "--no-browser",
         f"--uid={ADMIN_UID}", f"--uri={ACCOUNT}"],
        cwd=DRUPAL_ROOT, check=True, capture_output=True, text=True, timeout=60,
    )
    generated = urlsplit(process.stdout.strip().splitlines()[-1])
    account = urlsplit(ACCOUNT)
    return urlunsplit((account.scheme, account.netloc, generated.path, generated.query, generated.fragment))


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.sequence = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.sequence += 1
        command_id = self.sequence
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context, "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.3)

    def screenshot(self, path):
        data = self.command("browsingContext.captureScreenshot", {"context": self.context})["data"]
        path.write_bytes(base64.b64decode(data))

    def close(self):
        self.socket.close()


def main():
    driver = Driver(bidi=True, browser="firefox")
    viewport = Viewport(driver)
    results = {"checks": {}, "captures": []}
    try:
        driver.get(one_time_login_url())
        time.sleep(3)
        results["apos_uli"] = {"url": driver.url(), "titulo": driver.title()}
        driver.get(MAIN + "/")
        time.sleep(2)
        results["sessao"] = driver.js(
            "return !!document.querySelector('#toolbar, #admin-toolbar, .toolbar, a[href*=\"user/logout\"]')")
        driver.get(LIBRARY)
        time.sleep(3)
        results["url"] = driver.url()
        results["titulo"] = driver.title()

        results["checks"]["seletor_radios"] = driver.js(
            "return document.querySelectorAll('.aculta-library-mode input[type=radio]').length")
        results["checks"]["barra_institucional"] = driver.js(
            "return document.querySelectorAll('.page__institution').length")
        results["checks"]["previews"] = driver.js(
            "return document.querySelectorAll('.ui_patterns_story__preview, .ui_patterns_story__story').length")

        for mode in MODES:
            driver.js(
                "var r=document.querySelector('.aculta-library-mode input[value=\"%s\"]');"
                "if(r){r.click();} return !!r;" % mode)
            time.sleep(0.5)
            results["checks"][f"previews_{mode}"] = driver.js(
                "return Array.prototype.filter.call("
                "document.querySelectorAll('.ui_patterns_story__preview, .ui_patterns_story__story'),"
                "function(n){return n.getAttribute('data-theme')==='%s';}).length" % mode)
            for width in WIDTHS:
                viewport.set(width)
                overflow = driver.js(
                    "return document.documentElement.scrollWidth - window.innerWidth")
                name = f"{mode}-{width}.png"
                viewport.screenshot(OUT / name)
                results["captures"].append({"file": name, "mode": mode, "width": width,
                                            "overflow_px": overflow})
        results["ok"] = (
            results["checks"]["seletor_radios"] == 2
            and results["checks"]["barra_institucional"] == 0
            and results["checks"]["previews_light"] == results["checks"]["previews"]
            and results["checks"]["previews_dark"] == results["checks"]["previews"]
            and all(c["overflow_px"] <= 0 for c in results["captures"])
        )
    finally:
        viewport.close()
        driver.close()
        (OUT / "manifest.json").write_text(json.dumps(results, ensure_ascii=False, indent=2))
    print(json.dumps(results["checks"], ensure_ascii=False))
    print("ok" if results.get("ok") else "FALHOU")


if __name__ == "__main__":
    main()
