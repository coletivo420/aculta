#!/usr/bin/env python3
"""Gera o índice das frentes de teste de navegador.

Uso:
  python3 tests/browser/gerar-indice.py           # regenera os arquivos
  python3 tests/browser/gerar-indice.py --check   # sai com 1 se estiver desatualizado

Gera:
  - tests/browser/INDICE.md                      (índice completo, com caminho)
  - tabela entre os marcadores em tests/browser/README.md
  - tests/browser/<purpose>/README.md            (para pastas agrupadoras sem README)

Regra: uma frente é qualquer pasta com README.md em tests/browser/<purpose>/<frente>/
ou na raiz (legado). Não edite os arquivos gerados à mão: rode o script.
"""
import os
import sys

BASE = os.path.dirname(os.path.abspath(__file__))
MARK_INI = "<!-- INDICE:INICIO -->"
MARK_FIM = "<!-- INDICE:FIM -->"
SKIP_DIRS = {"node_modules", "__pycache__"}
# Pastas de artefatos (capturas) não são frentes de teste.
NOT_FRONTS = {"capturas"}


def title_of(readme_path):
    with open(readme_path, encoding="utf-8") as fh:
        for line in fh:
            if line.startswith("#"):
                return line.lstrip("#").strip()
    return ""


GENERATED = "Gerado por `tests/browser/gerar-indice.py`"


def is_generated(readme_path):
    with open(readme_path, encoding="utf-8") as fh:
        return GENERATED in fh.read(400)


def scan():
    """Retorna (frentes, containers).

    - Pasta da raiz com README escrito à mão é frente legada (ex.: aculta-game).
    - Subpasta com README dentro de uma pasta da raiz é frente com purpose = pasta.
    - Pasta da raiz sem README, ou com README gerado, é agrupadora.
    """
    frentes = []
    containers = set()
    for root in sorted(os.listdir(BASE)):
        rdir = os.path.join(BASE, root)
        if not os.path.isdir(rdir) or root in SKIP_DIRS or root.startswith(".") or root in NOT_FRONTS:
            continue
        rreadme = os.path.join(rdir, "README.md")
        if os.path.isfile(rreadme) and not is_generated(rreadme):
            frentes.append(entry(root, rdir, "raiz"))
        for sub in sorted(os.listdir(rdir)):
            sdir = os.path.join(rdir, sub)
            if os.path.isdir(sdir) and os.path.isfile(os.path.join(sdir, "README.md")):
                frentes.append(entry(f"{root}/{sub}", sdir, root))
                containers.add(root)
        if not os.path.isfile(rreadme) or is_generated(rreadme):
            containers.add(root)
    return frentes, sorted(containers)


def entry(rel, fdir, purpose):
    files = [f for f in os.listdir(fdir) if not f.startswith(".") and f != "README.md"]
    return {
        "path": rel,
        "title": title_of(os.path.join(fdir, "README.md")),
        "purpose": purpose,
        "routes": os.path.isfile(os.path.join(fdir, "routes.json")),
        "scripts": len([f for f in files if f.endswith((".py", ".mjs", ".js", ".sh"))]),
    }


def render_index(frentes):
    out = ["# Índice das frentes de teste de navegador", "",
           "Gerado por `tests/browser/gerar-indice.py`. Não edite à mão.", "",
           "Para encontrar um teste: procure a frente pelo nome e use o caminho da coluna **Pasta**.",
           "Scripts com `routes.json` têm cobertura de rotas públicas no runner comum.", "",
           "| Pasta | Frente | Scripts | routes.json |", "|---|---|---|---|"]
    for f in frentes:
        out.append(f"| [`tests/browser/{f['path']}/`](./{f['path']}/README.md) | {f['title']} | "
                   f"{f['scripts']} | {'sim' if f['routes'] else 'não'} |")
    return "\n".join(out) + "\n"


def render_table(frentes):
    lines = ["| Pasta | Frente |", "|---|---|"]
    for f in frentes:
        lines.append(f"| `tests/browser/{f['path']}/` | {f['title']} |")
    return "\n".join(lines)


def render_container(purpose, frentes):
    items = [f for f in frentes if f["purpose"] == purpose]
    out = [f"# Frentes de `{purpose}`", "",
           "Gerado por `tests/browser/gerar-indice.py`. Não edite à mão.", "",
           "Cada frente tem o próprio `README.md` com URLs, pré-requisitos e critérios.", ""]
    if not items:
        out.append("_Sem frentes ainda._")
    for f in items:
        name = f["path"].split("/", 1)[1]
        out.append(f"- [`{name}/`](./{name}/README.md) — {f['title']}")
    return "\n".join(out) + "\n"


def splice_readme(frentes):
    path = os.path.join(BASE, "README.md")
    with open(path, encoding="utf-8") as fh:
        text = fh.read()
    if MARK_INI not in text or MARK_FIM not in text:
        raise SystemExit("README.md sem marcadores INDICE:INICIO/FIM")
    head, rest = text.split(MARK_INI, 1)
    _, tail = rest.split(MARK_FIM, 1)
    body = (f"\n\nÍndice completo, com contagem de scripts e `routes.json`: "
            f"[`INDICE.md`](./INDICE.md).\n\n{render_table(frentes)}\n")
    return head + MARK_INI + body + MARK_FIM + tail


def build():
    frentes, containers = scan()
    files = {os.path.join(BASE, "INDICE.md"): render_index(frentes),
             os.path.join(BASE, "README.md"): splice_readme(frentes)}
    for purpose in containers:
        path = os.path.join(BASE, purpose, "README.md")
        if not os.path.exists(path) or is_generated(path):
            files[path] = render_container(purpose, frentes)
    return files


def main():
    files = build()
    check = "--check" in sys.argv
    stale = []
    for path, content in files.items():
        current = open(path, encoding="utf-8").read() if os.path.exists(path) else None
        if current != content:
            stale.append(os.path.relpath(path, BASE))
            if not check:
                os.makedirs(os.path.dirname(path), exist_ok=True)
                with open(path, "w", encoding="utf-8") as fh:
                    fh.write(content)
    if check:
        if stale:
            print("Índice desatualizado:", ", ".join(stale))
            print("Rode: python3 tests/browser/gerar-indice.py")
            return 1
        print("Índice atualizado.")
        return 0
    print(f"{len(files)} arquivos verificados; atualizados: {len(stale)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
