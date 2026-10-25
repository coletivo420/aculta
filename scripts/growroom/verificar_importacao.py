#!/usr/bin/env python3
"""Verifica os dados preparados para a importação do Growroom. Somente leitura: não altera o acervo.

Checagens:
- C1 manifestos: último registro válido de cada URL aponta para arquivo existente com SHA-256 igual.
- C2 duplicatas: caminhos canônico e duplicados existem e têm o mesmo conteúdo.
- C3 truncados: arquivos marcados continuam com 1 MiB e sem marcador JPEG final (FFD9).
- C4 comerciais: URLs marcadas existem entre os tópicos baixados.
- C5 links: destinos somam o total de alvos; imagens de conteúdo nunca vão para descartar.
- C6 perfis: URLs de perfil permitidas existem no CDX com status 200 e text/html.
- C7 mapeamento: arquivos de mapeamento batem com a versão atual do build_mapping (mesmos tópicos).
- C8 dados pessoais: arquivos de importação não contêm e-mail.

Uso: sudo -n python3 -B -I verificar_importacao.py [--rapido]
  --rapido pula a leitura completa dos arquivos (C1 e C3 só checam existência e tamanho).
Saída: restauro/verificacao-importacao.json e resumo na tela.
"""
import collections
import hashlib
import json
import os
import re
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build_mapping as bm  # noqa: E402

R = bm.BASE
CDX = bm.CDX
RAPIDO = "--rapido" in sys.argv
EMAIL = re.compile(rb"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}")


def jsonl(nome):
    with open(os.path.join(R, nome), encoding="utf-8") as fh:
        for linha in fh:
            if linha.strip():
                yield json.loads(linha)


def caminho(rel):
    return os.path.join(R, rel)


def sha(p):
    h = hashlib.sha256()
    with open(p, "rb") as fh:
        for bloco in iter(lambda: fh.read(1 << 20), b""):
            h.update(bloco)
    return h.hexdigest()


def c1_manifestos():
    ultimo = {}
    for nome in ("manifest-html.jsonl", "manifest-assets.jsonl"):
        for r in jsonl(nome):
            if r.get("local") and r.get("http") == 200:
                ultimo[(nome, r["original"])] = r
    ausentes = divergentes = 0
    for r in ultimo.values():
        p = caminho(r["local"])
        if not os.path.isfile(p):
            ausentes += 1
        elif not RAPIDO and sha(p) != r["sha256"]:
            divergentes += 1
    return {"registros_validos": len(ultimo), "arquivo_ausente": ausentes, "sha_divergente": divergentes,
            "ok": ausentes == 0 and divergentes == 0}


def c2_duplicatas():
    problemas = 0
    total = 0
    for g in jsonl("duplicatas.jsonl"):
        total += 1
        for rel in [g["canonico"]] + g["duplicados"]:
            if not os.path.isfile(caminho(rel)):
                problemas += 1
        if RAPIDO:
            continue
        s = sha(caminho(g["canonico"]))
        for rel in g["duplicados"]:
            if os.path.isfile(caminho(rel)) and sha(caminho(rel)) != s:
                problemas += 1
    return {"grupos": total, "problemas": problemas, "ok": problemas == 0}


def c3_truncados():
    problemas = 0
    total = 0
    for r in jsonl("truncados.jsonl"):
        total += 1
        for rel in r["capturas_truncadas"]:
            p = caminho(rel)
            if not os.path.isfile(p):
                problemas += 1
                continue
            if os.path.getsize(p) != 1048576:
                problemas += 1
            elif not RAPIDO:
                with open(p, "rb") as fh:
                    if fh.read().rstrip().endswith(b"\xff\xd9"):
                        problemas += 1
    return {"originais": total, "problemas": problemas, "ok": problemas == 0}


def c4_comerciais(topicos):
    fora = sum(1 for r in jsonl("comercial-marcados.jsonl") if r["url"] not in topicos)
    return {"marcados": sum(1 for _ in jsonl("comercial-marcados.jsonl")), "fora_dos_topicos": fora, "ok": fora == 0}


def c5_links():
    registros = list(jsonl("links-mapa.jsonl"))
    total = len(registros)
    resumo = collections.Counter(r["destino"] for r in registros)
    erros = 0
    for r in registros:
        if r["tipo"] == "imagem_conteudo" and r["destino"] == "descartar":
            erros += 1
    return {"alvos": total, "por_destino": dict(resumo), "imagem_conteudo_descartada": erros,
            "ok": erros == 0 and sum(resumo.values()) == total}


def c6_perfis():
    perfis = json.load(open(caminho("perfis-permitidos.json"), encoding="utf-8"))
    ok_cdx = set()
    for linha in json.load(open(CDX, encoding="utf-8"))[1:]:
        ts, orig, st, mime, _d, _l = linha
        if st == "200" and mime.split(";")[0].strip().lower() == "text/html" and "v_profile" in orig.lower() \
                or st == "200" and mime.split(";")[0].strip().lower() == "text/html" and "/profile/" in orig.lower():
            ok_cdx.add(orig)
    fora = [u for u in perfis if u not in ok_cdx]
    return {"urls": len(perfis), "sem_captura_200_no_cdx": len(fora), "ok": not fora}


def c7_mapeamento(topicos):
    """Compara o mapeamento salvo com o que o build_mapping atual produz."""
    salvo = json.load(open(caminho("mapeamento/topico-forum.json"), encoding="utf-8"))
    atual = {}
    for url, caps in topicos.items():
        analisados = [bm.analisar_topico(loc) for loc in caps]
        com = [f for f, _a in analisados if f is not None]
        if com:
            atual[url] = com[-1]
    so_salvo = len(set(salvo) - set(atual))
    so_atual = len(set(atual) - set(salvo))
    diverg = sum(1 for u in set(salvo) & set(atual) if str(salvo[u]) != str(atual[u]))
    return {"salvo": len(salvo), "atual": len(atual), "so_no_salvo": so_salvo, "so_na_versao_atual": so_atual,
            "fórum_diferente": diverg, "ok": so_salvo == 0 and so_atual == 0 and diverg == 0}


def c8_pii():
    achados = collections.Counter()
    for nome in ("mapeamento/autor-topicos.json", "mapeamento/topico-forum.json", "perfis-permitidos.json"):
        with open(caminho(nome), "rb") as fh:
            achados[nome] = len(EMAIL.findall(fh.read()))
    return {"emails_encontrados": dict(achados), "ok": sum(achados.values()) == 0}


def c9_conjunto(topicos):
    """Conjunto de importação: só tópicos baixados, nenhum comercial e exceções só de fórum excluído."""
    conjunto = json.load(open(caminho("conjunto-importacao.json"), encoding="utf-8"))
    comerciais = bm.comerciais_forte()
    fora_do_acervo = sum(1 for c in conjunto if c["url"] not in topicos)
    # Comercial só entra por escolha explícita (seleção manual), nunca pela regra automática.
    comerciais_dentro = sum(1 for c in conjunto
                            if c["url"] in comerciais and bm.thread_id(c["url"]) not in bm.SELECAO_MANUAL)
    def forum_valor(valor):
        return valor if valor.startswith("thw:") else int(valor)

    excecao_sem_exclusao = sum(1 for c in conjunto
                               if c["motivo"] == "excecao_tematica" and not bm.excluido(forum_valor(c["forum"])))
    return {"tópicos": len(conjunto), "fora_do_acervo": fora_do_acervo, "comerciais_dentro": comerciais_dentro,
            "excecao_sem_exclusao": excecao_sem_exclusao,
            "ok": fora_do_acervo == 0 and comerciais_dentro == 0 and excecao_sem_exclusao == 0}


def main():
    inicio = time.time()
    topicos = bm.topicos_baixados()
    resultado = {
        "C1_manifestos": c1_manifestos(),
        "C2_duplicatas": c2_duplicatas(),
        "C3_truncados": c3_truncados(),
        "C4_comerciais": c4_comerciais(topicos),
        "C5_links": c5_links(),
        "C6_perfis": c6_perfis(),
        "C7_mapeamento": c7_mapeamento(topicos),
        "C8_dados_pessoais": c8_pii(),
        "C9_conjunto": c9_conjunto(topicos),
    }
    resultado["_modo"] = "rapido" if RAPIDO else "completo"
    resultado["_segundos"] = round(time.time() - inicio)
    with open(caminho("verificacao-importacao.json"), "w", encoding="utf-8") as fh:
        json.dump(resultado, fh, ensure_ascii=False, indent=1)
    for k, v in resultado.items():
        if k.startswith("_"):
            continue
        print(("OK   " if v.get("ok") else "FALHA"), k, {x: y for x, y in v.items() if x != "ok"})
    falhas = [k for k, v in resultado.items() if not k.startswith("_") and not v.get("ok")]
    print("resultado:", "todas as checagens passaram" if not falhas else f"falhas: {falhas}")


if __name__ == "__main__":
    main()
