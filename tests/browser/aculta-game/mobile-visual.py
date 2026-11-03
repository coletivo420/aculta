#!/usr/bin/env python3
"""Exact CSS viewport and keyboard review for Game account/profile surfaces."""

import base64
from hashlib import sha256
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import urlparse
from urllib.request import Request, urlopen
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

RUNTIME_PROJECT = Path(os.environ.get("SUITE_PROJECT_ROOT", PROJECT))
ACCOUNT_NAME = os.environ.get("GAME_ACCOUNT", "teste.comum")
ACCOUNT_BASE = os.environ.get("GAME_ACCOUNT_BASE", "https://conta.aculta.toca.net.br")
MAIN_BASE = os.environ.get("SUITE_BASE", "https://aculta.toca.net.br")
OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
PROFILE_CSS = PROJECT / "web/themes/custom/aculta420/css/components/public-profile.css"
RESULTS = []


def check(name, passed, detail=""):
    RESULTS.append(bool(passed))
    print(f"{'PASS' if passed else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""), flush=True)


def one_time_login():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    result = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", "--no-browser",
         f"--name={ACCOUNT_NAME}", f"--uri={ACCOUNT_BASE}"],
        cwd=RUNTIME_PROJECT, env=env, capture_output=True, text=True,
        timeout=30, check=True,
    )
    return result.stdout.strip().splitlines()[-1]


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, driver, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 1000},
        })
        time.sleep(0.25)
        return driver.js("return window.innerWidth")

    def close(self):
        self.socket.close()


def wait_for(driver, script, timeout=20):
    end = time.time() + timeout
    while time.time() < end:
        value = driver.js(script)
        if value:
            return value
        time.sleep(0.25)
    return driver.js(script)


def screenshot(driver, name):
    value = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{name}.png").write_bytes(base64.b64decode(value))


def element_screenshot(driver, selector, name):
    element = driver.find(selector)["element-6066-11e4-a52e-4f735466cecf"]
    value = driver.req("GET", f"/session/{driver.session}/element/{element}/screenshot")
    (OUT / f"{name}.png").write_bytes(base64.b64decode(value))


def key_tab(driver):
    tab = "\ue004"
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "keyboard", "actions": [
            {"type": "keyDown", "value": tab}, {"type": "keyUp", "value": tab},
        ],
    }]})


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com Firefox ESR visível em DISPLAY=:0.")
    hosts = {"aculta.toca.net.br", "conta.aculta.toca.net.br"}
    if any(urlparse(url).hostname not in hosts for url in (ACCOUNT_BASE, MAIN_BASE)):
        raise SystemExit("Use somente os hosts de desenvolvimento do ACULTA.")
    with urlopen(Request(
        MAIN_BASE + "/themes/custom/aculta420/css/components/public-profile.css",
        headers={"Accept-Encoding": "identity"},
    ), timeout=10) as response:
        served_css = response.read()
        css_status = response.status
    candidate_css = PROFILE_CSS.read_bytes()
    check("CSS do perfil servido é idêntico ao candidato",
          css_status == 200 and served_css == candidate_css,
          f"HTTP={css_status}; sha256={sha256(served_css).hexdigest()}")
    driver = Driver(prefs={"network.dns.localDomains": ",".join(sorted(hosts))}, bidi=True)
    viewport = Viewport(driver)
    try:
        viewport.set(driver, 1280)
        driver.get(one_time_login())
        time.sleep(1)
        account_url = ACCOUNT_BASE + "/atividade/conquistas"
        driver.get(account_url)
        check("atividade mostra conquistas e missões", wait_for(
            driver, "return document.body.innerText.includes('Conquistas') && document.body.innerText.includes('Missões')"))
        check("controles de preferência têm rótulos", driver.js("""const inputs = [...document.querySelectorAll(
          'input[name="hide_all"], input[name^="items["]')];
          return inputs.length > 0 && inputs.every(input => input.labels && input.labels.length);"""))

        for width in (1280, 390, 320):
            actual = viewport.set(driver, width)
            driver.get(account_url)
            has_form = wait_for(driver, "return !!document.querySelector('input[name=\"hide_all\"]')")
            metrics = driver.js("""const form = document.querySelector('input[name="hide_all"]')?.closest('form');
              const r = form?.getBoundingClientRect();
              return {width: innerWidth, scroll: document.documentElement.scrollWidth,
                formWidth: r ? Math.round(r.width) : null, formRight: r ? Math.round(r.right) : null};""")
            check(f"viewport CSS {width}px aplicado na conta", actual == width, f"innerWidth={actual}")
            check(f"formulário de conta renderiza em {width}px", bool(has_form), str(metrics))
            check(f"conta sem overflow horizontal em {width}px",
                  metrics["scroll"] <= width + 1 and metrics["formRight"] <= width + 1, str(metrics))
            if width == 320:
                driver.click('input[name="hide_all"]')
                key_tab(driver)
                focus = driver.js("""const e = document.activeElement;
                  const form = document.querySelector('input[name="hide_all"]')?.closest('form');
                  return {name: e?.name || '', withinForm: !!form?.contains(e),
                    visible: !!e?.matches(':focus-visible'),
                    outline: getComputedStyle(e).outlineStyle};""")
                check("teclado avança para outro controle do formulário",
                      focus["name"] != "hide_all" and focus["withinForm"], str(focus))
                check("foco de teclado fica visível", focus["visible"] and focus["outline"] != "none", str(focus))
            screenshot(driver, f"game-conta-{width}")

        profile_url = MAIN_BASE + "/u/pessoa-5"
        for width in (1280, 390, 320):
            actual = viewport.set(driver, width)
            driver.get(profile_url)
            loaded = wait_for(driver, "return !!document.querySelector('.portal-profile__achievements-list .portal-profile__achievement')")
            metrics = driver.js("""const profile = document.querySelector('.portal-profile');
              const card = document.querySelector('.portal-profile__achievement');
              const header = document.querySelector('.portal-profile__header');
              const r = profile?.getBoundingClientRect(), c = card?.getBoundingClientRect();
              return {width: innerWidth, scroll: document.documentElement.scrollWidth,
                profileRight: r ? Math.round(r.right) : null, cardRight: c ? Math.round(c.right) : null,
                grid: profile ? getComputedStyle(document.querySelector('.portal-profile__achievements-list')).gridTemplateColumns : '',
                headerDirection: header ? getComputedStyle(header).flexDirection : ''};""")
            check(f"viewport CSS {width}px aplicado no perfil", actual == width, f"innerWidth={actual}")
            check(f"perfil público com conquista em {width}px", bool(loaded), str(metrics))
            check(f"cartão e perfil cabem em {width}px",
                  metrics["scroll"] <= width + 1 and metrics["profileRight"] <= width + 1
                  and metrics["cardRight"] <= metrics["profileRight"] + 1, str(metrics))
            expected_direction = "column" if width < 576 else "row"
            check(f"cabeçalho do perfil reflow em {width}px",
                  metrics["headerDirection"] == expected_direction, str(metrics))
            screenshot(driver, f"game-perfil-{width}")
            if width == 320:
                scrolled = driver.js("""const section = document.querySelector('.portal-profile__achievements');
                  section?.scrollIntoView({block:'start', behavior:'instant'});
                  return section ? {y: Math.round(scrollY), top: Math.round(section.getBoundingClientRect().top),
                    pageHeight: document.documentElement.scrollHeight, viewportHeight: innerHeight,
                    rootOverflow: getComputedStyle(document.documentElement).overflowY,
                    bodyOverflow: getComputedStyle(document.body).overflowY} : null;""")
                check("seção de conquistas pode ser inspecionada no celular",
                      scrolled is not None and 0 <= scrolled["top"] <= 60
                      and scrolled["pageHeight"] > scrolled["viewportHeight"], str(scrolled))
                time.sleep(0.25)
                element_screenshot(driver, ".portal-profile__achievements", "game-perfil-awards-320")
    finally:
        viewport.close()
        driver.close()
    print(f"\n{sum(RESULTS)}/{len(RESULTS)} verificações passaram.", flush=True)
    return 0 if all(RESULTS) else 1


if __name__ == "__main__":
    raise SystemExit(main())
