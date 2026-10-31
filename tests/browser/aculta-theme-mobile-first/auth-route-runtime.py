#!/usr/bin/env python3
"""Read-only Firefox check of the real anonymous login page with candidate CSS."""

import base64
import json
import os
import selectors
import socket
import socketserver
import sys
import threading
import time
from pathlib import Path

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
LOCAL_RUNTIME = os.environ.get("AUTH_ROUTE_LOCAL_RUNTIME") == "1"
AUTH_HOST = "conta.aculta.toca.net.br"
BASE = os.environ.get(
    "AUTH_ROUTE_BASE_URL",
    f"https://{AUTH_HOST}" if LOCAL_RUNTIME else "http://default",
).rstrip("/")
ROUTE = os.environ.get("AUTH_ROUTE_PATH", "/entrar")
if not ROUTE.startswith("/") or "?" in ROUTE or "#" in ROUTE:
    raise SystemExit("AUTH_ROUTE_PATH deve ser um path absoluto simples.")
EXPECTED_PATH_CLASS = os.environ.get(
    "AUTH_ROUTE_EXPECTED_PATH_CLASS",
    "path-" + ROUTE.strip("/").replace("/", "-"),
)
WIDTHS = [int(width) for width in os.environ.get(
    "AUTH_ROUTE_WIDTHS", "320,360,390,575,576,599,600,768,1024,1280,1440",
).split(",")]
DISABLE_PAGE_JS = os.environ.get("AUTH_ROUTE_DISABLE_JS") == "1"
CSS = "\n".join((ROOT / path).read_text() for path in (
    "web/themes/custom/aculta420/css/base.css",
    "web/themes/custom/aculta420/css/components/auth.css",
))
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "status": "pass" if passed else "fail", "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, driver, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.2)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


class LocalRuntimeProxyHandler(socketserver.StreamRequestHandler):
    """Allow only the canonical Account host and route it to local Apache."""

    def handle(self):
        request = self.rfile.readline(4096).decode("ascii", "replace").strip()
        parts = request.split(" ", 2)
        while self.rfile.readline(8192).strip():
            pass
        authority = parts[1].lower() if len(parts) >= 2 else ""
        if parts[0] != "CONNECT":
            self.wfile.write(b"HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n")
            return
        if authority == f"{AUTH_HOST}:443":
            upstream_address = ("127.0.0.1", 443)
        elif (os.environ.get("AUTH_ROUTE_ALLOW_CLOUDFLARE") == "1"
              and authority == "challenges.cloudflare.com:443"):
            upstream_address = ("challenges.cloudflare.com", 443)
        else:
            self.wfile.write(b"HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n")
            return
        try:
            upstream = socket.create_connection(upstream_address, timeout=5)
        except OSError:
            self.wfile.write(b"HTTP/1.1 502 Bad Gateway\r\nConnection: close\r\n\r\n")
            return
        self.wfile.write(b"HTTP/1.1 200 Connection Established\r\n\r\n")
        self.wfile.flush()
        self.connection.setblocking(False)
        upstream.setblocking(False)
        selector = selectors.DefaultSelector()
        selector.register(self.connection, selectors.EVENT_READ, upstream)
        selector.register(upstream, selectors.EVENT_READ, self.connection)
        try:
            while True:
                for key, _ in selector.select(timeout=10):
                    data = key.fileobj.recv(65536)
                    if not data:
                        return
                    key.data.sendall(data)
        except (ConnectionError, OSError):
            return
        finally:
            selector.close()
            upstream.close()


class LocalRuntimeProxy(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

measure = """const challenge=document.querySelector('.captcha-type-challenge--turnstile .cf-turnstile');
const captchaWrapper=document.querySelector('.captcha');
const frame=document.querySelector('[data-portal-ui~="challenge-frame"]');
const rect=(element)=>{if(!element)return null;const r=element.getBoundingClientRect();return {left:r.left,right:r.right,width:r.width,scrollWidth:element.scrollWidth,clientWidth:element.clientWidth};};
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,bodyWidth:document.body.scrollWidth,clientWidth:document.documentElement.clientWidth,
rootOverflowX:getComputedStyle(document.documentElement).overflowX,scrollX:window.scrollX,scrollY:window.scrollY,
bodyClass:document.body.className,
headingPadding:getComputedStyle(document.querySelector('.aculta-page-heading')).padding,
contentPadding:getComputedStyle(document.querySelector('.aculta-content')).padding,
headingRect:(()=>{const e=document.querySelector('.aculta-page-heading');if(!e)return null;const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom}})(),
contentRect:(()=>{const e=document.querySelector('.aculta-content');if(!e)return null;const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,width:r.width}})(),
formRect:(()=>{const e=document.querySelector('.aculta-main form');if(!e)return null;const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,width:r.width}})(),
challengeWidth:challenge?.getBoundingClientRect().width||0,challengeScrollWidth:challenge?.scrollWidth||0,
challengeClientWidth:challenge?.clientWidth||0,
challengeRect:rect(challenge),challengeFrameRect:rect(frame),hasCaptchaWrapper:!!captchaWrapper,
hasHeading:!!document.querySelector('.aculta-page-heading h1'),
hasAuthForm:!!document.querySelector('.aculta-main form'),
loggedIn:document.body.classList.contains('user-logged-in'),
drupalPresent:typeof window.Drupal==='object'};"""
proxy = None
if LOCAL_RUNTIME:
    from urllib.parse import urlparse
    parsed = urlparse(BASE)
    if parsed.scheme != "https" or parsed.hostname != AUTH_HOST:
        raise SystemExit(f"AUTH_ROUTE_LOCAL_RUNTIME=1 exige https://{AUTH_HOST}.")
    proxy = LocalRuntimeProxy(("127.0.0.1", 0), LocalRuntimeProxyHandler)
    threading.Thread(target=proxy.serve_forever, daemon=True).start()
    proxy_port = proxy.server_address[1]
    driver = Driver(prefs={
        "javascript.enabled": not DISABLE_PAGE_JS,
        "network.proxy.type": 1,
        "network.proxy.http": "127.0.0.1",
        "network.proxy.http_port": proxy_port,
        "network.proxy.ssl": "127.0.0.1",
        "network.proxy.ssl_port": proxy_port,
        "network.proxy.no_proxies_on": "",
        "network.captive-portal-service.enabled": False,
        "network.dns.disablePrefetch": True,
    }, bidi=True)
else:
    driver = Driver(prefs={"javascript.enabled": not DISABLE_PAGE_JS}, bidi=True)
viewport = Viewport(driver)
try:
    driver.get(f"{BASE}{ROUTE}")
    initial = driver.js(measure)
    route_ok = EXPECTED_PATH_CLASS in initial["bodyClass"].split()
    challenge_rendered = initial["challengeWidth"] > 0 or DISABLE_PAGE_JS
    page_js_disabled_confirmed = not initial["drupalPresent"] if DISABLE_PAGE_JS else True
    record(f"rota real anônima {ROUTE}", route_ok and initial["hasHeading"] and initial["hasAuthForm"]
           and initial["hasCaptchaWrapper"] and challenge_rendered and page_js_disabled_confirmed and not initial["loggedIn"],
           {**{key: initial[key] for key in ("bodyClass", "hasHeading", "hasAuthForm", "hasCaptchaWrapper", "challengeWidth", "loggedIn", "drupalPresent")},
            "pageJavaScriptDisabled": DISABLE_PAGE_JS})
    if os.environ.get("AUTH_ADD_MARKER_CANDIDATE") == "1":
        driver.js("""document.querySelectorAll('.captcha').forEach((frame) => {
          const tokens=(frame.getAttribute('data-portal-ui')||'').split(/\\s+/).filter(Boolean);
          if (!tokens.includes('challenge-frame')) tokens.push('challenge-frame');
          frame.setAttribute('data-portal-ui', tokens.join(' '));
        });return true;""")
    driver.js("""const style=document.createElement('style');style.id='auth-candidate';
      style.textContent=arguments[0];document.head.append(style);return true;""", [CSS])

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        driver.js("document.activeElement?.blur();window.scrollTo({left:8,top:0,behavior:'instant'});document.documentElement.scrollTop=0;document.body.scrollTop=0;return true")
        data = driver.js(measure)
        png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        path_name = ROUTE.strip("/").replace("/", "-") or "front"
        (OUT / f"candidate-{path_name}-{width}.png").write_bytes(png)
        expected_heading = "24px 20px 4px" if width < 576 else "32px 32px 4px"
        expected_content = "0px 20px 24px" if width < 576 else "0px 32px 32px"
        horizontal_scroll_blocked = data["documentWidth"] <= width or (
            data["rootOverflowX"] == "clip" and data["scrollX"] == 0
        )
        heading_form_gap = (data["formRect"]["top"] - data["headingRect"]["bottom"]
                            if data["headingRect"] and data["formRect"] else None)
        heading_and_form_do_not_overlap = heading_form_gap is not None and heading_form_gap >= -1
        challenge_frame_fits = (os.environ.get("AUTH_ADD_MARKER_CANDIDATE") != "1"
                                or (data["challengeFrameRect"] is not None
                                    and data["challengeFrameRect"]["left"] >= -1
                                    and data["challengeFrameRect"]["right"] <= width + 1))
        challenge_rendered = data["challengeWidth"] > 0 or DISABLE_PAGE_JS
        valid = (actual_width == width and EXPECTED_PATH_CLASS in data["bodyClass"].split()
                 and data["hasHeading"] and data["hasAuthForm"] and data["hasCaptchaWrapper"]
                 and challenge_rendered and not data["loggedIn"]
                 and horizontal_scroll_blocked and data["headingPadding"] == expected_heading
                 and data["contentPadding"] == expected_content
                 and heading_and_form_do_not_overlap
                 and (data["challengeWidth"] == 0 or data["challengeScrollWidth"] <= data["challengeWidth"] + 1)
                 and challenge_frame_fits)
        record(f"Runtime {ROUTE} {width}px: estilos candidatos e sem overflow", valid,
               {**{key: data[key] for key in ("documentWidth", "bodyWidth", "clientWidth", "rootOverflowX", "scrollX", "scrollY", "headingPadding", "contentPadding", "challengeWidth", "challengeScrollWidth", "challengeClientWidth")},
                "headingRect": data["headingRect"], "formRect": data["formRect"],
                "headingFormGap": heading_form_gap})
finally:
    viewport.close()
    driver.close()
    if proxy:
        proxy.shutdown()
        proxy.server_close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
