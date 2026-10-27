#!/usr/bin/env python3
"""Verifica a prévia e o lote do QR Code com administrador de teste logado, no Firefox ESR visível (DISPLAY=:0).

Fluxo:
1. Login pelo Firefox no host da conta (/entrar), com o usuário de teste dedicado. Espera o Turnstile resolver no
   próprio navegador, como login-captcha.py.
2. Captura de tela da coleção administrativa.
3. Os cookies da sessão são lidos pelo WebDriver e usados com curl para checar status e conteúdo da prévia e do lote.

Requisitos (fora do repositório):
- QR_TEST_PASS_FILE: arquivo com a senha do usuário de teste (uma linha). Nunca impresso.
- QR_TEST_USER: nome do usuário de teste (padrão qr_teste_agente).
- QR_TEST_CODE_ID: id de um código QR existente no servidor de teste, para o lote.

O que não cobre: as micropáginas `/pagina/{slug}` logadas, que exigem um slug publicado.
Código de saída: 0 se tudo passou; 1 se alguma verificação falhou.
"""
import base64
import json
import os
import subprocess
import sys
import tempfile
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))
import wdlib  # noqa: E402

QR_HOST = os.environ.get("QR_HOST", "https://aculta.toca.net.br")
CONTA_HOST = os.environ.get("CONTA_HOST", "https://conta.aculta.toca.net.br")
USUARIO = os.environ.get("QR_TEST_USER", "qr_teste_agente")
PASS_FILE = os.environ.get("QR_TEST_PASS_FILE", "")
CODE_ID = os.environ.get("QR_TEST_CODE_ID", "1")
OUT = os.environ.get("SUITE_OUT", "/tmp/aculta-qrcode-logado")

os.makedirs(OUT, exist_ok=True)


def senha():
    with open(PASS_FILE, encoding="utf-8") as fh:
        return fh.read().strip()


def try_fill(d, selectores, valor):
    for sel in selectores:
        if d.exists(sel):
            d.type(sel, valor)
            return True
    return False


def token_turnstile(d):
    """Comprimento do token do Cloudflare Turnstile no formulário (-1 quando o campo não existe)."""
    return d.js("var e=document.querySelector('[name=cf-turnstile-response]'); return e ? e.value.length : -1")


def login(d):
    """Login com o usuário de teste. Espera o Turnstile resolver no próprio navegador, como login-captcha.py."""
    d.get(CONTA_HOST + "/entrar")
    time.sleep(2)
    d.js("var b=[...document.querySelectorAll('button')].find(x=>x.innerText.trim()==='Aceitar'); if(b){b.click();return true} return false")
    for _ in range(20):
        if token_turnstile(d) > 0:
            break
        time.sleep(3)
    try_fill(d, ["#edit-name", "input[name=name]"], USUARIO)
    try_fill(d, ["#edit-pass", "input[type=password]"], senha())
    if d.exists("#edit-submit"):
        d.click("#edit-submit")
    else:
        d.submit("input[type=password]")
    time.sleep(3)


def cookies_da_sessao(d):
    """Cookies do navegador (WebDriver) no formato 'nome=valor; ...' para o curl."""
    itens = d.req("GET", f"/session/{d.session}/cookie")
    return "; ".join(f"{c['name']}={c['value']}" for c in itens)


def curl_status(url, cookie):
    """Status, tipo e corpo de uma requisição com a sessão do administrador de teste."""
    with tempfile.NamedTemporaryFile(delete=False) as corpo:
        r = subprocess.run(
            ["curl", "-sk", "-m", "30", "-b", cookie, "-o", corpo.name, "-w", "%{http_code} %{content_type}", url],
            capture_output=True, text=True,
        )
        status, _, ctype = r.stdout.partition(" ")
        with open(corpo.name, "rb") as fh:
            dados = fh.read()
        os.unlink(corpo.name)
    return int(status or 0), ctype.strip(), dados


def main():
    if not PASS_FILE or not os.path.isfile(PASS_FILE):
        print("FAIL: defina QR_TEST_PASS_FILE com a senha do usuário de teste.")
        return 1
    resultados = []
    d = wdlib.Driver()
    try:
        d.req("POST", f"/session/{d.session}/window/rect", {"width": 1280, "height": 900})
        login(d)
        d.get(QR_HOST + "/painel-administrativo/aculta/codigos-qr")
        time.sleep(1)
        png = d.req("GET", f"/session/{d.session}/screenshot")
        with open(os.path.join(OUT, "colecao-logado.png"), "wb") as fh:
            fh.write(base64.b64decode(png))
        cookie = cookies_da_sessao(d)
    finally:
        d.close()

    casos = [
        ("colecao", QR_HOST + "/painel-administrativo/aculta/codigos-qr", 200, None),
        ("previa-url-valida", QR_HOST + "/painel-administrativo/aculta/codigos-qr/previa?url=https%3A%2F%2Fexample.org", 200, "svg"),
        ("previa-url-insegura", QR_HOST + "/painel-administrativo/aculta/codigos-qr/previa?url=javascript%3Aalert(1)", 422, None),
        ("lote-valido", QR_HOST + f"/painel-administrativo/aculta/codigos-qr/lote?ids={CODE_ID}&formato=svg", 200, "zip"),
        ("lote-inexistente", QR_HOST + "/painel-administrativo/aculta/codigos-qr/lote?ids=999999999&formato=svg", 404, None),
    ]
    for nome, url, esperado, tipo in casos:
        http, ctype, corpo = curl_status(url, cookie)
        ok = http == esperado
        if tipo == "svg":
            ok = ok and b"<svg" in corpo
        if tipo == "zip":
            ok = ok and ("zip" in ctype) and corpo[:2] == b"PK"
        resultados.append({"caso": nome, "http": http, "content_type": ctype, "esperado": esperado, "ok": ok})

    with open(os.path.join(OUT, "resultado.json"), "w", encoding="utf-8") as fh:
        json.dump(resultados, fh, ensure_ascii=False, indent=2)
    falhas = [r for r in resultados if not r["ok"]]
    for r in resultados:
        print(("PASS" if r["ok"] else "FAIL"), r)
    print(f"{len(resultados) - len(falhas)}/{len(resultados)} verificações OK. Evidência: {OUT}")
    return 1 if falhas else 0


if __name__ == "__main__":
    sys.exit(main())
