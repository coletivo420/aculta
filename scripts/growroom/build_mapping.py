"""Mapeamento tópico -> fórum e autor -> perfil, a partir do que já foi baixado do Wayback (somente leitura por padrão).

Regra (docs/IMPORTACAO-GROWROOM.md): importar só usuários que comentaram em tópicos de fóruns importados.
Dois templates do Growroom são lidos (o arquivo mistura os dois):
- IPB (template antigo):
  - Fórum do tópico: <link rel='up' href='.../forum/<id>-...'> da própria página.
  - Autores: texto de <span class="author vcard"> em cada post; perfil só pelo nome (slug).
- IPS 4 (template atual):
  - Fórum do tópico: último link forum/<id>- dentro de <nav class='ipsBreadcrumb'>.
  - Autores: bloco cAuthorPane_author; quando há link de perfil, o ID do perfil vem exato.
- Perfil: casamento pelo ID exato (IPS 4) ou pelo nome (slug) com as URLs de perfil do índice CDX.
  Nomes com IDs diferentes ficam em relatório. Variações http/https/www do mesmo ID entram juntas.
Uso: sudo -n python3 -B build_mapping.py [--aplicar]
  --aplicar grava perfis-permitidos.json (usado pelo wayback_fetch.py) e o mapeamento em restauro/mapeamento/.
"""
import html as htmllib
import json
import os
import re
import sys
import unicodedata
from collections import Counter, defaultdict

BASE = "/home/aculta/private/aculta/forum/restauro"
CDX = "/home/aculta/private/aculta/forum/cdx/board-prefix-cdx-to-20250719.json"
EXCLUIDOS = {2, 52, 48, 76, 24}  # Avisos (2 e 52), Multimídia Cannábica (48), Cannabis Medicinal (76), Notícias (24)
SAIDA = os.path.join(BASE, "mapeamento")

RE_UP = re.compile(r"<link rel='up' href='[^']*/forum/(\d+)-")
RE_BREADCRUMB = re.compile(r"<nav class='ipsBreadcrumb[^']*'>(.*?)</nav>", re.S)
RE_FORUM_LINK = re.compile(r"/forum/(\d+)-")
RE_AUTOR_IPS = re.compile(r"cAuthorPane_author")
RE_PERFIL_IPS = re.compile(r"/board/profile/(\d+)-")
RE_NOME_IPS = re.compile(r'itemprop="name">(.*?)</strong>', re.S)


def slug(nome: str) -> str:
    s = unicodedata.normalize("NFKD", htmllib.unescape(nome)).encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")


def ler_pagina(local: str) -> str:
    with open(os.path.join(BASE, local), "rb") as fh:
        return fh.read().decode("iso-8859-1", "replace")


def topicos_baixados():
    vistos = {}
    with open(os.path.join(BASE, "manifest-html.jsonl"), encoding="utf-8") as fh:
        for linha in fh:
            try:
                r = json.loads(linha)
            except ValueError:
                continue
            if "/topic/" in r["original"] and r.get("local") and r.get("http") == 200:
                vistos[r["original"]] = r["local"]
    return vistos


def _texto(bruto: str) -> str:
    return re.sub(r"<[^>]+>", "", re.sub(r"\s+", " ", htmllib.unescape(bruto))).strip()


def analisar_topico(local: str):
    """Devolve (fórum ou None, [(nome, id do perfil ou None)])."""
    t = ler_pagina(local)
    m = RE_UP.search(t)
    forum = int(m.group(1)) if m else None
    if forum is None:
        ids = [i for nav in RE_BREADCRUMB.finditer(t) for i in RE_FORUM_LINK.findall(nav.group(1))]
        forum = int(ids[-1]) if ids else None
    autores = [(_texto(a), None) for a in re.findall(r'class="author vcard">(.*?)</span>', t, re.S)]
    for m in RE_AUTOR_IPS.finditer(t):
        bloco = t[m.start():m.start() + 900]
        nome = RE_NOME_IPS.search(bloco)
        if not nome:
            continue
        perfil = RE_PERFIL_IPS.search(nome.group(1))
        autores.append((_texto(nome.group(1)), perfil.group(1) if perfil else None))
    return forum, autores


def perfis_cdx():
    """(slug -> {id do perfil: set(URLs)}, id do perfil -> set(URLs))."""
    rows = json.load(open(CDX, encoding="utf-8"))[1:]
    por_slug = defaultdict(lambda: defaultdict(set))
    por_id = defaultdict(set)
    for ts, orig, status, mime, _d, _l in rows:
        if status != "200" or mime.split(";")[0].strip().lower() != "text/html":
            continue
        m = re.search(r"/board/profile/(\d+)-([^/?#]+)/?$", orig.lower())
        if m:
            por_slug[m.group(2)][m.group(1)].add(orig)
            por_id[m.group(1)].add(orig)
    return por_slug, por_id


def main():
    aplicar = "--aplicar" in sys.argv
    topicos = topicos_baixados()
    forum_de = {}
    autores_de = defaultdict(set)
    ids_ips = defaultdict(set)  # nome -> IDs de perfil exatos (IPS 4)
    sem_forum = 0
    for url, local in topicos.items():
        forum, autores = analisar_topico(local)
        if forum is None:
            sem_forum += 1
            continue
        forum_de[url] = forum
        if forum in EXCLUIDOS:
            continue
        for nome, perfil in autores:
            autores_de[nome].add(url)
            if perfil:
                ids_ips[nome].add(perfil)
    por_slug, por_id = perfis_cdx()
    casados, ambiguos, sem = {}, [], []
    for nome in sorted(autores_de):
        if ids_ips.get(nome):
            casados[nome] = sorted({u for pid in ids_ips[nome] for u in por_id.get(pid, ())})
            continue
        ids = por_slug.get(slug(nome), {})
        if len(ids) == 1:
            casados[nome] = sorted(next(iter(ids.values())))
        elif len(ids) > 1:
            ambiguos.append((nome, sorted(ids)))
        else:
            sem.append(nome)
    permitidos = sorted({u for urls in casados.values() for u in urls})
    contagem = Counter(forum_de.values())
    topicos_importados = sum(1 for f in forum_de.values() if f not in EXCLUIDOS)
    print(f"tópicos baixados: {len(topicos)}; com fórum identificado: {len(forum_de)}; sem fórum: {sem_forum}")
    print(f"tópicos em fóruns importados: {topicos_importados}; excluídos: {len(forum_de) - topicos_importados}")
    print(f"tópicos por fórum (top 12): {contagem.most_common(12)}")
    print(f"autores de tópicos importados: {len(autores_de)}; com ID exato (IPS 4): {len(ids_ips)}; "
          f"casados: {len(casados)}; ambíguos (IDs diferentes): {len(ambiguos)}; sem perfil: {len(sem)}; "
          f"URLs de perfil permitidas: {len(permitidos)}")
    print("ambíguos (até 5):", ambiguos[:5])
    if aplicar:
        os.makedirs(SAIDA, exist_ok=True)
        with open(os.path.join(BASE, "perfis-permitidos.json"), "w", encoding="utf-8") as fh:
            json.dump(permitidos, fh, ensure_ascii=False, indent=0)
        with open(os.path.join(SAIDA, "topico-forum.json"), "w", encoding="utf-8") as fh:
            json.dump(forum_de, fh, ensure_ascii=False, indent=0)
        with open(os.path.join(SAIDA, "autor-topicos.json"), "w", encoding="utf-8") as fh:
            json.dump({k: sorted(v) for k, v in autores_de.items()}, fh, ensure_ascii=False, indent=0)
        print("gravado: perfis-permitidos.json e mapeamento/")


if __name__ == "__main__":
    main()
