#!/usr/bin/env python3
"""Foco por teclado, alvos de toque e cor do ícone de apagar no painel de segredos (tema Claro).

Ambiente: servidor de testes (*.aculta.toca.net.br), Firefox ESR visível em DISPLAY=:0.
Login: link de uso único gerado pelo Drush no subprocesso; nunca impresso nem salvo.
Variáveis: SECRETS_UID (padrão 205, conta qa_segredos), SUITE_OUT (capturas e log).
Limites: só lê a página e percorre o foco com Tab. Não clica em revelar, alterar nem apagar,
e não grava nada. Mede o foco visível pelo estilo computado (outline ou box-shadow).
Cor esperada do ícone de apagar: vermelho de erro do ACULTA (#b3261e). Se a cor computada for
outra, o script reporta a cor e a origem provável (variável CSS ausente no Claro).
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
OUT = Path(os.environ.get("SUITE_OUT", "/home/piradopirata/worktrees/out/claro-foco"))
OUT.mkdir(parents=True, exist_ok=True)
WIDTHS = [1280, 390, 320]
TAB = ""
MIN_TARGET = 44
DANGER_RGB = "rgb(179, 38, 30)"
results = []


def check(name, ok, detail=""):
    results.append(bool(ok))
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


def one_time_login():
    env = os.environ.copy()
    env.pop("DISPLAY", None)  # o Drush não deve herdar o X11
    r = subprocess.run(["php", "vendor/drush/drush/drush.php", "uli", f"--uid={UID}",
                        f"--uri={ACCOUNT}"], cwd=PROJECT, capture_output=True, text=True,
                       timeout=60, env=env, check=True)
    return r.stdout.strip().splitlines()[-1]  # nunca impresso


def press(driver, k):
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "kb", "actions": [{"type": "keyDown", "value": k}, {"type": "keyUp", "value": k}]}]})


def set_width(driver, w):
    driver.req("POST", f"/session/{driver.session}/window/rect", {"width": w, "height": 900})


FOCUSED_INFO = """const a = document.activeElement;
  if (!a || a === document.body) return null;
  const s = getComputedStyle(a);
  const visible = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || s.boxShadow !== 'none';
  return {tag: a.tagName, ui: a.getAttribute('data-portal-ui') || '', id: a.id || '',
          text: (a.textContent || a.getAttribute('aria-label') || '').trim().slice(0, 40),
          visible: visible, outline: s.outlineStyle + ' ' + s.outlineWidth};"""


def focus_walk(driver, limit=80):
    """Percorre o foco com Tab a partir do início da página e registra cada elemento focado."""
    driver.js("document.activeElement && document.activeElement.blur();")
    seen = []
    for _ in range(limit):
        press(driver, TAB)
        info = driver.js(FOCUSED_INFO)
        if info is None:
            continue
        seen.append(info)
    return seen


def targets(driver):
    return driver.js("""const sel = '#credenciais-test [data-portal-ui~="secret-value-eye"],'
        + '#credenciais-test [data-portal-ui~="secret-value-edit"],'
        + '#credenciais-test [data-portal-ui~="secret-value-clear"],'
        + '[data-portal-ui="secrets-tab"]';
      return [...document.querySelectorAll(sel)].map(e => {
        const r = e.getBoundingClientRect();
        return {ui: e.getAttribute('data-portal-ui'), w: Math.round(r.width), h: Math.round(r.height),
                shown: r.width > 0 && r.height > 0,
                color: getComputedStyle(e).color};
      });""")


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
    d = Driver(prefs=PREFS)
    try:
        set_width(d, 1280)
        d.get(one_time_login())
        d.get(PANEL)
        check("painel responde com título", "Credenciais do ambiente" in d.title(), d.title())
        vars_ = d.js("""const s = getComputedStyle(document.documentElement);
          return [s.getPropertyValue('--aculta-status-error-text').trim(), s.getPropertyValue('--color-danger').trim()];""")
        print(f"  variáveis de cor na raiz: --aculta-status-error-text='{vars_[0]}' --color-danger='{vars_[1]}'")

        for width in WIDTHS:
            # Recarrega a página em cada largura: a varredura de Tab começa no início do documento.
            set_width(d, width)
            d.get(PANEL)
            d.js("document.querySelectorAll('[id*=\"off-canvas\"], .toolbar-tray').forEach(e => e.style.display = 'none');")
            seq = focus_walk(d)
            names = {(s["ui"] or s["tag"]) for s in seq}
            for ui, label in [("secret-value-eye", "revelar"), ("secret-value-edit", "alterar"),
                              ("secret-value-clear", "apagar")]:
                hits = [s for s in seq if s["ui"] == ui or s["ui"].startswith(ui)]
                check(f"{width}px: Tab alcança {label}", bool(hits), f"{len(hits)} ocorrência(s)")
                if hits:
                    check(f"{width}px: foco visível em {label}", all(h["visible"] for h in hits),
                          hits[0]["outline"])
            tabs_hit = [s for s in seq if s["ui"] == "secrets-tab"]
            check(f"{width}px: abas recebem foco", bool(tabs_hit) or "credenciais-tab-test" in str(seq),
                  f"{len(tabs_hit)} foco(s) em aba")

            small = [t for t in targets(d) if t["shown"] and (t["w"] < MIN_TARGET or t["h"] < MIN_TARGET)]
            check(f"{width}px: alvos de toque ≥ {MIN_TARGET} px", not small,
                  "; ".join(f"{t['ui']} {t['w']}x{t['h']}" for t in small[:5]) or "todos ≥ 44")

            clear = [t for t in targets(d) if t["ui"] and "secret-value-clear" in t["ui"]]
            if clear:
                color = clear[0]["color"]
                check(f"{width}px: ícone de apagar vermelho", color == DANGER_RGB,
                      f"cor computada={color}; esperado={DANGER_RGB}")
            shot = d.req("GET", f"/session/{d.session}/screenshot")
            (OUT / f"claro-{width}.png").write_bytes(base64.b64decode(shot))
            print(f"  elementos focados em {width}px: {sorted(n for n in names if n)}")
    finally:
        d.close()
    print(f"\n{sum(results)}/{len(results)} verificações passaram. Capturas em {OUT}")
    return 0 if all(results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
