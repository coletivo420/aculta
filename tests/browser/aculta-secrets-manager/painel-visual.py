#!/usr/bin/env python3
"""Verificação visual e de teclado do painel de credenciais (abas Produção/Testes).

Ambiente: servidor de testes (*.aculta.toca.net.br). Firefox ESR visível em DISPLAY=:0.
Login: link de uso único gerado pelo Drush no subprocesso; nunca impresso nem salvo.
Variáveis: SECRETS_UID (padrão 205, conta qa_segredos), SUITE_OUT (capturas).
Limites: não altera dados; só lê a página e troca de aba. Não compara conteúdo com o banco.
"""

import base64
import os
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

PREFS = {"network.dns.localDomains": ",".join([
    "aculta.toca.net.br",
    "conta.aculta.toca.net.br",
])}
ACCOUNT = "https://conta.aculta.toca.net.br"
PANEL = "https://aculta.toca.net.br/painel-administrativo/aculta/segredos"
UID = os.environ.get("SECRETS_UID", "205")
OUT = Path(os.environ.get("SUITE_OUT", "/mnt/disco1/tmp/claude-1000/-home-piradopirata-projetos-aculta/9ebbe7e5-df6b-4fba-bc97-415309bead0f/scratchpad/secrets-visual"))
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
                        f"--uri={ACCOUNT}"], cwd=PROJECT, capture_output=True, text=True,
                       timeout=30, env=env, check=True)
    return r.stdout.strip().splitlines()[-1]  # nunca impresso


def key(driver, k):
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "kb", "actions": [{"type": "keyDown", "value": k}, {"type": "keyUp", "value": k}]}]})


def set_width(driver, w):
    driver.req("POST", f"/session/{driver.session}/window/rect", {"width": w, "height": 900})


def selected(driver):
    return driver.js("""return [...document.querySelectorAll('[data-portal-ui="secrets-tab"]')]
      .map(t => t.id.replace('credenciais-tab-','') + ':' + t.getAttribute('aria-selected'))""")


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
    d = Driver(prefs=PREFS)
    try:
        set_width(d, 1280)
        d.get(one_time_login())
        d.get(PANEL)
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
            set_width(d, width)
            overflow = d.js("return document.documentElement.scrollWidth - window.innerWidth")
            check(f"sem rolagem horizontal da página em {width}px", overflow <= 0, f"excesso={overflow}")
            wrap = d.js("""const w = document.querySelector('#credenciais-test .secrets-table-scroll');
              return w ? Math.round(w.getBoundingClientRect().right) - window.innerWidth : null;""")
            check(f"tabela dentro da largura da janela em {width}px", wrap is not None and wrap <= 1, f"excesso={wrap}")
            # Oculta só para a captura o off-canvas do menu administrativo (Claro), que cobre o painel em
            # telas estreitas. Não altera o estado do site: a alteração existe apenas nesta página aberta.
            d.js("document.querySelectorAll('[id*=\"off-canvas\"], .toolbar-tray').forEach(e => e.style.display = 'none');")
            shot = d.req("GET", f"/session/{d.session}/screenshot")
            (OUT / f"{name}.png").write_bytes(base64.b64decode(shot))
    finally:
        d.close()
    print(f"\n{sum(results)}/{len(results)} verificações passaram. Capturas em {OUT}")
    return 0 if all(results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
