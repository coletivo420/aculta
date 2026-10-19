#!/usr/bin/env python3
"""Baixa do Wayback só o que foi capturado (CDX já filtrado pela F1), em modo "assets" ou "html".

Regras:
- Cada URL é baixada da captura exata do CDX (status 200), sem seguir para outra data.
- Nada é sobrescrito: caminho que colide com pasta/arquivo vai para html/__conflitos/<hash da URL>;
  captura diferente da mesma URL ganha sufixo com a data.
- Um registro é considerado válido quando o arquivo existe e o SHA-256 bate. Registros
  divergentes ou sem resposta são baixados de novo, sem tocar nos arquivos anteriores.
- Manifesto JSONL é só de acréscimo.
- Ordem da fila (html): 1 tópicos, 2 galerias e demais, 3 perfis. Perfis entram só se estiverem em
  perfis-permitidos.json (gerado por build_mapping.py, regra de usuários da importação).
- Downloads em paralelo com janela deslizante (WORKERS conexões); a gravação é sequencial.

Uso: sudo -n python3 -B wayback_fetch.py {assets|html}
Exige acesso root ao armazenamento privado (/home/aculta/private/aculta/forum).
"""
import hashlib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import FIRST_COMPLETED, ThreadPoolExecutor, wait

CDX = "/home/aculta/private/aculta/forum/cdx/board-prefix-cdx-to-20250719.json"
BASE = "/home/aculta/private/aculta/forum/restauro"
PERFIS_PERMITIDOS = os.path.join(BASE, "perfis-permitidos.json")
DELAY = 1.0      # pausa após cada requisição, em cada conexão
WORKERS = 2      # conexões simultâneas. Acima disso o Wayback recusou conexões nos testes.
JANELA = 8       # downloads em voo no máximo; limita a memória
UA = "ACULTA-restauro-forum/1.0 (recuperacao do proprio acervo; contato: coletivo420)"


def wanted_html(orig: str) -> bool:
    low = orig.lower()
    query = urllib.parse.urlsplit(low).query
    if query.startswith(("do=", "action=", "tab=", "sortby=")) or any(
        a in "&" + query for a in ("&do=", "&action=", "&tab=", "&sortby=")
    ):
        return False
    return ("/topic/" in low or "showtopic" in low or "/profile/" in low or "v_profile" in low
            or "/gallery/" in low or "show_pic" in low or "list_pics" in low or "popup_pic" in low)


def prioridade(orig: str) -> int:
    """1 tópicos, 2 galerias e demais, 3 perfis."""
    low = orig.lower()
    if "profile" in low or "showuser" in low:
        return 3
    if "/topic/" in low or "showtopic" in low:
        return 1
    return 2


def select(mode: str):
    with open(CDX, encoding="utf-8") as fh:
        rows = json.load(fh)[1:]
    latest = {}
    for ts, orig, status, mime, _d, _l in rows:
        if status != "200":
            continue
        m = mime.split(";")[0].strip().lower()
        if mode == "html":
            if m != "text/html" or not wanted_html(orig):
                continue
        elif m == "text/html" or m == "text/plain":
            continue
        if orig not in latest or ts > latest[orig][0]:
            latest[orig] = (ts, m)
    permitidos = set()
    if os.path.isfile(PERFIS_PERMITIDOS):
        with open(PERFIS_PERMITIDOS, encoding="utf-8") as fh:
            permitidos = set(json.load(fh))
    saida = [(ts, orig, m) for orig, (ts, m) in latest.items()
             if not (mode == "html" and ("profile" in orig.lower() or "showuser" in orig.lower()) and orig not in permitidos)]
    return sorted(saida, key=lambda r: (prioridade(r[1]), r[1]))


def root_for(mode: str) -> str:
    return os.path.join(BASE, "html" if mode == "html" else "assets")


def base_path(root: str, orig: str) -> str:
    p = urllib.parse.urlsplit(orig)
    host = re.sub(r"[^a-zA-Z0-9.-]", "_", p.hostname or "host")
    path = p.path or "/"
    if path.endswith("/"):
        path += "index"
    safe = re.sub(r"[^a-zA-Z0-9._/-]", "_", path).lstrip("/")
    parts = safe.split("/")
    if p.query:
        parts[-1] += "__q" + hashlib.sha1(p.query.encode()).hexdigest()[:10]
    return os.path.join(root, host, *parts)


def choose_path(root: str, orig: str, ts: str, body_sha: str) -> str:
    """Escolhe um caminho livre. Nunca sobrescreve arquivo de outra captura."""
    path = base_path(root, orig)
    # Conflito de tipo: um ancestral do caminho é arquivo, ou o alvo é pasta.
    conflito = os.path.isdir(path)
    cur = os.path.dirname(path)
    while cur.startswith(root) and cur != root:
        if os.path.isfile(cur):
            conflito = True
            break
        cur = os.path.dirname(cur)
    if conflito:
        # Renomear só o último componente não resolve conflito de ancestral: vai para pasta própria.
        path = os.path.join(root, "__conflitos", hashlib.sha1(orig.encode()).hexdigest())
    if os.path.exists(path):
        with open(path, "rb") as fh:
            if hashlib.sha256(fh.read()).hexdigest() == body_sha:
                return path
        root_name, ext = os.path.splitext(path)
        path = f"{root_name}__c{ts}{ext}"
    return path


def valid_records(manifest: str):
    """Último registro por URL e se ele ainda confere com o disco."""
    last = {}
    if os.path.exists(manifest):
        with open(manifest, encoding="utf-8") as fh:
            for line in fh:
                try:
                    r = json.loads(line)
                except ValueError:
                    continue
                last[r["original"]] = r
    ok = set()
    for orig, r in last.items():
        if r.get("http") == 200 and r.get("local"):
            p = os.path.join(BASE, r["local"])
            if os.path.isfile(p):
                with open(p, "rb") as fh:
                    if hashlib.sha256(fh.read()).hexdigest() == r.get("sha256"):
                        ok.add(orig)
    return ok


def fetch(ts: str, orig: str):
    url = f"https://web.archive.org/web/{ts}id_/{orig}"
    for attempt in range(4):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": UA})
            with urllib.request.urlopen(req, timeout=60) as resp:
                return resp.status, resp.geturl(), resp.headers.get("Content-Type", ""), resp.read()
        except urllib.error.HTTPError as err:
            if err.code in (404, 403, 410):
                return err.code, url, "", b""
        except Exception:
            pass
        time.sleep(5 * (attempt + 1))
    return None, url, "", b""


def _baixar(item):
    ts, orig, _mime = item
    resultado = fetch(ts, orig)
    time.sleep(DELAY)
    return item, resultado


def main(mode: str) -> None:
    root = root_for(mode)
    manifest = os.path.join(BASE, f"manifest-{mode}.jsonl")
    log_path = os.path.join(BASE, f"progress-{mode}.log")
    os.makedirs(root, exist_ok=True)
    rows = select(mode)
    ok = valid_records(manifest)
    pendentes = [r for r in rows if r[1] not in ok]
    print(f"{mode}: total {len(rows)}; válidos {len(ok)}; a baixar {len(pendentes)}", flush=True)
    fila = iter(pendentes)
    i = 0
    with open(manifest, "a", encoding="utf-8") as man, open(log_path, "a", encoding="utf-8") as log, \
            ThreadPoolExecutor(max_workers=WORKERS) as pool:
        em_voo = set()

        def reabastecer():
            while len(em_voo) < JANELA:
                item = next(fila, None)
                if item is None:
                    return
                em_voo.add(pool.submit(_baixar, item))

        reabastecer()
        while em_voo:
            concluidos, _ = wait(em_voo, return_when=FIRST_COMPLETED)
            for futuro in concluidos:
                em_voo.discard(futuro)
                (ts, orig, mime), (status, final, ctype, body) = futuro.result()
                i += 1
                rec = {"original": orig, "timestamp": ts, "mime_cdx": mime, "http": status,
                       "final_url": final, "content_type": ctype, "size": len(body)}
                if status == 200 and body:
                    sha = hashlib.sha256(body).hexdigest()
                    path = choose_path(root, orig, ts, sha)
                    try:
                        if not os.path.exists(path):
                            os.makedirs(os.path.dirname(path), exist_ok=True)
                            with open(path, "wb") as out:
                                out.write(body)
                        rec["local"] = os.path.relpath(path, BASE)
                        rec["sha256"] = sha
                    except OSError as err:
                        # Erro de gravação de um item não derruba a rodada: registra e segue.
                        rec["erro_gravacao"] = str(err)
                man.write(json.dumps(rec, ensure_ascii=False) + "\n")
                man.flush()
                log.write(f"{i}/{len(pendentes)} {status} {orig}\n")
                log.flush()
            reabastecer()
    print(f"{mode}: concluido", flush=True)


if __name__ == "__main__":
    main(sys.argv[1])
