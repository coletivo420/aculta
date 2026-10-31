#!/usr/bin/env python3
"""Read-only Firefox coverage for the Magazine landing-page cover on Runtime."""

import base64
import json
import os
from pathlib import Path
import selectors
import shutil
import socket
import socketserver
import subprocess
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


def press_tab(driver):
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "magazine-keyboard",
            "actions": [
                {"type": "keyDown", "value": "\uE004"},
                {"type": "keyUp", "value": "\uE004"},
            ],
        }],
    })


def press_browser_key(key):
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True,
    ).splitlines()
    if not windows:
        raise RuntimeError("Janela visível do Firefox ESR não encontrada.")
    subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", key], check=True)


def main():
    if not shutil.which("xdotool"):
        raise RuntimeError("xdotool é necessário para verificar o zoom real do Firefox.")
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
  backgroundImage:light.backgroundImage,scrollWidth:document.documentElement.scrollWidth,
  aspectErrorPx:innerWidth<992?Math.abs(box.height-box.width*9/16):0};
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
                    and fallback.get("aspectErrorPx", float("inf")) <= 1
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
        set_viewport(driver, 320)
        driver.js("window.scrollTo(0, 0);document.activeElement?.blur();return true")
        keyboard_targets = {}
        for _ in range(100):
            press_tab(driver)
            focused = driver.js("""const el=document.activeElement;
if (!el?.matches('.aculta-revista-teaser__title a,.aculta-revista-teaser__cta')) return null;
const style=getComputedStyle(el), rect=el.getBoundingClientRect();
return {selector:el.matches('.aculta-revista-teaser__title a')?'title':'cta',
  text:el.innerText.trim(),focusVisible:el.matches(':focus-visible'),
  outlineStyle:style.outlineStyle,outlineWidth:style.outlineWidth,
  left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom,
  viewportWidth:innerWidth,viewportHeight:innerHeight};""")
            if focused:
                keyboard_targets.setdefault(focused["selector"], focused)
                if len(keyboard_targets) == 2:
                    break
        keyboard_passed = len(keyboard_targets) == 2 and all(
            target["focusVisible"] and target["outlineStyle"] != "none"
            and float(target["outlineWidth"].replace("px", "")) >= 2
            and target["left"] >= -1 and target["right"] <= target["viewportWidth"] + 1
            and target["top"] >= -1 and target["bottom"] <= target["viewportHeight"] + 1
            for target in keyboard_targets.values()
        )
        record("teclado alcança título e CTA da publicação com foco visível",
               keyboard_passed, keyboard_targets)
        original_theme = driver.js("return document.documentElement.getAttribute('data-bs-theme')")
        mode_results = {}
        for mode in ("light", "dark"):
            driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0]);return true", [mode])
            driver.js("window.scrollTo(0, 0);return true")
            time.sleep(0.15)
            mode_results[mode] = driver.js("""const root=document.documentElement;
const title=document.querySelector('.aculta-revista-teaser__title a');
const cta=document.querySelector('.aculta-revista-teaser__cta');
const card=title?.closest('.aculta-card');
const color=node=>node?getComputedStyle(node).color:'transparent';
const background=node=>{for(let current=node;current;current=current.parentElement){
  const value=getComputedStyle(current).backgroundColor;
  const rgba=value.match(/[\\d.]+/g)?.map(Number)||[];
  if(rgba.length<4||rgba[3]>0)return value;
}return getComputedStyle(document.body).backgroundColor;};
const luminance=value=>{const rgb=value.match(/[\\d.]+/g)?.slice(0,3).map(Number)||[];
  if(rgb.length!==3)return NaN;
  const channels=rgb.map(v=>{v/=255;return v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4;});
  return 0.2126*channels[0]+0.7152*channels[1]+0.0722*channels[2];};
const contrast=(foreground,backgroundColor)=>{const a=luminance(foreground),b=luminance(backgroundColor);
  return (Math.max(a,b)+0.05)/(Math.min(a,b)+0.05);};
const titleBackground=background(title),ctaBackground=background(cta);
return {mode:root.getAttribute('data-bs-theme'),pageSurface:getComputedStyle(root).getPropertyValue('--aculta-surface-page').trim(),
  cardBackground:background(card),titleColor:color(title),titleBackground,
  titleContrast:contrast(color(title),titleBackground),ctaColor:color(cta),ctaBackground,
  ctaContrast:contrast(color(cta),ctaBackground),overflow:root.scrollWidth>root.clientWidth};""")
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"magazine-cover-mode-{mode}.png").write_bytes(base64.b64decode(screenshot))
        light_surface = mode_results["light"]["pageSurface"]
        for mode, metrics in mode_results.items():
            mode_passed = (
                metrics["mode"] == mode and metrics["pageSurface"]
                and metrics["titleContrast"] >= 4.5 and metrics["ctaContrast"] >= 4.5
                and not metrics["overflow"]
                and (mode != "dark" or metrics["pageSurface"] != light_surface)
            )
            record(f"capa {mode}: tema aplicado, contraste AA em links e sem overflow",
                   mode_passed, metrics)
        if original_theme is None:
            driver.js("document.documentElement.removeAttribute('data-bs-theme')")
        else:
            driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0])", [original_theme])
        set_viewport(driver, 1280)
        press_browser_key("ctrl+0")
        time.sleep(0.15)
        base_width = driver.js("return innerWidth")
        for target in (2.0, 4.0):
            press_browser_key("ctrl+0")
            time.sleep(0.12)
            current_width = driver.js("return innerWidth")
            for _ in range(24):
                if base_width / max(1, current_width) >= target - 0.08:
                    break
                press_browser_key("ctrl+plus")
                time.sleep(0.12)
                current_width = driver.js("return innerWidth")
            zoom_metrics = driver.js("""const root=document.documentElement;
const view=document.querySelector('.page-view-revista-sessoes .view-display-id-page_revista');
const links=[...document.querySelectorAll('.aculta-revista-teaser__title a,.aculta-revista-teaser__cta')];
const rect=el=>{const r=el.getBoundingClientRect();return {left:r.left,right:r.right,width:r.width,height:r.height};};
return {width:innerWidth,documentWidth:root.clientWidth,scrollWidth:root.scrollWidth,
  bodyScrollWidth:document.body.scrollWidth,rows:view?.querySelectorAll('.views-row').length||0,
  titleCount:document.querySelectorAll('.aculta-revista-teaser__title a').length,
  ctaCount:document.querySelectorAll('.aculta-revista-teaser__cta').length,
  links:links.map(el=>({text:el.innerText.trim(),...rect(el)})),
  institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
  domainHeader:document.querySelectorAll('.aculta-header').length};""")
            ratio = base_width / max(1, zoom_metrics["width"])
            targets_fit = all(
                item["left"] >= -1 and item["right"] <= zoom_metrics["width"] + 1
                and item["height"] >= 44 for item in zoom_metrics["links"]
            )
            zoom_passed = (
                ratio >= target - 0.08 and zoom_metrics["documentWidth"] == zoom_metrics["width"]
                and zoom_metrics["scrollWidth"] <= zoom_metrics["width"]
                and zoom_metrics["bodyScrollWidth"] <= zoom_metrics["width"]
                and zoom_metrics["rows"] > 0
                and zoom_metrics["titleCount"] == zoom_metrics["rows"]
                and zoom_metrics["ctaCount"] == zoom_metrics["rows"]
                and zoom_metrics["institutionBar"] == 1 and zoom_metrics["domainHeader"] == 1
                and targets_fit
            )
            record(f"capa zoom {round(target * 100)}%: links 44px e sem overflow",
                   zoom_passed, {**zoom_metrics, "target": target, "ratio": ratio,
                                 "allTargetsFit": targets_fit})
            for kind, selector in (("title", ".aculta-revista-teaser__title a"),
                                   ("cta", ".aculta-revista-teaser__cta")):
                driver.js("const el=document.querySelector(arguments[0]);if(el)el.scrollIntoView({block:'center',behavior:'instant'});return true", [selector])
                time.sleep(0.25)
                visible_target = driver.js("""const el=document.querySelector(arguments[0]);
if (!el) return {exists:false};
const r=el.getBoundingClientRect();
return {exists:true,width:innerWidth,height:innerHeight,scrollY,
  left:r.left,right:r.right,top:r.top,bottom:r.bottom,
  horizontallyVisible:r.left>=-1&&r.right<=innerWidth+1,
  verticallyVisible:r.top>=-1&&r.bottom<=innerHeight+1,
  focusTargetHeight:r.height};""", [selector])
                visible_passed = (
                    visible_target["exists"] and visible_target["horizontallyVisible"]
                    and visible_target["verticallyVisible"] and visible_target["focusTargetHeight"] >= 44
                )
                record(f"capa zoom {round(target * 100)}%: {kind} pode ser trazido ao viewport",
                       visible_passed, visible_target)
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"magazine-cover-zoom-{round(target * 100)}-{kind}.png").write_bytes(base64.b64decode(shot))
        press_browser_key("ctrl+0")
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
