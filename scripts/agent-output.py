#!/usr/bin/env python3
"""Run a command, keep the full log locally and print a bounded summary for agents.

Shows: the exit code, the lines matching --match (first --max-matches, e.g. FAIL/ERROR
lines that gates print early), and the last --lines lines. The full log stays in a
private directory outside the repository.

Usage:
  python3 scripts/agent-output.py --lines 40 -- php scripts/validate-root-policy.php
  python3 scripts/agent-output.py --lines 20 --match 'FAIL|Error' -- vendor/bin/phpunit ...
"""
import argparse
import collections
import os
import re
from pathlib import Path
import subprocess
import sys
import tempfile

DEFAULT_MATCH = r'FAIL|ERROR|Error:|Fatal|Exception|PASS|OK \(|Exit code [1-9]'


def log_dir_for(explicit):
    base = explicit or os.environ.get('SUITE_OUT') or tempfile.gettempdir()
    path = Path(base) / 'aculta-agent-logs'
    path.mkdir(mode=0o700, parents=True, exist_ok=True)
    return path


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--lines', type=int, default=40, help='tail lines to show (1-1000, default 40)')
    parser.add_argument('--match', default=DEFAULT_MATCH, help='regex for lines to always show')
    parser.add_argument('--max-matches', type=int, default=30, help='cap for matched lines (default 30)')
    parser.add_argument('--log-dir', default=None, help='directory for full logs (default $SUITE_OUT or tmp)')
    parser.add_argument('command', nargs=argparse.REMAINDER)
    args = parser.parse_args()
    command = args.command[1:] if args.command[:1] == ['--'] else args.command
    if not command or not 1 <= args.lines <= 1000 or not 0 <= args.max_matches <= 200:
        parser.error('provide a command, --lines 1-1000 and --max-matches 0-200')
    try:
        pattern = re.compile(args.match)
    except re.error as exc:
        parser.error(f'invalid --match regex: {exc}')

    log_dir = log_dir_for(args.log_dir)
    fd, path = tempfile.mkstemp(prefix='command-', suffix='.log', dir=log_dir)
    count = 0
    tail = collections.deque(maxlen=args.lines)
    matches = []
    matched_numbers = []
    try:
        with os.fdopen(fd, 'w', encoding='utf-8', errors='replace') as log:
            os.chmod(path, 0o600)
            with subprocess.Popen(command, stdout=subprocess.PIPE, stderr=subprocess.STDOUT) as proc:
                assert proc.stdout is not None
                for raw in proc.stdout:
                    line = raw.decode('utf-8', errors='replace')
                    log.write(line)
                    count += 1
                    tail.append((count, line))
                    if len(matches) < args.max_matches and pattern.search(line):
                        matches.append((count, line.rstrip('\n')[:300]))
                code = proc.wait()
    except OSError as exc:
        print(f'Could not run command: {exc}', file=sys.stderr)
        return 127

    print(f'Exit: {code}; output lines: {count}; full log (local, may contain secrets): {path}')
    tail_numbers = {number for number, _ in tail}
    extra = [(n, t) for n, t in matches if n not in tail_numbers]
    if extra:
        print(f'--- matched lines outside the tail ({len(extra)} of {len(matches)} shown) ---')
        for number, text in extra:
            print(f'{number}: {text}')
        print('--- tail ---')
    sys.stdout.writelines(line for _, line in tail)
    return code


if __name__ == '__main__':
    sys.exit(main())
