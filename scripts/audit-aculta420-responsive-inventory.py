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
LIBRARIES_SUFFIX = ".libraries.yml"
SDC_MANIFEST_SUFFIX = ".component.yml"
MEDIA = re.compile(r"@media\s+([^{}]+)\{", re.I)
CONTAINER = re.compile(r"@container\s+([^{}]+)\{", re.I)
JS_MEDIA = re.compile(r"matchMedia\s*\(\s*(['\"])(.*?)\1", re.I)
JS_VIEWPORT = re.compile(
    r"\b(?:innerWidth|outerWidth|clientWidth|scrollWidth|visualViewport|ResizeObserver|orientationchange)\b",
    re.I,
)
JS_SCROLL = re.compile(r"\b(?:scrollWidth|scrollHeight|scrollTop|scrollLeft|scrollTo|scrollIntoView)\b", re.I)
LIBRARY_ASSET = re.compile(
    r"^\s*['\"]?((?:https?://[^'\"\s]+)|(?:[^'\"\s]+\.(?:css|js)(?:\?[^'\"\s]*)?))['\"]?\s*:",
    re.M | re.I,
)
WIDTH = re.compile(
    r"(?<![\w-])(?:(?:min|max)-)?(?:width|height|inline-size|block-size)\s*:\s*[^;{}]+",
    re.I,
)
OVERFLOW = re.compile(r"(?<![\w-])overflow(?:-[xy])?\s*:\s*[^;{}]+", re.I)
HEADER = (
    "tipo\tcaminho\tresponsive_query_count\tresponsive_queries"
    "\tcontainer_query_count\tdimension_or_viewport_signals"
    "\toverflow_or_scroll_signals\tlibrary_asset_references"
)


def joined(values):
    cleaned = [re.sub(r"\s+", " ", value.strip()) for value in values]
    return " | ".join(cleaned) if cleaned else "—"


def main():
    rows = [HEADER]
    asset_reference_count = 0
    counts = {
        "estilo": 0,
        "javascript": 0,
        "template": 0,
        "biblioteca": 0,
        "sdc_manifest": 0,
    }
    for path in sorted(THEME.rglob("*")):
        if not path.is_file() or {"node_modules", "vendor"} & set(path.parts):
            continue
        if path.name.endswith(LIBRARIES_SUFFIX):
            kind = "biblioteca"
        elif path.name.endswith(SDC_MANIFEST_SUFFIX):
            kind = "sdc_manifest"
        else:
            kind = SOURCE_SUFFIXES.get(path.suffix.lower())
        if kind is None:
            continue
        counts[kind] += 1
        relative = path.relative_to(ROOT).as_posix()
        source = path.read_text(errors="replace")
        if kind in {"biblioteca", "sdc_manifest"}:
            assets = list(dict.fromkeys(LIBRARY_ASSET.findall(source)))
            asset_reference_count += len(assets)
            asset_root = THEME if kind == "biblioteca" else path.parent
            missing_assets = [
                asset for asset in assets
                if not asset.startswith(("http://", "https://"))
                and not (asset_root / asset).is_file()
            ]
            if missing_assets:
                raise SystemExit(
                    f"Assets locais ausentes em {relative}: {', '.join(missing_assets)}"
                )
            rows.append("\t".join((kind, relative, "—", "—", "—", "—", "—", joined(assets))))
            continue
        if kind != "estilo":
            if kind == "javascript":
                media_queries = [match.group(2) for match in JS_MEDIA.finditer(source)]
                viewport_signals = [match.group(0) for match in JS_VIEWPORT.finditer(source)]
                scroll_signals = [match.group(0) for match in JS_SCROLL.finditer(source)]
                rows.append("\t".join((
                    kind,
                    relative,
                    str(len(media_queries)),
                    joined(media_queries),
                    "0",
                    joined(list(dict.fromkeys(viewport_signals))),
                    joined(list(dict.fromkeys(scroll_signals))),
                    "—",
                )))
                continue
            rows.append(f"{kind}\t{relative}\t—\t—\t—\t—\t—\t—")
            continue
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
            "—",
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
        f"({counts['estilo']} estilos, {counts['javascript']} JS, "
        f"{counts['template']} Twig/HTML, {counts['biblioteca']} libraries, "
        f"{counts['sdc_manifest']} SDC manifests, "
        f"{asset_reference_count} CSS/JS asset references validated)",
        file=sys.stderr,
    )


if __name__ == "__main__":
    main()
