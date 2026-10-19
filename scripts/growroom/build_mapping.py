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
  --so-mapeamento grava só restauro/mapeamento/ (não altera a fila de perfis).
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
# THWboard (formato antigo, showtopic.php?threadid=N): fórum no breadcrumb, autor por userid
EXCLUIDOS_THW = {2, 24, 28}  # boardid do THWboard: Avisos (2), Notícias (24), Cannabis Medicinal (28)
RE_THW_BC = re.compile(r"board\.php\?boardid=(\d+)[^>]*>([^<]{1,80})</a>\s*(?:&raquo;|»)?\s*(?:<[^>]+>\s*)*"
                       r"<a[^>]*showtopic\.php\?threadid=\d+")
# Conjunto de importação: tópicos excluídos que ficam por decisão do responsável (2026-10-19).
# Critério de título, o mesmo da triagem: temas políticos e sociais e casos individuais ficam; vida interna não.
_NORM = lambda s: unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode().lower()  # noqa: E731
RE_INTERNO = re.compile(r"usuario do (mes|ano)|rifa|growroom\b|encontro|evento|promo|sorteio|concurso|votacao", re.I)
RE_INDIVIDUAL = re.compile(r"\bpreso|\bpresa|cerco|cultivador|portador|motorista|denuncia|acusad|condenad", re.I)
RE_POLITICO = re.compile(r"legaliz|marcha|descriminal|\blei\b|projeto de lei|stf|stj|politica|governador|deputad|"
                         r"senador|anvisa|\bsus\b|medicinal|direitos|proibi|guerra|repress|justica", re.I)
EXCECOES_TEMATICAS = {"politico_social", "caso_individual"}
COMERCIAIS_ARQUIVO = os.path.join(BASE, "comercial-marcados.jsonl")
EMAIL_NOME = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+")  # nome com forma de e-mail: omitido do mapa
RE_THW_AUTOR = re.compile(r'v_profile\.php\?userid=(\d+)"\s*>\s*<img[^>]*alt="Ver o Perfil de ([^"]+)"')


def slug(nome: str) -> str:
    s = unicodedata.normalize("NFKD", htmllib.unescape(nome)).encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")


_CHARSET_CABECALHO = None


def _charset_cabecalho() -> dict:
    """local -> charset declarado no cabeçalho HTTP da captura (manifesto de html)."""
    global _CHARSET_CABECALHO
    if _CHARSET_CABECALHO is None:
        _CHARSET_CABECALHO = {}
        with open(os.path.join(BASE, "manifest-html.jsonl"), encoding="utf-8") as fh:
            for linha in fh:
                try:
                    r = json.loads(linha)
                except ValueError:
                    continue
                ct = (r.get("content_type") or "").lower()
                if r.get("local") and "charset=" in ct:
                    _CHARSET_CABECALHO[r["local"]] = ct.split("charset=")[-1].strip().strip('"\'')
    return _CHARSET_CABECALHO


def decodificar(dados: bytes, charset_cabecalho: str | None) -> str:
    """Ordem: cabeçalho HTTP, meta charset, UTF-8 válido e, por último, cp1252 (superconjunto do ISO-8859-1)."""
    m = re.search(rb"charset=[\"']?([a-zA-Z0-9_-]+)", dados[:4000])
    candidatos = [charset_cabecalho, m.group(1).decode("ascii") if m else None, "utf-8"]
    for cs in candidatos:
        if not cs:
            continue
        try:
            return dados.decode(cs)
        except (UnicodeDecodeError, LookupError):
            continue
    return dados.decode("cp1252", "replace")


def ler_pagina(local: str) -> str:
    with open(os.path.join(BASE, local), "rb") as fh:
        dados = fh.read()
    return decodificar(dados, _charset_cabecalho().get(local))


def topicos_baixados():
    """URL -> lista de capturas locais válidas (todas, não só a última: capturas antigas podem ter fórum/autor)."""
    vistos = defaultdict(list)
    with open(os.path.join(BASE, "manifest-html.jsonl"), encoding="utf-8") as fh:
        for linha in fh:
            try:
                r = json.loads(linha)
            except ValueError:
                continue
            o = r["original"]
            if ("/topic/" in o or "showtopic.php?threadid=" in o.lower()) and r.get("local") and r.get("http") == 200:
                if r["local"] not in vistos[o]:
                    vistos[o].append(r["local"])
    return dict(vistos)


def categoria_titulo(titulo: str) -> str:
    n = _NORM(titulo)
    if RE_INTERNO.search(n):
        return "vida_interna"
    if RE_INDIVIDUAL.search(n):
        return "caso_individual"
    if RE_POLITICO.search(n):
        return "politico_social"
    return "outros"


def titulo_da_captura(local: str) -> str:
    m = re.search(r"<title>(.*?)</title>", ler_pagina(local), re.S | re.I)
    return re.sub(r"\s+", " ", htmllib.unescape(m.group(1))).split(" - ")[0].strip() if m else ""


def comerciais_forte() -> set:
    """URLs marcadas como comerciais de anúncio (marcar_comercial.py). Saem da importação."""
    if not os.path.isfile(COMERCIAIS_ARQUIVO):
        return set()
    with open(COMERCIAIS_ARQUIVO, encoding="utf-8") as fh:
        return {r["url"] for r in map(json.loads, filter(str.strip, fh)) if r["nivel"] == "comercial_forte"}


def excluido(forum) -> bool:
    """Fórum excluído pela regra de importação. IDs do THWboard ('thw:N') têm espaço próprio."""
    if isinstance(forum, str) and forum.startswith("thw:"):
        return int(forum[4:]) in EXCLUIDOS_THW
    return forum in EXCLUIDOS


def _texto(bruto: str) -> str:
    return re.sub(r"<[^>]+>", "", re.sub(r"\s+", " ", htmllib.unescape(bruto))).strip()


def analisar_topico(local: str):
    """Devolve (fórum ou None, [(nome, id de perfil IPS ou None, userid THWboard ou None)])."""
    t = ler_pagina(local)
    thw = RE_THW_BC.search(t)
    if thw:
        autores = [(_texto(nome), None, uid) for uid, nome in RE_THW_AUTOR.findall(t)]
        return f"thw:{thw.group(1)}", autores
    m = RE_UP.search(t)
    forum = int(m.group(1)) if m else None
    if forum is None:
        ids = [i for nav in RE_BREADCRUMB.finditer(t) for i in RE_FORUM_LINK.findall(nav.group(1))]
        forum = int(ids[-1]) if ids else None
    autores = [(_texto(a), None, None) for a in re.findall(r'class="author vcard">(.*?)</span>', t, re.S)]
    for m in RE_AUTOR_IPS.finditer(t):
        bloco = t[m.start():m.start() + 900]
        nome = RE_NOME_IPS.search(bloco)
        if not nome:
            continue
        perfil = RE_PERFIL_IPS.search(nome.group(1))
        autores.append((_texto(nome.group(1)), perfil.group(1) if perfil else None, None))
    return forum, autores


def perfis_cdx():
    """(slug -> {id do perfil: set(URLs)}, id do perfil -> set(URLs), userid THWboard -> set(URLs))."""
    rows = json.load(open(CDX, encoding="utf-8"))[1:]
    por_slug = defaultdict(lambda: defaultdict(set))
    por_id = defaultdict(set)
    por_uid = defaultdict(set)
    for ts, orig, status, mime, _d, _l in rows:
        if status != "200" or mime.split(";")[0].strip().lower() != "text/html":
            continue
        m = re.search(r"/board/profile/(\d+)-([^/?#]+)/?$", orig.lower())
        if m:
            por_slug[m.group(2)][m.group(1)].add(orig)
            por_id[m.group(1)].add(orig)
        m = re.search(r"v_profile\.php\?userid=(\d+)", orig.lower())
        if m:
            por_uid[m.group(1)].add(orig)
    return por_slug, por_id, por_uid


def main():
    aplicar = "--aplicar" in sys.argv
    so_mapeamento = "--so-mapeamento" in sys.argv
    topicos = topicos_baixados()
    forum_de = {}
    autores_de = defaultdict(set)
    ids_ips = defaultdict(set)  # nome -> IDs de perfil exatos (IPS 4)
    ids_thw = defaultdict(set)  # nome -> userids exatos (THWboard)
    sem_forum = 0
    omitidos_email = set()
    comerciais = comerciais_forte()
    comerciais_fora = 0
    conjunto = []
    for url, capturas in topicos.items():
        # Fórum: da captura mais recente que o identifica. Autores: de todas as capturas com fórum.
        analisadas = [analisar_topico(local) for local in capturas]
        com_forum = [(f, a) for f, a in analisadas if f is not None]
        if not com_forum:
            sem_forum += 1
            continue
        forum_de[url] = com_forum[-1][0]
        if url in comerciais:
            comerciais_fora += 1
            continue
        excecao = False
        if excluido(forum_de[url]):
            if categoria_titulo(titulo_da_captura(capturas[-1])) not in EXCECOES_TEMATICAS:
                continue
            excecao = True
        conjunto.append({"url": url, "forum": str(forum_de[url]),
                         "motivo": "excecao_tematica" if excecao else "forum_importado"})
        for forum, autores in com_forum:
            if excluido(forum) and not excecao:
                continue
            for nome, perfil, uid in autores:
                if EMAIL_NOME.search(nome):
                    # Contato pessoal digitado como nome: não é necessário para o casamento de perfil e não vai ao mapa.
                    omitidos_email.add(nome)
                    continue
                autores_de[nome].add(url)
                if perfil:
                    ids_ips[nome].add(perfil)
                if uid:
                    ids_thw[nome].add(uid)
    por_slug, por_id, por_uid = perfis_cdx()
    casados, ambiguos, sem = {}, [], []
    for nome in sorted(autores_de):
        if ids_thw.get(nome):
            casados[nome] = sorted({u for uid in ids_thw[nome] for u in por_uid.get(uid, ())})
            continue
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
    topicos_importados = sum(1 for f in forum_de.values() if not excluido(f))
    print(f"tópicos baixados: {len(topicos)}; com fórum identificado: {len(forum_de)}; sem fórum: {sem_forum}")
    print(f"tópicos em fóruns importados: {topicos_importados}; excluídos: {len(forum_de) - topicos_importados}")
    print(f"tópicos por fórum (top 12): {contagem.most_common(12)}")
    print(f"autores de tópicos importados: {len(autores_de)}; com ID exato (IPS 4): {len(ids_ips)}; "
          f"casados: {len(casados)}; ambíguos (IDs diferentes): {len(ambiguos)}; sem perfil: {len(sem)}; "
          f"URLs de perfil permitidas: {len(permitidos)}")
    print("ambíguos (até 5):", ambiguos[:5])
    print(f"nomes com forma de e-mail omitidos do mapa: {len(omitidos_email)}")
    print(f"conjunto de importação: {len(conjunto)} tópicos "
          f"({sum(1 for c in conjunto if c['motivo'] == 'forum_importado')} de fóruns importados, "
          f"{sum(1 for c in conjunto if c['motivo'] == 'excecao_tematica')} por exceção temática); "
          f"comerciais removidos: {comerciais_fora}")
    if aplicar or so_mapeamento:
        os.makedirs(SAIDA, exist_ok=True)
        if aplicar:
            # perfis-permitidos.json muda a fila de download: só com --aplicar, decisão do responsável.
            with open(os.path.join(BASE, "perfis-permitidos.json"), "w", encoding="utf-8") as fh:
                json.dump(permitidos, fh, ensure_ascii=False, indent=0)
        with open(os.path.join(SAIDA, "topico-forum.json"), "w", encoding="utf-8") as fh:
            json.dump(forum_de, fh, ensure_ascii=False, indent=0)
        with open(os.path.join(SAIDA, "autor-topicos.json"), "w", encoding="utf-8") as fh:
            json.dump({k: sorted(v) for k, v in autores_de.items()}, fh, ensure_ascii=False, indent=0)
        with open(os.path.join(BASE, "conjunto-importacao.json"), "w", encoding="utf-8") as fh:
            json.dump(conjunto, fh, ensure_ascii=False, indent=0)
        print("gravado:", "perfis-permitidos.json e " if aplicar else "", "mapeamento/", sep="")


if __name__ == "__main__":
    main()
