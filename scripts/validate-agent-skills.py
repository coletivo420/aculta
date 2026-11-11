#!/usr/bin/env python3
"""Valida estrutura e referências locais das Skills de projeto, sem dependências externas."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CANONICAL = ROOT / ".agents" / "skills"
AGENTS = (".claude/skills", ".codex/skills")
EXPECTED_EVALS = {"A", "B", "C", "D", "E", "F", "G1", "G2", "G3", "G4", "G5", "G6", "G7", "G8", "G9", "G10", "G11", "G12"}
ERRORS: list[str] = []


def fail(message: str) -> None:
    ERRORS.append(message)


def frontmatter(path: Path) -> tuple[str, str] | None:
    content = path.read_text(encoding="utf-8")
    match = re.match(r"\A---\n(.*?)\n---(?:\n|$)", content, re.S)
    if not match:
        fail(f"frontmatter ausente/inválido: {path.relative_to(ROOT)}")
        return None
    fields = dict((key, value.strip().strip("\"'")) for key, value in re.findall(r"^(name|description):\s*(.*?)\s*$", match.group(1), re.M))
    if not fields.get("name") or not fields.get("description"):
        fail(f"frontmatter precisa de name e description: {path.relative_to(ROOT)}")
        return None
    return fields["name"], fields["description"]


def check_links(path: Path) -> None:
    for target in re.findall(r"\[[^]]*\]\(([^)]+)\)", path.read_text(encoding="utf-8")):
        if target.startswith(("https://", "http://", "mailto:", "#")):
            continue
        local = target.split("#", 1)[0]
        if local and not (path.parent / local.replace("%20", " ")).resolve().exists():
            fail(f"referência local ausente em {path.relative_to(ROOT)}: {target}")


def main() -> int:
    if not CANONICAL.is_dir():
        fail("diretório canônico .agents/skills ausente")
        return 1
    names = sorted(p.name for p in CANONICAL.iterdir() if p.is_dir())
    for name in names:
        base = CANONICAL / name
        skill = base / "SKILL.md"
        if not skill.is_file():
            fail(f"SKILL.md ausente: {skill.relative_to(ROOT)}")
            continue
        meta = frontmatter(skill)
        if meta and meta[0] != name:
            fail(f"name diverge do diretório: {skill.relative_to(ROOT)}")
        for doc in base.rglob("*.md"):
            check_links(doc)
        for script in base.rglob("*.py"):
            if script.stat().st_mode & 0o111 and not script.read_text(encoding="utf-8").startswith("#!"):
                fail(f"script executável sem shebang: {script.relative_to(ROOT)}")
    for rel in AGENTS:
        directory = ROOT / rel
        listed = sorted(p.name for p in directory.iterdir()) if directory.is_dir() else []
        if listed != names:
            fail(f"inventário divergente em {rel}: esperado {names}, encontrado {listed}")
        for name in names:
            link = directory / name
            if not link.is_symlink() or link.resolve() != (CANONICAL / name).resolve():
                fail(f"entrada deve apontar para fonte canônica: {link.relative_to(ROOT)}")
            elif Path(link.readlink()).is_absolute():
                fail(f"symlink absoluto não portátil: {link.relative_to(ROOT)}")
    compat = ROOT / "docs/agent-skills"
    if not compat.is_symlink() or compat.resolve() != CANONICAL.resolve():
        fail("docs/agent-skills deve ser alias relativo para .agents/skills")
    eval_file = ROOT / "docs/operations/TESTES-SKILLS-UI-SUITE.md"
    ids = set(re.findall(r"^\| (A|B|C|D|E|F|G\d+) \|", eval_file.read_text(encoding="utf-8"), re.M))
    expected = {"A", "B", "C", "D", "E", "F", *(f"G{i}" for i in range(1, 13))}
    if ids != expected:
        fail(f"casos de avaliação incompletos: faltam {sorted(expected - ids)}")
    runtime_evals = ROOT / "docs/operations/TESTES-SKILLS-RUNTIME-SYNC.md"
    if not runtime_evals.is_file():
        fail("matriz de avaliação Runtime Sync ausente")
    else:
        runtime_ids = set(re.findall(r"^\| (RS-\d{2}) \|", runtime_evals.read_text(encoding="utf-8"), re.M))
        expected_runtime = {f"RS-{number:02d}" for number in range(1, 13)}
        if runtime_ids != expected_runtime:
            fail(f"casos Runtime Sync incompletos: faltam {sorted(expected_runtime - runtime_ids)}")
    if ERRORS:
        print("FALHA na validação de Skills:\n" + "\n".join(f"- {e}" for e in ERRORS))
        return 1
    print(f"OK: {len(names)} Skills canônicas, dois índices de agente, referências locais e {len(ids)} casos.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
