#!/usr/bin/env python3
"""Marca tópicos de venda e propaganda do acervo Growroom. Não apaga nada: grava um índice derivado.

Critério: só o título. O texto da página traz menus, anúncios e blocos do template em todas as páginas,
e a busca no texto marcou notícias e debates como comerciais. Por isso:
- comercial_forte: marca de loja no título ("[Growshop]") ou verbo de venda de anúncio ("vendo", "vende-se").
- revisar: termos que também aparecem em notícias ("a venda", "propaganda", "loja", "promoção"). Decisão humana.

Uso: sudo -n python3 -B marcar_comercial.py
Saída: restauro/comercial-marcados.jsonl
"""
import json
import os
import re
import sys
import unicodedata
from collections import Counter

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build_mapping as bm  # noqa: E402

# Marca de loja no título (seção de anúncios de lojas de sementes do próprio fórum).
MARCA_LOJA = re.compile(r"^\s*\[\s*growshop\s*\]|^\s*\[\s*loja\s*\]", re.I)
# Anúncio de vendedor: verbo de venda no início ou "vende-se" / "vendo" no título.
ANUNCIO = re.compile(r"^\s*(vendo|vende-se|vendemos|promo[cç][aã]o)\b|\bvende-se\b|\bvendo\b|\bgrow ?shop\b", re.I)
# Termos que também aparecem em notícias e debates: exigem revisão humana.
REVISAR = re.compile(r"\ba venda\b|\bpropaganda\b|\bloja\b|\bpromo[cç][aã]o\b|\bcupom\b|\banuncio\b|\bpatrocin", re.I)


def norm(s):
    return unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode().lower()


def texto_visivel(t):
    t = re.sub(r"(?is)<script.*?</script>|<style.*?</style>", " ", t)
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", t))


def titulo_de(t):
    m = re.search(r"<title>(.*?)</title>", t, re.S | re.I)
    return re.sub(r"\s+", " ", m.group(1)).split(" - ")[0].strip() if m else ""


def main():
    topicos = bm.topicos_baixados()
    saida = []
    cont = Counter()
    for url, capturas in topicos.items():
        loc = capturas[-1]
        titulo = titulo_de(bm.ler_pagina(loc))
        tn = norm(titulo)
        if MARCA_LOJA.search(titulo) or ANUNCIO.search(titulo) or ANUNCIO.search(tn):
            nivel = "comercial_forte"
        elif REVISAR.search(titulo) or REVISAR.search(tn):
            nivel = "revisar"
        else:
            continue
        cont[nivel] += 1
        saida.append({"url": url, "titulo": titulo, "nivel": nivel, "local": loc})
    caminho = os.path.join(bm.BASE, "comercial-marcados.jsonl")
    with open(caminho, "w", encoding="utf-8") as fh:
        for r in saida:
            fh.write(json.dumps(r, ensure_ascii=False) + "\n")
    print("topicos analisados:", len(topicos), dict(cont))
    print("indice:", caminho)


if __name__ == "__main__":
    main()
