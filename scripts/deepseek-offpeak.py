#!/usr/bin/env python3
"""Fail-closed DeepSeek off-peak gate. No third-party dependencies.

Subcommands: check [--at ISO_UTC], run -- COMMAND..., proxy [--port PORT].
Only the local proxy enforces policy on every new HTTP request. Route all
DeepSeek traffic through it; direct API credentials/URLs bypass the gate.
"""
import argparse
import datetime as dt
import http.client
import http.server
import os
import signal
import sys
import urllib.parse

UTC = dt.timezone.utc
UPSTREAM = "api.deepseek.com"
PEAK = ((1, 4), (6, 10))  # UTC weekdays, Chinese public holidays excluded.
HOLIDAYS = set()  # Optional YYYY-MM-DD via DEEPSEEK_CN_HOLIDAYS, curated locally.


def offpeak(now, holidays=None):
    now = now.astimezone(UTC)
    holiday_set = HOLIDAYS if holidays is None else holidays
    if now.date().isoformat() in holiday_set or now.weekday() >= 5:
        return True
    return not any(start <= now.hour < end for start, end in PEAK)


def allowed(now=None):
    now = now or dt.datetime.now(UTC)
    # Missing holiday information is conservative: weekdays use peak restrictions.
    holidays = set(filter(None, os.environ.get("DEEPSEEK_CN_HOLIDAYS", "").split(",")))
    return offpeak(now, holidays)


class Handler(http.server.BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def _handle(self):
        if not allowed():
            self.send_response(429, "DeepSeek peak-hour gate")
            self.send_header("Content-Type", "application/json")
            self.send_header("Retry-After", "3600")
            self.send_header("Content-Length", "46")
            self.end_headers()
            self.wfile.write(b'{"error":"DeepSeek peak-hour gate: blocked"}\n')
            return
        parsed = urllib.parse.urlsplit(self.path)
        if parsed.scheme or parsed.netloc or not self.path.startswith("/"):
            self.send_error(400, "Only origin-form requests supported")
            return
        length = self.headers.get("Content-Length")
        if self.headers.get("Transfer-Encoding") or length is None and self.command in ("POST", "PUT", "PATCH"):
            self.send_error(411, "Content-Length required")
            return
        size = int(length or 0)
        if size < 0 or size > 32 * 1024 * 1024:
            self.send_error(413)
            return
        body = self.rfile.read(size) if size else None
        conn = http.client.HTTPSConnection(UPSTREAM, timeout=15)
        hop = {"host", "connection", "proxy-connection", "keep-alive", "transfer-encoding", "te", "trailer", "upgrade", "content-length"}
        headers = {k: v for k, v in self.headers.items() if k.lower() not in hop}
        headers["Host"] = UPSTREAM
        try:
            conn.request(self.command, self.path, body=body, headers=headers)
            resp = conn.getresponse()
            self.send_response_only(resp.status, resp.reason)
            for k, v in resp.getheaders():
                if k.lower() not in {"connection", "transfer-encoding", "content-length", "keep-alive", "proxy-connection"}:
                    self.send_header(k, v)
            self.send_header("Connection", "close")
            self.end_headers()
            self.close_connection = True
            while True:
                # Check between chunks to stop transmitting once a peak begins.
                if not allowed():
                    break
                chunk = resp.read(16384)
                if not chunk:
                    break
                self.wfile.write(chunk)
        except (OSError, http.client.HTTPException) as exc:
            # Do not echo request headers or secrets in errors.
            self.close_connection = True
            self.log_error("upstream request failed: %s", type(exc).__name__)
        finally:
            conn.close()

    do_GET = do_POST = do_PUT = do_PATCH = do_DELETE = _handle

    def log_message(self, fmt, *args):
        # Never log paths, prompts, authorization headers or response bodies.
        pass


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="action", required=True)
    check = sub.add_parser("check")
    check.add_argument("--at", help="ISO-8601 timestamp with timezone, for diagnostics")
    run = sub.add_parser("run")
    run.add_argument("command", nargs=argparse.REMAINDER)
    proxy = sub.add_parser("proxy")
    proxy.add_argument("--port", type=int, default=18763)
    args = parser.parse_args()
    if args.action == "check":
        now = dt.datetime.fromisoformat(args.at.replace("Z", "+00:00")) if args.at else dt.datetime.now(UTC)
        if now.utcoffset() is None:
            parser.error("--at requires timezone")
        ok = allowed(now)
        print(f"{now.astimezone(UTC).isoformat()} {'OFFPEAK' if ok else 'BLOCKED'}")
        return 0 if ok else 75
    if args.action == "run":
        command = args.command[1:] if args.command[:1] == ["--"] else args.command
        if not command:
            parser.error("run requires a command")
        if not allowed():
            print("DeepSeek peak hours: launch blocked", file=sys.stderr)
            return 75
        # Launch gate is NOT sufficient for long-running interactive sessions.
        os.execvp(command[0], command)
    if not (1024 <= args.port <= 65535):
        parser.error("port must be 1024..65535")
    server = http.server.ThreadingHTTPServer(("127.0.0.1", args.port), Handler)
    print(f"DeepSeek gated endpoint: http://127.0.0.1:{args.port} (localhost only)", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
