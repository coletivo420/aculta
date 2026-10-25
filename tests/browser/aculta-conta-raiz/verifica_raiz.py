#!/usr/bin/env python3
"""Verifica no Firefox ESR visível (DISPLAY=:0) a raiz da Conta, sem sessão logada.

Cobre o contrato de rotas do Domain ACCOUNT:
- `/` e `/conta-legada` anônimos levam ao login (/entrar);
- `/minha-conta` e `/studio` respondem 404;
- a página de login não tem rolagem horizontal em 390 px e em 1280 px.

Ambiente: servidor de testes (*.aculta.toca.net.br). Não usa login nem senha.
Evidência (PNG e JSON) vai para SUITE_OUT, fora do repositório.
"""
import base64
import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))
import wdlib  # noqa: E402

HOST = os.environ.get("CONTA_HOST", "https://conta.aculta.toca.net.br")
OUT = os.environ.get("SUITE_OUT", "/tmp/aculta-conta-raiz")
LARGURAS = [1280, 390]

os.makedirs(OUT, exist_ok=True)


def status_http(d):
    """Código HTTP da navegação atual, lido da API de Performance."""
    v = d.js(
        "const e = performance.getEntriesByType('navigation')[0];"
        "return e ? (e.responseStatus || 0) : -1;"
    )
    return v


def tamanho_janela(d, largura):
    d.req("POST", f"/session/{d.session}/window/rect", {"width": largura, "height": 900})


def rolagem_horizontal(d):
    return d.js(
        "return document.documentElement.scrollWidth > document.documentElement.clientWidth;"
    )


def captura(d, nome):
    png = d.req("GET", f"/session/{d.session}/screenshot")
    with open(os.path.join(OUT, nome), "wb") as fh:
        fh.write(base64.b64decode(png))


def main():
    resultados = []
    d = wdlib.Driver()
    try:
        for largura in LARGURAS:
            tamanho_janela(d, largura)
            for caminho, esperado in [("/", "entrar"), ("/conta-legada", "entrar")]:
                d.get(HOST + caminho)
                time.sleep(1)
                ok = esperado in d.url() and d.exists("form#user-login-form, form[id^='user-login']")
                resultados.append({"largura": largura, "url": caminho, "esperado": "login", "final": d.url(),
                                   "http": status_http(d), "ok": ok})
            if largura == 390:
                captura(d, "entrar-390.png")
            d.get(HOST + "/entrar")
            time.sleep(1)
            sem_rolagem = not rolagem_horizontal(d)
            resultados.append({"largura": largura, "url": "/entrar", "esperado": "sem rolagem horizontal",
                               "ok": sem_rolagem})
            captura(d, f"entrar-{largura}.png")
        for caminho in ["/minha-conta", "/studio"]:
            d.get(HOST + caminho)
            time.sleep(1)
            http = status_http(d)
            resultados.append({"largura": 1280, "url": caminho, "esperado": "404", "http": http,
                               "ok": http == 404})
    finally:
        d.close()

    with open(os.path.join(OUT, "resultado.json"), "w", encoding="utf-8") as fh:
        json.dump(resultados, fh, ensure_ascii=False, indent=2)
    falhas = [r for r in resultados if not r["ok"]]
    for r in resultados:
        print(("PASS" if r["ok"] else "FAIL"), r)
    print(f"{len(resultados) - len(falhas)}/{len(resultados)} verificações OK. Evidência: {OUT}")
    return 1 if falhas else 0


if __name__ == "__main__":
    sys.exit(main())
