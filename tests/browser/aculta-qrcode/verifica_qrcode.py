#!/usr/bin/env python3
"""Verifica no Firefox ESR visível (DISPLAY=:0) o QR Code e o layout mobile dos links, como visitante anônimo.

Cobre:
- as rotas administrativas do QR Code (coleção, prévia e lote) negam acesso ao anônimo (403);
- `/redes-sociais` e `/entrar` não têm rolagem horizontal em 390 px e 1280 px.

O que não cobre: a rota /redes-sociais (404 no servidor de teste), a prévia e o lote com administrador logado (exigem sessão e um código existente), e a
revisão de layout das micropáginas `/pagina/{slug}` (exigem um slug publicado). Esses dois pontos ficam no README.

Ambiente: servidor de testes (*.aculta.toca.net.br). Não usa login nem senha. Evidência (PNG e JSON) vai para
SUITE_OUT, fora do repositório.

Código de saída: 0 se tudo passou; 1 se alguma verificação falhou.
"""
import base64
import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))
import wdlib  # noqa: E402

HOST = os.environ.get("QR_HOST", "https://aculta.toca.net.br")
LINKS_HOST = os.environ.get("LINKS_HOST", "https://links.aculta.toca.net.br")
CONTA_HOST = os.environ.get("CONTA_HOST", "https://conta.aculta.toca.net.br")
OUT = os.environ.get("SUITE_OUT", "/tmp/aculta-qrcode")
LARGURAS = [1280, 390]

ROTAS_ADMIN_QR = [
    "/painel-administrativo/aculta/codigos-qr",
    "/painel-administrativo/aculta/codigos-qr/previa?url=https%3A%2F%2Fexample.org",
    "/painel-administrativo/aculta/codigos-qr/lote?ids=1&formato=svg",
]
# (host, caminho): páginas publicadas no servidor de teste. /redes-sociais ainda não está publicada (404), e fica de fora.
PAGINAS_PUBLICAS = [(LINKS_HOST, "/"), (CONTA_HOST, "/entrar")]

os.makedirs(OUT, exist_ok=True)


def status_http(d):
    """Código HTTP da navegação atual, lido da API de Performance."""
    return d.js(
        "const e = performance.getEntriesByType('navigation')[0];"
        "return e ? (e.responseStatus || 0) : -1;"
    )


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
        tamanho_janela(d, 1280)
        for caminho in ROTAS_ADMIN_QR:
            d.get(HOST + caminho)
            time.sleep(1)
            http = status_http(d)
            # Anônimo deve ser negado (403). 404 indica rota ausente no ambiente, e é reportado como falha.
            resultados.append({
                "largura": 1280,
                "url": caminho.split("?")[0],
                "esperado": "403 (acesso negado ao anônimo)",
                "http": http,
                "ok": http == 403,
                "observacao": "rota ausente no ambiente (404): a prévia/lote da branch ainda não está publicada"
                if http == 404 else "",
            })
        for largura in LARGURAS:
            tamanho_janela(d, largura)
            for host, caminho in PAGINAS_PUBLICAS:
                d.get(host + caminho)
                time.sleep(1)
                http = status_http(d)
                sem_rolagem = not rolagem_horizontal(d)
                resultados.append({
                    "largura": largura,
                    "url": host + caminho,
                    "esperado": "sem rolagem horizontal",
                    "http": http,
                    "ok": sem_rolagem and http == 200,
                })
                captura(d, f"{host.split('//')[1].split('.')[0]}{caminho.replace('/', '-') or '-raiz'}-{largura}.png")
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
