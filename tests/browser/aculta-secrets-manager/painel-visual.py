#!/usr/bin/env python3
"""Verificação visual e de teclado do painel de credenciais (abas Produção/Testes).

Ambiente: servidor de testes (*.aculta.toca.net.br). Firefox ESR visível em DISPLAY=:0.
Login: link de uso único gerado pelo Drush no subprocesso; nunca impresso nem salvo.
Variáveis: SECRETS_UID (padrão 205, conta qa_segredos), SUITE_OUT (capturas).
Limites: não altera dados; só lê a página e troca de aba. Não compara conteúdo com o banco.
"""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

RUNTIME_PROJECT = Path(os.environ.get("ACULTA_RUNTIME_PROJECT", PROJECT))
CSS_PATH = Path(os.environ.get(
    "SECRETS_CANDIDATE_CSS",
    PROJECT / "web/modules/custom/aculta_portal/modules/aculta_secrets_manager/css/secrets-tabs.css",
))

PREFS = {"network.dns.localDomains": ",".join([
    "aculta.toca.net.br",
    "conta.aculta.toca.net.br",
])}
ACCOUNT = "https://conta.aculta.toca.net.br"
PANEL = "https://aculta.toca.net.br/painel-administrativo/aculta/segredos"
UID = os.environ.get("SECRETS_UID", "205")
OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
KEY_RIGHT, KEY_LEFT, KEY_HOME = "", "", ""
results = []


def check(name, ok, detail=""):
    results.append(bool(ok))
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


def one_time_login():
    env = os.environ.copy()
    env.pop("DISPLAY", None)  # o Drush não deve herdar o X11
    r = subprocess.run(["php", "vendor/drush/drush/drush.php", "uli", f"--uid={UID}",
                        f"--uri={ACCOUNT}"], cwd=RUNTIME_PROJECT, capture_output=True, text=True,
                       timeout=30, env=env, check=True)
    return r.stdout.strip().splitlines()[-1]  # nunca impresso


def key(driver, k):
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "kb", "actions": [{"type": "keyDown", "value": k}, {"type": "keyUp", "value": k}]}]})


class Viewport:
    """Sets the CSS viewport exactly, independent of window-manager limits."""

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
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.3)
        return driver.js("return window.innerWidth")

    def close(self):
        self.socket.close()


def selected(driver):
    return driver.js("""return [...document.querySelectorAll('[data-portal-ui="secrets-tab"]')]
      .map(t => t.id.replace('credenciais-tab-','') + ':' + t.getAttribute('aria-selected'))""")


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
    d = Driver(prefs=PREFS, bidi=True)
    viewport = Viewport(d)
    try:
        viewport.set(d, 1280)
        d.get(one_time_login())
        d.get(PANEL)
        d.js("""const style = document.createElement('style');
          style.dataset.candidate = 'secrets-manager';
          style.textContent = arguments[0]; document.head.append(style); return true;""",
              [CSS_PATH.read_text()])
        check("CSS candidato aplicado ao painel servido", d.js(
            "return !!document.querySelector('style[data-candidate=\"secrets-manager\"]')"))
        check("painel responde com título", "Credenciais do ambiente" in d.title(), d.title())
        tabs = selected(d)
        check("duas abas", len(tabs) == 2, str(tabs))
        check("aba do ambiente ativo selecionada (test)", "test:true" in tabs and "production:false" in tabs, str(tabs))
        check("aba de produção sem botões", d.js("""return document.querySelectorAll(
          '#credenciais-production [data-portal-ui="secret-value-eye"], #credenciais-production [data-portal-ui="secret-value-edit"]').length""") == 0)
        check("aba de testes com botões", d.js("""return document.querySelectorAll(
          '#credenciais-test [data-portal-ui="secret-value-eye"]').length""") > 0)
        d.click('[data-portal-ui="secrets-tab"][id="credenciais-tab-production"]')
        env_text = d.js("return document.querySelector('[data-portal-ui=\"secrets-environment\"]').textContent")
        check("troca para produção e painel de produção visível",
              "production:true" in selected(d) and d.js("return !document.getElementById('credenciais-production').hidden"))
        check("texto do ambiente operacional inalterado após trocar aba", "test" in env_text, env_text[:80])
        key(d, KEY_LEFT)
        check("teclado: seta esquerda volta para testes", "test:true" in selected(d), str(selected(d)))
        key(d, KEY_RIGHT)
        check("teclado: seta direita vai para produção", "production:true" in selected(d), str(selected(d)))
        key(d, KEY_HOME)
        check("teclado: Home vai para a primeira aba", "production:true" in selected(d), str(selected(d)))
        d.click('[data-portal-ui="secrets-tab"][id="credenciais-tab-test"]')
        for width, name in WIDTHS:
            actual_width = viewport.set(d, width)
            overflow = d.js("return document.documentElement.scrollWidth - window.innerWidth")
            check(f"viewport CSS de {width}px aplicado", actual_width == width, f"innerWidth={actual_width}")
            check(f"sem rolagem horizontal da página em {width}px", overflow <= 0, f"excesso={overflow}")
            d.js("""document.querySelector('#credenciais-test [data-portal-ui~="secrets-table-scroll"]')
              ?.scrollTo({left: 0});""")
            metrics = d.js("""const w = document.querySelector('#credenciais-test [data-portal-ui~="secrets-table-scroll"]');
              const t = w?.querySelector('[data-portal-ui~="requirements"]');
              const box = w?.getBoundingClientRect();
              return w && t ? {right: Math.round(box.right), client: w.clientWidth,
                scrollLeft: w.scrollLeft, scroll: w.scrollWidth,
                min: parseFloat(getComputedStyle(t).minInlineSize),
                cells: [...t.rows[0].cells].map(c => Math.round(c.getBoundingClientRect().width)),
                rows: [...(t.tBodies[0]?.rows || [])].slice(0, 3).map(r => Math.round(r.getBoundingClientRect().height))} : null;""")
            bounded = metrics is not None and metrics["right"] <= width + 1
            check(f"área da tabela limitada ao painel em {width}px", bounded, str(metrics))
            readable = (metrics is not None and metrics["min"] >= 896
                        and len(metrics["cells"]) == 5 and min(metrics["cells"]) >= 48
                        and metrics["cells"][3] >= 250 and metrics["cells"][4] >= 180)
            check(f"colunas preservam largura legível em {width}px", readable, str(metrics))
            rows_readable = metrics is not None and bool(metrics["rows"]) and max(metrics["rows"]) <= 180
            check(f"altura das linhas não fragmenta o conteúdo em {width}px", rows_readable, str(metrics))
            if width < 600:
                check(f"tabela rola dentro do contêiner em {width}px",
                      metrics is not None and metrics["scroll"] > metrics["client"], str(metrics))
            check(f"captura começa na primeira coluna em {width}px",
                  metrics is not None and metrics["scrollLeft"] == 0, str(metrics))
            # Oculta só para a captura o off-canvas do menu administrativo (Claro), que cobre o painel em
            # telas estreitas. Não altera o estado do site: a alteração existe apenas nesta página aberta.
            d.js("""document.querySelectorAll('#admin-toolbar, #toolbar-administration, [id*=\"off-canvas\"], .toolbar-tray')
              .forEach(e => e.style.display = 'none');
              const w = document.querySelector('#credenciais-test [data-portal-ui~="secrets-table-scroll"]');
              if (w) { w.scrollLeft = 0; w.scrollIntoView({block: 'center', inline: 'nearest'}); }""")
            shot = d.req("GET", f"/session/{d.session}/screenshot")
            (OUT / f"{name}.png").write_bytes(base64.b64decode(shot))
            if width < 600:
                d.js("""const w = document.querySelector('#credenciais-test [data-portal-ui~=\"secrets-table-scroll\"]');
                  if (w) w.scrollLeft = w.scrollWidth;""")
                shot = d.req("GET", f"/session/{d.session}/screenshot")
                (OUT / f"{name}-right.png").write_bytes(base64.b64decode(shot))
    finally:
        viewport.close()
        d.close()
    print(f"\n{sum(results)}/{len(results)} verificações passaram. Capturas em {OUT}")
    return 0 if all(results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
