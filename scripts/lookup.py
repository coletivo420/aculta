#!/usr/bin/env python3
"""Find one route or data-portal-ui token in the reference docs, with its section.

Avoids reading the 100+ KB reference files in full. Prints each matching line with
its line number and the heading it belongs to, capped by --max.

Usage:
  python3 scripts/lookup.py rota /meu-perfil
  python3 scripts/lookup.py token aculta-lms-course-builder
  python3 scripts/lookup.py rota "/painel-administrativo/eventos" --max 5
"""
import argparse
import re
import signal
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCES = {
    'rota': 'docs/architecture/ROUTES-SITEMAP.md',
    'token': 'docs/portal/TOKENS-E-ATRIBUTOS.md',
}
HEADING = re.compile(r'^(#{1,6})\s+(.*\S)\s*$')


def main():
    signal.signal(signal.SIGPIPE, signal.SIG_DFL)
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('kind', choices=sorted(SOURCES))
    parser.add_argument('name', help='route path or token (case-insensitive substring)')
    parser.add_argument('--max', type=int, default=12, help='maximum matches printed (default 12)')
    args = parser.parse_args()
    source = ROOT / SOURCES[args.kind]
    if not source.is_file():
        print(f'source not found: {SOURCES[args.kind]}', file=sys.stderr)
        return 2
    needle = args.name.lower()
    heading = '(sem título)'
    hits = []
    total = 0
    for number, line in enumerate(source.read_text(encoding='utf-8', errors='replace').splitlines(), 1):
        match = HEADING.match(line)
        if match:
            heading = match.group(2)[:120]
            continue
        if needle in line.lower():
            total += 1
            if len(hits) < args.max:
                hits.append(f'{number} [{heading}] {line.strip()[:260]}')
    if not total:
        print(f'no match for "{args.name}" in {SOURCES[args.kind]}')
        return 1
    print(f'{total} match(es) in {SOURCES[args.kind]}' + (f'; showing {len(hits)}' if total > len(hits) else ''))
    for hit in hits:
        print(hit)
    return 0


if __name__ == '__main__':
    sys.exit(main())
