#!/usr/bin/env python3
"""Read-only Firefox coverage for the Magazine landing-page cover on Runtime."""

import base64
import json
import os
from pathlib import Path
import selectors
import socket
import socketserver
import sys
import threading
import time
from urllib.parse import urlparse

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
URL = os.environ.get("MAGAZINE_COVER_URL", "https://revista.aculta.toca.net.br/")
HOST = "revista.aculta.toca.net.br"
CSS_CANDIDATE = ROOT / "web/themes/custom/aculta420/css/components/revista-listing.css"
WIDTHS = tuple(map(int, os.environ.get(
    "MAGAZINE_COVER_WIDTHS", "320,360,390,599,600,768,1024,1280,1440",
).split(",")))

parsed_url = urlparse(URL)
if parsed_url.scheme != "https" or parsed_url.hostname != HOST or parsed_url.path != "/":
    raise SystemExit(f"MAGAZINE_COVER_URL deve ser a raiz HTTPS de {HOST}.")
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com Firefox ESR visível e DISPLAY=:0.")


class LoopbackMagazineProxy(socketserver.StreamRequestHandler):
    """Map only the Magazine HTTPS host to the local development Apache."""

    def handle(self):
        request = self.rfile.readline(4096).decode("ascii", "replace").strip()
        parts = request.split(" ", 2)
        if len(parts) < 2:
            self.wfile.write(b"HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n")
            return
        method, authority = parts[:2]
        while self.rfile.readline(8192).strip():
            pass
        if method != "CONNECT" or authority.lower() != f"{HOST}:443":
            self.wfile.write(b"HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n")
            return

        try:
            upstream = socket.create_connection(("127.0.0.1", 443), timeout=5)
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
                events = selector.select(timeout=10)
                if not events:
                    continue
                for key, _ in events:
                    data = key.fileobj.recv(65536)
                    if not data:
                        return
                    key.data.sendall(data)
        except (ConnectionError, OSError):
            return
        finally:
            selector.close()
            upstream.close()


class ThreadedProxy(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def set_viewport(driver, width):
    with connect(driver.websocket_url) as websocket:
        websocket.send(json.dumps({
            "id": 1,
            "method": "browsingContext.getTree",
            "params": {},
        }))
        while True:
            tree = json.loads(websocket.recv())
            if tree.get("id") == 1:
                break
        context = tree["result"]["contexts"][0]["context"]
        websocket.send(json.dumps({
            "id": 2,
            "method": "browsingContext.setViewport",
            "params": {
                "context": context,
                "viewport": {"width": width, "height": 1000 if width >= 1000 else 844},
            },
        }))
        while True:
            result = json.loads(websocket.recv())
            if result.get("id") == 2:
                if "error" in result:
                    raise RuntimeError(result["error"])
                break
    time.sleep(0.2)


def main():
    proxy = ThreadedProxy(("127.0.0.1", 0), LoopbackMagazineProxy)
    threading.Thread(target=proxy.serve_forever, daemon=True).start()
    proxy_port = proxy.server_address[1]
    proxy_prefs = {
        "network.proxy.type": 1,
        "network.proxy.http": "127.0.0.1",
        "network.proxy.http_port": proxy_port,
        "network.proxy.ssl": "127.0.0.1",
        "network.proxy.ssl_port": proxy_port,
        "network.proxy.no_proxies_on": "",
        "network.captive-portal-service.enabled": False,
        "network.dns.disablePrefetch": True,
    }
    driver = None
    checks = []

    def record(name, passed, detail):
        checks.append({"name": name, "passed": bool(passed), "detail": detail})
        print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)

    try:
        driver = Driver(prefs=proxy_prefs, bidi=True)
        driver.get(URL)
        if os.environ.get("MAGAZINE_COVER_CSS_OVERRIDE") == "1":
            candidate_css = CSS_CANDIDATE.read_text(encoding="utf-8")
            driver.js("""const style=document.createElement('style');style.id='magazine-css-candidate';
              style.textContent=arguments[0];document.head.append(style);return true;""", [candidate_css])
        initial = driver.js("""
const view=document.querySelector('.page-view-revista-sessoes .view-display-id-page_revista');
return {url:location.href,statusClass:document.body.className,title:document.title,
view:!!view,hero:!!view?.querySelector('.aculta-hero h1'),
heroText:view?.querySelector('.aculta-hero h1')?.innerText.trim()||'',
rows:view?.querySelectorAll('.views-row').length||0,
teasers:view?.querySelectorAll('.aculta-revista-teaser').length||0,
institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
domainHeader:document.querySelectorAll('.aculta-header').length,
titleTargets:[...document.querySelectorAll('.aculta-revista-teaser__title a')].map(el=>{const r=el.getBoundingClientRect();return {width:r.width,height:r.height}}),
ctaTargets:[...document.querySelectorAll('.aculta-revista-teaser__cta')].map(el=>{const r=el.getBoundingClientRect();return {width:r.width,height:r.height}})};
""")
        record("Runtime anônimo renderiza a View e a chamada da capa", (
            initial["view"] and initial["hero"] and initial["rows"] > 0
            and initial["teasers"] == initial["rows"]
            and initial["institutionBar"] == 1 and initial["domainHeader"] == 1
        ), initial)

        for width in WIDTHS:
            set_viewport(driver, width)
            metrics = driver.js("""
const root=document.documentElement, view=document.querySelector('.page-view-revista-sessoes .view-display-id-page_revista');
const grid=view?.querySelector('.view-content');
const first=view?.querySelector('.views-row:first-child');
const card=first?.querySelector('.aculta-card');
const image=first?.querySelector('.card-img-top img');
const columns=getComputedStyle(grid||document.body).gridTemplateColumns.split(' ').filter(Boolean).length;
return {width:innerWidth,documentWidth:root.clientWidth,scrollWidth:root.scrollWidth,
hero:!!view?.querySelector('.aculta-hero h1'),rows:view?.querySelectorAll('.views-row').length||0,
columns,firstCard:!!card,firstCardGrid:getComputedStyle(card||document.body).display==='grid',
image:!!image,imageLoaded:!!image&&image.complete&&image.naturalWidth>0,
institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
domainHeader:document.querySelectorAll('.aculta-header').length,
titleTargets:[...document.querySelectorAll('.aculta-revista-teaser__title a')].map(el=>{const r=el.getBoundingClientRect();return {width:r.width,height:r.height}}),
ctaTargets:[...document.querySelectorAll('.aculta-revista-teaser__cta')].map(el=>{const r=el.getBoundingClientRect();return {width:r.width,height:r.height}})};
""")
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"magazine-cover-{width}.png").write_bytes(base64.b64decode(screenshot))
            expected_columns = 1 if width < 600 else 2 if width < 992 else 3
            passed = (
                metrics["width"] == width and metrics["scrollWidth"] <= metrics["documentWidth"]
                and metrics["hero"] and metrics["rows"] > 0 and metrics["firstCard"]
                and metrics["imageLoaded"] and metrics["columns"] == expected_columns
                and (width < 992 or metrics["firstCardGrid"])
                and metrics["institutionBar"] == 1 and metrics["domainHeader"] == 1
                and len(metrics["titleTargets"]) == metrics["rows"]
                and len(metrics["ctaTargets"]) == metrics["rows"]
                and all(target["height"] >= 44 for target in metrics["titleTargets"] + metrics["ctaTargets"])
            )
            record(f"capa da Revista em {width}px", passed, metrics)

            if os.environ.get("MAGAZINE_COVER_FALLBACK_CANDIDATE") == "1":
                fallback = driver.js("""
const view=document.querySelector('.page-view-revista-sessoes .view-display-id-page_revista');
const first=view?.querySelector('.views-row:first-child .aculta-card');
const original=first?.querySelector('.card-img-top');
if (!first || !original) return {available:false};
const placeholder=document.createElement('div');
placeholder.className='card-img-top aculta-revista-teaser__cover-fallback';
placeholder.setAttribute('aria-hidden','true');
original.replaceWith(placeholder);
window.__magazineOriginalCover=original;
window.__magazineFallbackCover=placeholder;
document.documentElement.setAttribute('data-bs-theme','light');
const light=getComputedStyle(placeholder), box=placeholder.getBoundingClientRect();
const lightMetrics={width:box.width,height:box.height,backgroundColor:light.backgroundColor,
  backgroundImage:light.backgroundImage,scrollWidth:document.documentElement.scrollWidth};
document.documentElement.setAttribute('data-bs-theme','dark');
const dark=getComputedStyle(placeholder), darkColor=dark.backgroundColor, darkImage=dark.backgroundImage;
const result={available:true,viewportWidth:innerWidth,...lightMetrics,darkBackgroundColor:darkColor,
  darkBackgroundImage:darkImage,scrollWidth:document.documentElement.scrollWidth,
  columns:getComputedStyle(view.querySelector('.view-content')).gridTemplateColumns.split(' ').filter(Boolean).length};
document.documentElement.setAttribute('data-bs-theme','light');
return result;
""")
                fallback_passed = bool(
                    fallback.get("available")
                    and fallback.get("viewportWidth") == width
                    and fallback.get("width") > 0 and fallback.get("height") > 0
                    and fallback.get("backgroundImage") != "none"
                    and fallback.get("darkBackgroundImage") != "none"
                    and fallback.get("scrollWidth") <= width
                    and fallback.get("columns") == expected_columns
                )
                record(f"fallback decorativo sem imagem em {width}px", fallback_passed, fallback)
                if width in (320, 1440):
                    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    (OUT / f"magazine-cover-fallback-{width}.png").write_bytes(base64.b64decode(screenshot))
                driver.js("""
if (window.__magazineFallbackCover?.isConnected && window.__magazineOriginalCover) {
  window.__magazineFallbackCover.replaceWith(window.__magazineOriginalCover);
}
window.__magazineFallbackCover=null;window.__magazineOriginalCover=null;
document.documentElement.setAttribute('data-bs-theme','light');return true;
""")
    finally:
        if driver:
            driver.close()
        proxy.shutdown()
        proxy.server_close()

    (OUT / "report.json").write_text(json.dumps(checks, ensure_ascii=False, indent=2) + "\n")
    failures = [check for check in checks if not check["passed"]]
    print(f"\n{len(checks) - len(failures)} PASS, {len(failures)} FAIL; evidências: {OUT}")
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
