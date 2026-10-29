#!/usr/bin/env python3
"""Regenerate the lexical responsive inventory for ACULTA420 theme sources."""

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
THEME = ROOT / "web/themes/custom/aculta420"
OUTPUT = THEME / "docs/theme-mobile-file-inventory.tsv"
SOURCE_SUFFIXES = {
    ".css": "estilo",
    ".scss": "estilo",
    ".sass": "estilo",
    ".js": "javascript",
    ".twig": "template",
    ".html": "template",
}
MEDIA = re.compile(r"@media\s+([^{}]+)\{", re.I)
CONTAINER = re.compile(r"@container\s+([^{}]+)\{", re.I)
WIDTH = re.compile(
    r"(?<![\w-])(?:(?:min|max)-)?(?:width|height|inline-size|block-size)\s*:\s*[^;{}]+",
    re.I,
)
OVERFLOW = re.compile(r"(?<![\w-])overflow(?:-[xy])?\s*:\s*[^;{}]+", re.I)
HEADER = "tipo\tcaminho\tmedia_count\tmedia_queries\tcontainer_count\twidth_related_matches\toverflow_declarations"


def joined(values):
    cleaned = [re.sub(r"\s+", " ", value.strip()) for value in values]
    return " | ".join(cleaned) if cleaned else "—"


def main():
    rows = [HEADER]
    counts = {"estilo": 0, "javascript": 0, "template": 0}
    for path in sorted(THEME.rglob("*")):
        if not path.is_file() or {"node_modules", "vendor"} & set(path.parts):
            continue
        kind = SOURCE_SUFFIXES.get(path.suffix.lower())
        if kind is None:
            continue
        counts[kind] += 1
        relative = path.relative_to(ROOT).as_posix()
        if kind != "estilo":
            rows.append(f"{kind}\t{relative}\t—\t—\t—\t—\t—")
            continue
        source = path.read_text(errors="replace")
        media = [match.group(1) for match in MEDIA.finditer(source)]
        containers = [match.group(1) for match in CONTAINER.finditer(source)]
        widths = [match.group(0) for match in WIDTH.finditer(source)]
        overflows = [match.group(0) for match in OVERFLOW.finditer(source)]
        rows.append("\t".join((
            kind,
            relative,
            str(len(media)),
            joined(media),
            str(len(containers)),
            joined(widths),
            joined(overflows),
        )))

    rendered = "\n".join(rows) + "\n"
    if len(sys.argv) == 2 and sys.argv[1] == "--write":
        OUTPUT.write_text(rendered)
        print(f"Inventário atualizado: {OUTPUT.relative_to(ROOT)}")
    elif len(sys.argv) == 1:
        sys.stdout.write(rendered)
    else:
        raise SystemExit("Uso: scripts/audit-aculta420-responsive-inventory.py [--write]")
    print(
        f"Fontes inventariadas: {sum(counts.values())} "
        f"({counts['estilo']} estilos, {counts['javascript']} JS, {counts['template']} Twig/HTML)",
        file=sys.stderr,
    )


if __name__ == "__main__":
    main()
