#!/usr/bin/env python3
"""Read-only Firefox check of the authenticated Culturas dashboard route."""

import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[3]
RUNTIME_ROOT = Path(os.environ.get("ACULTA_ROOT", "/home/piradopirata/projetos/aculta")).resolve()
RUNTIME_URL = os.environ.get("CULTURAS_RUNTIME_URL", "https://eventos.aculta.toca.net.br/culturas")
LOGIN_URI = os.environ.get("STUDIO_LOGIN_URI", "https://conta.aculta.toca.net.br")
USER = os.environ.get("STUDIO_USER", "aculta_visual_admin")
OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT fora do Git.")
OUT = Path(OUT_VALUE).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if subprocess.check_output(["hostname"], text=True).strip() != "toca":
    raise SystemExit("Runtime visual somente no servidor de desenvolvimento toca.")
if str(RUNTIME_ROOT) != "/home/piradopirata/projetos/aculta":
    raise SystemExit("Use somente o checkout servido autorizado do servidor de desenvolvimento.")
runtime_parts = urlparse(RUNTIME_URL)
login_parts = urlparse(LOGIN_URI)
if (runtime_parts.scheme, runtime_parts.hostname) != ("https", "eventos.aculta.toca.net.br"):
    raise SystemExit("CULTURAS_RUNTIME_URL deve apontar somente ao purpose Eventos de teste.")
if (login_parts.scheme, login_parts.hostname) != ("https", "conta.aculta.toca.net.br"):
    raise SystemExit("STUDIO_LOGIN_URI deve apontar somente ao domínio Conta de teste.")

drush = ["php", str(RUNTIME_ROOT / "vendor/drush/drush/drush.php")]
status = subprocess.check_output(drush + ["status", "--fields=uri,root,db-name"], cwd=RUNTIME_ROOT, text=True)
if "aculta_runtime" not in status or str(RUNTIME_ROOT / "web") not in status:
    raise SystemExit("Drush não confirmou aculta_runtime no checkout do servidor.")

login = subprocess.check_output(
    drush + ["uli", "--no-browser", f"--name={USER}", f"--uri={LOGIN_URI}"],
    cwd=RUNTIME_ROOT,
    text=True,
).strip().splitlines()[-1]


def fidelity_css():
    theme = ROOT / "web/themes/custom/aculta420"
    tokens = (theme / "css/tokens.css").read_text()

    def rule(selector):
        start = tokens.find(selector)
        if start < 0:
            raise RuntimeError(f"Token Fidelity ausente: {selector}")
        opening = tokens.find("{", start)
        depth = 0
        for index in range(opening, len(tokens)):
            if tokens[index] == "{":
                depth += 1
            elif tokens[index] == "}":
                depth -= 1
                if depth == 0:
                    return tokens[start:index + 1]
        raise RuntimeError(f"Regra Fidelity sem fechamento: {selector}")

    start = tokens.index("/* OpenCulturas Next 3.1.5 tokens are scoped to Studio-owned visual roots. */")
    end = tokens.index('\n[data-bs-theme="dark"] {', start)
    fidelity_tokens = tokens[start:end] + "\n" + rule('[data-bs-theme="dark"] .studio,')
    fidelity_tokens += "\n@media (prefers-color-scheme: dark) {\n" + rule(':root:not([data-bs-theme="light"]) .studio,') + "\n}"
    css = (theme / "css/components/studio.css").read_text()
    fonts = theme / "assets/fonts/montserrat"
    for name in ("Montserrat-latin.woff2", "Montserrat-latin-ext.woff2"):
        encoded = base64.b64encode((fonts / name).read_bytes()).decode("ascii")
        css = css.replace(f"../../assets/fonts/montserrat/{name}", f"data:font/woff2;base64,{encoded}")
    return fidelity_tokens + "\n" + css


class Viewport:
    def __init__(self, webdriver):
        from websockets.sync.client import connect

        self.socket = connect(webdriver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {"context": self.context, "viewport": {"width": width, "height": 1000}})

    def close(self):
        self.socket.close()


WIDTHS = tuple(map(int, os.environ.get("CULTURAS_WIDTHS", "320,360,390,599,600,768,1024,1280,1440").split(",")))
MODES = tuple(os.environ.get("CULTURAS_MODES", "light,dark").split(","))
RESULTS = []
driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get(login)
    time.sleep(1)
    driver.get(RUNTIME_URL)
    for _ in range(100):
        if driver.js("return document.readyState === 'complete'"):
            break
        time.sleep(0.2)
    driver.js("const s=document.createElement('style');s.dataset.culturasCandidate='true';s.textContent=arguments[0];document.head.append(s);return true", [fidelity_css()])
    for mode in MODES:
        driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0])", [mode])
        for width in WIDTHS:
            viewport.set(width)
            time.sleep(0.15)
            data = driver.js("""return (()=>{const q=s=>document.querySelector(s),all=s=>Array.from(document.querySelectorAll(s));
const root=q('.aculta-events-organizer-dashboard'),main=q('.aculta-main'),actions=root?.querySelector(':scope > .culturas-guide + div');
const links=Array.from(actions?.querySelectorAll('a')||[]),first=links[0],second=links[1],table=root?.querySelector('table'),tableWrap=root?.querySelector(':scope > .table-responsive');
const filterForm=root?.querySelector('.organizer-dashboard-filters form'),filterInput=filterForm?.querySelector('input[name="busca"]'),filterSelect=filterForm?.querySelector('select[name="status"]');
const box=e=>e?{width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height,background:getComputedStyle(e).backgroundColor,color:getComputedStyle(e).color,border:getComputedStyle(e).borderColor,borderWidth:getComputedStyle(e).borderTopWidth,display:getComputedStyle(e).display}:null;
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,bodyClasses:Array.from(document.body.classList),dashboardCount:all('.aculta-events-organizer-dashboard').length,
rootChildren:Array.from(root?.children||[]).map(e=>({tag:e.tagName,className:String(e.className)})),actionsClass:actions?String(actions.className):null,
actionCount:links.length,actionChildren:Array.from(actions?.children||[]).map(e=>({tag:e.tagName,className:String(e.className)})),actionLinkClasses:links.map(e=>String(e.className)),firstAction:box(first),secondAction:box(second),tableWidth:tableWrap?.clientWidth||0,tableScrollWidth:tableWrap?.scrollWidth||0,
filterColumns:getComputedStyle(filterForm).gridTemplateColumns.split(' ').length,filterInput:box(filterInput),filterSelect:box(filterSelect),filterWidth:filterForm?.clientWidth||0,guideMinHeight:parseFloat(getComputedStyle(root?.querySelector('summary')).minBlockSize),
institutionCount:all('.aculta-institution-bar').length,domainHeaderCount:all('.aculta-header').length,mainWidth:main?.getBoundingClientRect().width||0,
font:getComputedStyle(root||main).fontFamily,focusables:all('a,button,input,select,summary').length};})();""")
            data["mode"] = mode
            RESULTS.append(data)
            print(json.dumps(data, ensure_ascii=False), flush=True)
            shot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"culturas-runtime-{mode}-{width}.png").write_bytes(base64.b64decode(shot))
        driver.js("document.querySelector('.organizer-dashboard-filters input')?.focus();return true")
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "culturas-keyboard", "actions": [
            {"type": "keyDown", "value": chr(0xE004)}, {"type": "keyUp", "value": chr(0xE004)}]}]})
        focus = driver.js("const e=document.activeElement,s=getComputedStyle(e);return {tag:e.tagName,outline:s.outlineStyle,width:s.outlineWidth,visible:e.matches(':focus-visible')}")
        keyboard_passed = focus["tag"] == "SELECT" and focus["outline"] == "solid" and focus["width"] == "3px" and focus["visible"]
        RESULTS.append({"type": "keyboard", "mode": mode, "passed": keyboard_passed, "focus": focus})
        print(json.dumps({"type": "keyboard", "mode": mode, "passed": keyboard_passed, "focus": focus}, ensure_ascii=False), flush=True)
        driver.req("DELETE", f"/session/{driver.session}/actions")
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2) + "\n")


def failed(item):
    if item.get("type") == "keyboard":
        return not item["passed"]
    return (
        item["documentWidth"] > item["width"]
        or item["dashboardCount"] != 1
        or item["institutionCount"] != 1
        or item["domainHeaderCount"] != 1
        or item["actionCount"] < 2
        or not item["firstAction"]
        or not item["secondAction"]
        or item["firstAction"]["height"] < 44
        or item["firstAction"]["background"] == "rgba(0, 0, 0, 0)"
        or item["secondAction"]["borderWidth"] == "0px"
        or item["filterInput"] is None
        or item["filterSelect"] is None
        or item["filterInput"]["height"] < 44
        or item["filterSelect"]["height"] < 44
        or item["filterInput"]["width"] > item["filterWidth"]
        or item["filterSelect"]["width"] > item["filterWidth"]
        or item["filterColumns"] != (1 if item["width"] < 768 else 3)
        or item["guideMinHeight"] < 44
        or (item["width"] < 600 and item["tableScrollWidth"] <= item["tableWidth"])
    )


bad = [item for item in RESULTS if failed(item)]
print(f"\n{len(RESULTS) - len(bad)}/{len(RESULTS)} route checks passed; evidence: {OUT}")
sys.exit(1 if bad else 0)
