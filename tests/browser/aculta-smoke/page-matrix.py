#!/usr/bin/env python3
"""Responsive, color-mode and optional axe matrix for arbitrary project URLs."""

import base64
import json
import os
from pathlib import Path
import sys
import time
from urllib.parse import urlparse, urlunparse

ROOT = Path(__file__).resolve().parents[3]
os.umask(0o077)
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402


class Viewport:
    """Set a viewport through WebDriver BiDi without depending on window managers."""

    def __init__(self, driver):
        from websockets.sync.client import connect

        self.socket = connect(driver.websocket_url)
        self.sequence = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.sequence += 1
        command_id = self.sequence
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width, height):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": height},
        })

    def close(self):
        self.socket.close()


def public_url(value):
    parsed = urlparse(value)
    return urlunparse((parsed.scheme, parsed.netloc, parsed.path, "", "", ""))


def valid_url(value):
    parsed = urlparse(value)
    return parsed.scheme in ("http", "https") and bool(parsed.hostname)


OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT em armazenamento persistente fora do Git.")
OUT = Path(OUT_VALUE).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.chmod(OUT, 0o700)
BROWSER = os.environ.get("BROWSER", "firefox").lower()
HEADLESS = os.environ.get("HEADLESS") == "1"
if not HEADLESS and (BROWSER != "firefox" or os.environ.get("DISPLAY") != ":0"):
    raise SystemExit("Modo visível requer Firefox ESR e DISPLAY=:0.")
URLS = sys.argv[1:]
if not URLS or any(not valid_url(url) for url in URLS):
    raise SystemExit("Uso: page-matrix.py URL [URL ...] (somente http/https).")
START_URL = os.environ.get("BROWSER_SESSION_START_URL", "")
if START_URL and not valid_url(START_URL):
    raise SystemExit("BROWSER_SESSION_START_URL deve ser uma URL http/https.")
WIDTHS = tuple(map(int, os.environ.get("MATRIX_WIDTHS", "320,360,390,599,600,768,1024,1280,1440").split(",")))
HEIGHT = int(os.environ.get("MATRIX_HEIGHT", "1000"))
MODES = tuple(os.environ.get("MATRIX_MODES", "light,dark").split(","))
if not WIDTHS or min(WIDTHS) < 1 or not MODES or any(mode not in ("light", "dark") for mode in MODES):
    raise SystemExit("MATRIX_WIDTHS deve conter larguras positivas e MATRIX_MODES aceita light,dark.")
AXE_ENABLED = os.environ.get("ACCESSIBILITY_SCAN") == "1"
AXE_STRICT = os.environ.get("ACCESSIBILITY_STRICT") == "1"
RESULTS = []
driver = Driver(bidi=True, headless=HEADLESS, browser=BROWSER)
viewport = Viewport(driver)
try:
    if START_URL:
        driver.get(START_URL)
        time.sleep(1)
    axe_source = None
    if AXE_ENABLED:
        axe_value = os.environ.get("AXE_SCRIPT")
        axe_path = Path(axe_value).expanduser().resolve() if axe_value else (
            ROOT / "web/themes/custom/aculta420/node_modules/axe-core/axe.min.js"
        )
        if not axe_path.is_file():
            raise SystemExit(
                "axe-core ausente; defina AXE_SCRIPT para axe.min.js ou instale "
                "a dependência existente do tema ACULTA420."
            )
        axe_source = axe_path.read_text()

    for url_index, url in enumerate(URLS, start=1):
        driver.get(url)
        for _ in range(60):
            if driver.js("return document.readyState === 'complete'"):
                break
            time.sleep(0.25)
        if AXE_ENABLED:
            driver.js("const s=document.createElement('script');s.textContent=arguments[0];document.head.append(s);return true", [axe_source])

        for mode in MODES:
            driver.js("document.documentElement.setAttribute('data-bs-theme',arguments[0])", [mode])
            for width in WIDTHS:
                viewport.set(width, HEIGHT)
                time.sleep(0.1)
                data = driver.js("""return {url:location.origin+location.pathname,title:document.title,readyState:document.readyState,
status:performance.getEntriesByType('navigation')[0]?.responseStatus||null,
width:innerWidth,documentWidth:document.documentElement.scrollWidth,
visibleRight:(()=>{let right=innerWidth;for(const el of document.querySelectorAll('body *')){if(el.closest('.offcanvas:not(.show)'))continue;const s=getComputedStyle(el),r=el.getBoundingClientRect();if(s.display==='none'||s.visibility==='hidden'||r.width===0||r.height===0)continue;if(r.right>right)right=r.right;}return Math.ceil(right)})(),
horizontalOverflow:(()=>{for(const el of document.querySelectorAll('body *')){if(el.closest('.offcanvas:not(.show)'))continue;const s=getComputedStyle(el),r=el.getBoundingClientRect();if(s.display==='none'||s.visibility==='hidden'||r.width===0||r.height===0)continue;if(r.right>innerWidth+1)return true;}return false})(),
mainCount:document.querySelectorAll('main,[role=main]').length,
navCount:document.querySelectorAll('nav,[role=navigation]').length,
focusableCount:document.querySelectorAll('a[href],button,input,select,textarea,summary,[tabindex]:not([tabindex="-1"])').length};""")
                data.update({"urlIndex": url_index, "mode": mode})
                RESULTS.append(data)
                print(f"page{url_index}/{mode}/{width}px status={data['status']} "
                      f"doc={data['documentWidth']} overflow={data['horizontalOverflow']}", flush=True)
                if width in (WIDTHS[0], WIDTHS[-1]):
                    shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    (OUT / f"page-{url_index:02d}-{mode}-{width}.png").write_bytes(base64.b64decode(shot))
                if AXE_ENABLED and width == WIDTHS[0]:
                    audit = driver.js("return axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa','best-practice']}}).then(r=>({violations:r.violations.map(v=>({id:v.id,impact:v.impact,help:v.help,nodes:v.nodes.map(n=>({target:n.target,html:n.html,summary:n.failureSummary}))})),incomplete:r.incomplete.map(v=>({id:v.id,impact:v.impact,help:v.help,nodes:v.nodes.map(n=>({target:n.target,html:n.html,summary:n.failureSummary}))}))}))")
                    audit.update({"type": "axe", "urlIndex": url_index, "url": public_url(url), "mode": mode, "width": width})
                    RESULTS.append(audit)
                    print(f"axe/page{url_index}/{mode}/{width}px violations={len(audit['violations'])} "
                          f"incomplete={len(audit['incomplete'])}", flush=True)
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2) + "\n")
gate_results = [item for item in RESULTS if item.get("type") != "axe"]
bad = [item for item in gate_results if item["readyState"] != "complete"
       or (item["status"] is not None and not 200 <= item["status"] < 400)
       or item["horizontalOverflow"]]
axe_violations = sum(len(item["violations"]) for item in RESULTS if item.get("type") == "axe")
print(f"{len(gate_results) - len(bad)}/{len(gate_results)} viewport checks passed; "
      f"axe={'%d violation(s)' % axe_violations if AXE_ENABLED else 'disabled'}; evidence: {OUT}")
sys.exit(1 if bad or (AXE_STRICT and axe_violations) else 0)
