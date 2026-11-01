#!/usr/bin/env python3
"""Run a command, retain full output locally and print a bounded summary.

Usage: python3 scripts/agent-output.py --lines 60 -- php -l path/to/file.php
"""
import argparse
import collections
import os
from pathlib import Path
import subprocess
import sys
import tempfile


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--lines", type=int, default=60, help="Maximum output lines (default 60)")
    parser.add_argument("--log-dir", default=None, help="Directory for full logs")
    parser.add_argument("command", nargs=argparse.REMAINDER)
    args = parser.parse_args()
    command = args.command[1:] if args.command[:1] == ["--"] else args.command
    if not command or args.lines < 1 or args.lines > 1000:
        parser.error("provide a command and --lines between 1 and 1000")

    log_dir = Path(args.log_dir or tempfile.gettempdir()) / "aculta-agent-logs"
    log_dir.mkdir(mode=0o700, parents=True, exist_ok=True)
    fd, path = tempfile.mkstemp(prefix="command-", suffix=".log", dir=log_dir)
    count = 0
    tail = collections.deque(maxlen=args.lines)
    try:
        with os.fdopen(fd, "w", encoding="utf-8", errors="replace") as log:
            log.flush()
            os.chmod(path, 0o600)
            with subprocess.Popen(command, stdout=subprocess.PIPE, stderr=subprocess.STDOUT) as proc:
                assert proc.stdout is not None
                for raw in proc.stdout:
                    line = raw.decode("utf-8", errors="replace")
                    log.write(line)
                    count += 1
                    tail.append(line)
                code = proc.wait()
    except OSError as exc:
        print(f"Could not run command: {exc}", file=sys.stderr)
        return 127
    print(f"Exit: {code}; output lines: {count}; showing last {min(count, args.lines)}")
    print(f"Full log (local, may contain secrets): {path}")
    sys.stdout.writelines(tail)
    return code


if __name__ == "__main__":
    sys.exit(main())
