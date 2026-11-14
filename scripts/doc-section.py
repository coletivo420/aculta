#!/usr/bin/env python3
"""Print one Markdown section by heading, with a byte cap, for agents.

Use it instead of reading large roadmaps, changelogs or policies in full.
The section starts at the heading whose text matches TITLE (case-insensitive,
substring) and ends before the next heading of the same or higher level.

Usage:
  python3 scripts/doc-section.py docs/portal/ROADMAP.md "Fase 3" --max 4000
  python3 scripts/doc-section.py FILE --list          # headings only, with line numbers
"""
import argparse
import re
import signal
import sys
from pathlib import Path

HEADING = re.compile(r'^(#{1,6})\s+(.*\S)\s*$')


def parse(lines):
    out = []
    in_fence = False
    for number, line in enumerate(lines, 1):
        if line.lstrip().startswith('```'):
            in_fence = not in_fence
            continue
        if in_fence:
            continue
        match = HEADING.match(line)
        if match:
            out.append((number, len(match.group(1)), match.group(2)))
    return out


def main():
    signal.signal(signal.SIGPIPE, signal.SIG_DFL)
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('file')
    parser.add_argument('title', nargs='?', default=None)
    parser.add_argument('--max', type=int, default=4000, help='maximum bytes printed (default 4000)')
    parser.add_argument('--list', action='store_true', help='list headings with line numbers')
    args = parser.parse_args()
    path = Path(args.file)
    if not path.is_file():
        print(f'file not found: {args.file}', file=sys.stderr)
        return 2
    lines = path.read_text(encoding='utf-8', errors='replace').splitlines(keepends=True)
    headings = parse(lines)
    if args.list or args.title is None:
        for number, level, text in headings:
            print(f'{number}:{"  " * (level - 1)}{"#" * level} {text}')
        return 0
    wanted = args.title.lower()
    matches = [i for i, (_, _, text) in enumerate(headings) if wanted in text.lower()]
    if not matches:
        print(f'no heading contains "{args.title}" in {args.file}; use --list', file=sys.stderr)
        return 1
    if len(matches) > 1:
        print(f'{len(matches)} headings match; showing the first. Narrow the title:', file=sys.stderr)
        for i in matches[:10]:
            print(f'  line {headings[i][0]}: {headings[i][2]}', file=sys.stderr)
    index = matches[0]
    start_line, level, _ = headings[index]
    end_line = len(lines) + 1
    for number, other_level, _ in headings[index + 1:]:
        if other_level <= level:
            end_line = number
            break
    text = ''.join(lines[start_line - 1:end_line - 1])
    data = text.encode('utf-8')
    if len(data) > args.max:
        cut = data[:args.max].decode('utf-8', errors='ignore')
        text = cut + f'\n[TRUNCADO: {len(data)} bytes; use --max ou leia linhas {start_line + cut.count(chr(10))}+ com sed]\n'
    sys.stdout.write(text)
    return 0


if __name__ == '__main__':
    sys.exit(main())
