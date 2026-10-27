#!/usr/bin/env python3
"""Verificação HTTP do painel de credenciais no servidor de testes, com sessão real.

Ambiente: servidor de testes (*.aculta.toca.net.br), por IPv4 e pelo DNS público.
Login: link de uso único do Drush para a conta de teste (SECRETS_UID, padrão 205 = qa_segredos),
gerado no subprocesso e nunca impresso. O cookie de sessão é compartilhado pelo próprio servidor
(cookie_domain da produção/teste), como no navegador.
Não altera dados: só lê o painel e tenta requisições que devem ser negadas.
"""

import http.cookiejar
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request
from pathlib import Path

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
MAIN = "https://aculta.toca.net.br"
ACCOUNT = "https://conta.aculta.toca.net.br"
PANEL = MAIN + "/painel-administrativo/aculta/segredos"
UID = os.environ.get("SECRETS_UID", "205")
results = []


def check(name, ok, detail=""):
    results.append(bool(ok))
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def opener(jar, follow=True):
    handlers = [urllib.request.HTTPCookieProcessor(jar)]
    if not follow:
        handlers.append(NoRedirect())
    return urllib.request.build_opener(*handlers)


def get(op, url):
    try:
        with op.open(url, timeout=30) as r:
            return r.status, dict(r.headers), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode("utf-8", "replace")


def secret_values():
    """Pares (nome, valor) do arquivo de segredos do servidor. Lidos só para comparação; nunca impressos."""
    path = PROJECT / "secrets" / "aculta.secrets.env"
    if not path.is_file():
        return []
    pairs = []
    for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        if "=" in line and not line.lstrip().startswith("#"):
            name, value = line.split("=", 1)
            pairs.append((name.strip(), value.strip()))
    return pairs


def one_time_link():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    r = subprocess.run(["php", "vendor/drush/drush/drush.php", "uli", f"--uid={UID}", f"--uri={ACCOUNT}"],
                       cwd=PROJECT, capture_output=True, text=True, timeout=60, env=env, check=True)
    return r.stdout.strip().splitlines()[-1]  # nunca impresso


def main():
    anon = opener(http.cookiejar.CookieJar())
    status, _, _ = get(anon, PANEL)
    check("visitante anônimo não vê o painel", status in (403, 404), f"HTTP {status}")

    jar = http.cookiejar.CookieJar()
    op = opener(jar)
    get(op, one_time_link())  # login pela Conta; segue os redirecionamentos e grava o cookie compartilhado
    status, headers, body = get(op, PANEL)
    check("admin acessa o painel", status == 200, f"HTTP {status}")
    if status == 200:
        check("resposta sem cache (no-store)", "no-store" in headers.get("Cache-Control", ""), headers.get("Cache-Control", ""))
        selected = dict(re.findall(r'id="credenciais-tab-(production|test)"[^>]*aria-selected="(true|false)"', body))
        check("duas abas presentes", len(selected) == 2, str(selected))
        active = "test" if selected.get("test") == "true" else "production"
        check("aba do ambiente ativo selecionada", selected.get(active) == "true", active)
        prod = re.search(r'id="credenciais-production".*?(?=id="credenciais-test"|$)', body, re.S)
        check("aba de produção sem botões de olho/lápis",
              prod is not None and "secret-value-eye" not in prod.group(0) and "secret-value-edit" not in prod.group(0))
        leaked = [name for name, value in secret_values() if len(value) >= 6 and value in body]
        check("nenhum valor real aparece no HTML", not leaked, f"vazados: {len(leaked)}")

        status, _, _ = get(op, MAIN + "/painel-administrativo/aculta/segredos/mostrar/BREVO_API_KEY")
        check("revelar sem token CSRF é negado", status == 403, f"HTTP {status}")

    status, _, _ = get(anon, MAIN + "/painel-administrativo/aculta/segredos/editar/BREVO_API_KEY")
    check("alterar sem sessão é negado", status in (403, 404), f"HTTP {status}")

    print(f"\n{sum(results)}/{len(results)} verificações passaram.")
    return 0 if all(results) else 1


if __name__ == "__main__":
    sys.exit(main())
