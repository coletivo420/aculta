#!/usr/bin/env python3
"""Verifica no Firefox ESR visível (DISPLAY=:0) o layout mobile de uma micropágina publicada, como visitante anônimo.

Cobre, para a página indicada por LINK_SLUG:
- a página responde 200 para anônimo;
- não há rolagem horizontal em 390 px nem em 1280 px;
- os botões e links do conteúdo (dentro de <main>) têm altura de toque de pelo menos 44 px (WCAG 2.5.5).
  O cromo do tema (barra institucional e rodapé) não entra: é do ACULTA420, fora do escopo desta frente.

Ambiente: servidor de testes (`links.aculta.toca.net.br`). A página precisa existir e estar publicada nesse
servidor. Não usa login nem senha. Evidência (PNG e JSON) vai para SUITE_OUT, fora do repositório.

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

LINKS_HOST = os.environ.get("LINKS_HOST", "https://links.aculta.toca.net.br")
SLUG = os.environ.get("LINK_SLUG", "pagina-teste-mobile")
OUT = os.environ.get("SUITE_OUT", "/tmp/aculta-links-micropagina")
LARGURAS = [1280, 390]
ALTURA_MIN_TOQUE = 44

os.makedirs(OUT, exist_ok=True)


def status_http(d):
    return d.js(
        "const e = performance.getEntriesByType('navigation')[0];"
        "return e ? (e.responseStatus || 0) : -1;"
    )


def tamanho_janela(d, largura):
    d.req("POST", f"/session/{d.session}/window/rect", {"width": largura, "height": 900})


def rolagem_horizontal(d):
    return d.js("return document.documentElement.scrollWidth > document.documentElement.clientWidth;")


def botoes_pequenos(d):
    """Elementos interativos do conteúdo (dentro de <main>) com altura menor que o mínimo de toque.

    Exclui o link de pular para o conteúdo, que só aparece com foco. O cromo do tema (barra institucional e
    rodapé) fica de fora: é do ACULTA420, fora do escopo desta frente.
    """
    return d.js(
        "const raiz = document.querySelector('main') || document.body;"
        "return [...raiz.querySelectorAll('a, button')]"
        ".filter(e => !e.classList.contains('visually-hidden-focusable') && e.offsetParent !== null"
        f"  && e.getBoundingClientRect().height > 0 && e.getBoundingClientRect().height < {ALTURA_MIN_TOQUE}).length;"
    )


def captura(d, nome):
    png = d.req("GET", f"/session/{d.session}/screenshot")
    with open(os.path.join(OUT, nome), "wb") as fh:
        fh.write(base64.b64decode(png))


def main():
    resultados = []
    url = f"{LINKS_HOST}/pagina/{SLUG}"
    d = wdlib.Driver()
    try:
        for largura in LARGURAS:
            tamanho_janela(d, largura)
            d.get(url)
            time.sleep(2)
            http = status_http(d)
            sem_rolagem = not rolagem_horizontal(d)
            pequenos = botoes_pequenos(d)
            resultados.append({
                "largura": largura,
                "url": url,
                "http": http,
                "sem_rolagem_horizontal": sem_rolagem,
                "elementos_menores_que_44px": pequenos,
                "ok": http == 200 and sem_rolagem and pequenos == 0,
            })
            captura(d, f"{SLUG}-{largura}.png")
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
