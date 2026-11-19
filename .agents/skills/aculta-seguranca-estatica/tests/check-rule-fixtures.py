#!/usr/bin/env python3
"""Verifica as regras locais do Semgrep contra as fixtures anotadas.

Uso: python3 -I check-rule-fixtures.py <semgrep-binario> <regras.yml> <fixture>

Cada linha "// ruleid: <id>" precisa gerar pelo menos um achado com esse id
na linha seguinte. Cada linha "// ok: <id>" não pode gerar achado do id.
Sai com código 1 se alguma expectativa falhar. Não imprime conteúdo do código.
"""
import json
import os
import re
import subprocess
import sys
import tempfile

ANNOT = re.compile(r"//\s*(ruleid|ok):\s*([A-Za-z0-9_.-]+)")


def main() -> int:
    if len(sys.argv) != 4:
        print(__doc__)
        return 2
    semgrep, rules, fixture = sys.argv[1:4]
    expected = []  # (line_do_codigo, tipo, rule_id)
    with open(fixture, encoding="utf-8") as fh:
        lines = fh.read().splitlines()
    for idx, text in enumerate(lines, start=1):
        m = ANNOT.search(text)
        if m:
            expected.append((idx + 1, m.group(1), m.group(2)))

    with tempfile.TemporaryDirectory(prefix="aculta-rules-") as tmp:
        out = os.path.join(tmp, "out.json")
        subprocess.run(
            [semgrep, "--metrics=off", "--config", rules, "--json", "-o", out, fixture],
            check=False,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        with open(out, encoding="utf-8") as fh:
            results = json.load(fh).get("results", [])

    found = {(r["start"]["line"], r["check_id"].split(".")[-1]) for r in results}
    failures = 0
    for line, kind, rule_id in expected:
        hit = (line, rule_id) in found
        if kind == "ruleid" and not hit:
            print(f"FALHA: esperado achado {rule_id} na linha {line}")
            failures += 1
        if kind == "ok" and hit:
            print(f"FALHA: achado indevido {rule_id} na linha {line}")
            failures += 1
    total = len(expected)
    print(f"fixtures: {total} expectativas, {failures} falhas")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
