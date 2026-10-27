#!/usr/bin/env python3
"""Popups do painel de credenciais, somente leitura: alterar e apagar são abertos e fechados sem gravar.

Ambiente: servidor de testes (*.aculta.toca.net.br). Firefox ESR visível em DISPLAY=:0.
Não clica em salvar nem em apagar, e não aciona o olho (que revela o valor). Confere só que o link do olho
traz o token CSRF. Login e variáveis: ver painel-visual.py.
"""

import importlib.util
import os
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location("painel", HERE / "painel-visual.py")
painel = importlib.util.module_from_spec(spec)
spec.loader.exec_module(painel)
from wdlib import Driver  # noqa: E402

KEY_ESC = ""
results = []


def check(name, ok, detail=""):
    results.append(bool(ok))
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


def open_popup(d, selector):
    d.js(f"document.querySelector('{selector}').click();")
    d.js("return true;")
    for _ in range(40):
        if d.js("return !!document.querySelector('.ui-dialog:not(.ui-dialog--closing), [role=dialog]')"):
            return True
        time.sleep(0.25)
    return False


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
    d = Driver(prefs=painel.PREFS)
    try:
        painel.set_width(d, 1280)
        d.get(painel.one_time_login())
        d.get(painel.PANEL)
        eye_href = d.js("""const a = document.querySelector('#credenciais-test [data-portal-ui="secret-value-eye"]');
          return a ? a.getAttribute('href') : null;""")
        check("olho tem token CSRF no link (sem clicar)", eye_href and "token=" in eye_href)
        for label, selector in [
            ("alterar", '#credenciais-test [data-portal-ui="secret-value-edit"]'),
            ("apagar", '#credenciais-test [data-portal-ui="secret-value-clear"]'),
        ]:
            ok = open_popup(d, selector)
            check(f"popup de {label} abre", ok)
            if ok:
                title = d.js("const t = document.querySelector('.ui-dialog-title, [role=dialog] h1, [role=dialog] h2'); return t ? t.textContent.trim() : ''")
                check(f"popup de {label} tem título", bool(title), title[:60])
                # Fecha pelo botão do próprio diálogo (Cancelar ou fechar); não envia o formulário.
                d.js("""const b = document.querySelector('.ui-dialog-titlebar-close')
                  || [...document.querySelectorAll('.ui-dialog button, [role=dialog] button, [role=dialog] a')]
                       .find(e => /cancelar|fechar|close/i.test(e.textContent + ' ' + (e.getAttribute('aria-label') || '')));
                if (b) { b.click(); }""")
                closed = False
                for _ in range(40):
                    if not d.js("return !!document.querySelector('.ui-dialog:not(.ui-dialog--closing), [role=dialog]')"):
                        closed = True
                        break
                    time.sleep(0.25)
                check(f"popup de {label} fecha pelo botão do diálogo, sem gravar", closed)
            d.get(painel.PANEL)
    finally:
        d.close()
    print(f"\n{sum(results)}/{len(results)} verificações passaram.")
    return 0 if all(results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
