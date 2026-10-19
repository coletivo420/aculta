#!/usr/bin/env python3
"""Mapeia os links para growroom.net citados nas páginas arquivadas e decide o destino de cada um na importação.

Regras (decisão do responsável, 2026-10-19):
- Link que resolve para uma captura válida do acervo: vira link interno da importação.
- Link quebrado (alvo sem captura válida): aponta para o modal de conteúdo legado
  (`data-legado-growroom`), que informa a origem do conteúdo e conta a história do Growroom.
- Anúncios de terceiros e recursos de tema antigo não entram como link: são descartados na importação.

Saídas (derivadas, fora do Git, em restauro/):
- links-mapa.jsonl: um registro por alvo, com classe, tipo e destino.
- links-resumo.json: totais por classe e tipo.

Uso:
  sudo -n python3 -B mapear_links.py                # mapeia e grava
  sudo -n python3 -B mapear_links.py --testar-vivo N  # além disso, testa N alvos quebrados e N resolvidos no site vivo
"""
import collections
import html
import json
import os
import random
import re
import sys
import time
import urllib.parse
import urllib.request
import urllib.error

BASE = "/home/aculta/private/aculta/forum/restauro"
CDX = "/home/aculta/private/aculta/forum/cdx/board-prefix-cdx-to-20250719.json"
UA = "ACULTA-restauro-forum/1.0 (verificacao de links do acervo; contato: coletivo420)"
RE_LINK = re.compile(r"""(?:href|src)\s*=\s*["']([^"']+)["']""", re.I)
RE_DOMINIO = re.compile(r"^(https?:)?//([a-z0-9-]+\.)*growroom\.net(:80)?(/|$)", re.I)


def norm(u: str) -> str:
    """Chave canônica: host sem www, sem :80, sem fragmento e sem barra final."""
    u = html.unescape(u).strip()
    u = re.sub(r"^(https?://)?(www\.)?growroom\.net(:80)?", "http://growroom.net", u, flags=re.I)
    return u.split("#")[0].rstrip("/") if u.count("/") > 3 else u.split("#")[0]


def absoluto(a: str) -> str:
    if a.startswith("http"):
        return a
    if a.startswith("//"):
        return "http:" + a
    if a.startswith("/"):
        return "http://www.growroom.net" + a
    return "http://www.growroom.net/" + a


def tipo(alvo: str) -> str:
    p = urllib.parse.urlsplit(alvo.replace("&amp;", "&"))
    path = p.path.lower()
    q = urllib.parse.parse_qs(p.query)
    if re.search(r"\.(jpe?g|png|gif|css|js|ico|svg|woff2?|ttf)$", path):
        return "recurso_estatico"
    if "openx" in path or "/ads" in path or "delivery" in path:
        return "anuncio_terceiro"
    if any(k in q for k in ("do", "act", "sortby", "tab", "action", "s")):
        return "acao_navegacao"
    if path in ("", "/", "/board", "/board/"):
        return "raiz"
    if "profile" in path or "userid" in q or "showuser" in q:
        return "perfil"
    if "topic" in path or "showtopic" in q or "threadid" in q:
        return "topico"
    if "gallery" in path or "show_pic" in q or "list_pics" in path:
        return "galeria"
    if "forum" in path or "showforum" in q or "boardid" in q:
        return "forum"
    return "outro"


def destino(classe: str, tp: str) -> str:
    if classe == "resolve_no_acervo":
        return "link_interno"
    if tp in ("anuncio_terceiro", "recurso_estatico"):
        return "descartar"
    return "modal_legado"


def coletar_alvos():
    """Alvo bruto -> (ocorrências, conjunto de páginas de origem), em páginas com captura válida."""
    ultimo = {}
    with open(os.path.join(BASE, "manifest-html.jsonl"), encoding="utf-8") as fh:
        for linha in fh:
            r = json.loads(linha)
            if r.get("local") and r.get("http") == 200:
                ultimo[r["original"]] = r["local"]
    ocorr = collections.Counter()
    origem = collections.defaultdict(set)
    for o, loc in ultimo.items():
        with open(os.path.join(BASE, loc), "rb") as fh:
            t = fh.read().decode("iso-8859-1", "replace")
        for a in RE_LINK.findall(t):
            a = html.unescape(a).strip()
            if a.startswith(("#", "javascript:", "mailto:", "data:")):
                continue
            if RE_DOMINIO.match(a) or a.startswith("/board"):
                ab = absoluto(a)
                ocorr[ab] += 1
                origem[ab].add(o)
    return ocorr, origem


def indices():
    manif = collections.defaultdict(list)
    for nome in ("manifest-html.jsonl", "manifest-assets.jsonl"):
        with open(os.path.join(BASE, nome), encoding="utf-8") as fh:
            for linha in fh:
                r = json.loads(linha)
                manif[norm(r["original"])].append(r)
    cdx = collections.defaultdict(set)
    with open(CDX, encoding="utf-8") as fh:
        for ts, o, st, _m, _d, _l in json.load(fh)[1:]:
            cdx[norm(o)].add(st)
    return manif, cdx


def classificar(alvo, manif, cdx):
    k = norm(alvo)
    ok = [r for r in manif.get(k, []) if r.get("local") and r.get("http") == 200
          and os.path.isfile(os.path.join(BASE, r["local"]))]
    if ok:
        return "resolve_no_acervo"
    if k in cdx:
        return "cdx_sem_captura_valida:" + ",".join(sorted(cdx[k]))
    return "fora_do_inventario"


def testar_vivo(alvos, n):
    """Amostra de alvos: GET no site vivo, com pausa entre requisições. Não baixa corpo além de 2 KB."""
    amostra = random.Random(7).sample(alvos, min(n, len(alvos)))
    resultados = []
    for a in amostra:
        req = urllib.request.Request(a, headers={"User-Agent": UA}, method="GET")
        try:
            with urllib.request.urlopen(req, timeout=20) as r:
                r.read(2048)
                resultados.append((a, r.status))
        except urllib.error.HTTPError as err:
            resultados.append((a, err.code))
        except Exception as err:  # rede, DNS, timeout
            resultados.append((a, type(err).__name__))
        time.sleep(1.5)
    return resultados


def main():
    ocorr, origem = coletar_alvos()
    manif, cdx = indices()
    registros = []
    resumo = collections.Counter()
    for alvo, n in ocorr.items():
        classe = classificar(alvo, manif, cdx)
        tp = tipo(alvo)
        dest = destino(classe, tp)
        registros.append({"alvo": alvo, "ocorrencias": n, "paginas_origem": len(origem[alvo]),
                          "classe": classe, "tipo": tp, "destino": dest})
        resumo[f"destino:{dest}"] += 1
        resumo[f"classe:{classe.split(':')[0]}"] += 1
    with open(os.path.join(BASE, "links-mapa.jsonl"), "w", encoding="utf-8") as fh:
        for r in registros:
            fh.write(json.dumps(r, ensure_ascii=False) + "\n")
    with open(os.path.join(BASE, "links-resumo.json"), "w", encoding="utf-8") as fh:
        json.dump({"alvos": len(registros), "ocorrencias": sum(ocorr.values()), **resumo}, fh,
                  ensure_ascii=False, indent=1)
    print(f"alvos: {len(registros)}; ocorrências: {sum(ocorr.values())}")
    for k, v in sorted(resumo.items()):
        print(f"  {k}: {v}")
    if "--testar-vivo" in sys.argv:
        n = int(sys.argv[sys.argv.index("--testar-vivo") + 1])
        quebrados = [r["alvo"] for r in registros if r["destino"] == "modal_legado"]
        resolvidos = [r["alvo"] for r in registros if r["destino"] == "link_interno"]
        for rotulo, lista in (("quebrados", quebrados), ("resolvidos", resolvidos)):
            print(f"teste vivo ({rotulo}, amostra {n}):")
            cont = collections.Counter(s for _, s in testar_vivo(lista, n))
            print("  ", dict(cont))


if __name__ == "__main__":
    main()
