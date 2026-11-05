#!/usr/bin/env python3
"""Validate the ACULTA420 V2 search popup migrated to HTMX in visible Firefox.

Uses the development server hosts (*.aculta.toca.net.br). The script does not
authenticate and does not write to the Runtime: it opens the anonymous search
trigger from the shell, types a term in the preview field and checks the
returned fragment, the six widths in light/dark mode, real 200% zoom and the
no-JavaScript fallback.
"""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if subprocess.run(["which", "xdotool"], capture_output=True).returncode:
    raise SystemExit("xdotool é necessário para validar zoom real do Firefox.")

sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

HOST = os.environ.get("ACULTA_DEV_HOST", "aculta.toca.net.br")
BASE = f"https://{HOST}"
WIDTHS = (320, 390, 768, 959, 960, 1440)


def set_viewport(driver, width, height=900):
    node = r'''const ws=new WebSocket(process.argv[1]);ws.onopen=()=>ws.send(JSON.stringify({id:1,method:'browsingContext.getTree',params:{}}));ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id===1)ws.send(JSON.stringify({id:2,method:'browsingContext.setViewport',params:{context:m.result.contexts[0].context,viewport:{width:Number(process.argv[2]),height:Number(process.argv[3])}}}));else if(m.id===2){if(m.error){console.error(m.error.message);process.exitCode=1;}ws.close();}};'''
    subprocess.run(
        ["node", "-e", node, driver.websocket_url, str(width), str(height)],
        check=True,
    )


def open_search(driver):
    driver.get(f"{BASE}/")
    driver.js("""document.querySelector('a[data-portal-ui="search-utility"]').click(); return true;""")
    deadline = time.time() + 15
    while time.time() < deadline:
        state = driver.js("""return {dialog: !!document.querySelector('dialog[data-aculta-search-dialog][open]'),
          form: !!document.querySelector('dialog[data-aculta-search-dialog] form[data-portal-ui="search-popup"]')};""")
        if state["dialog"] and state["form"]:
            return state
        time.sleep(0.2)
    raise SystemExit("O popup de busca não carregou o formulário por HTMX.")


def open_login(driver):
    driver.get(f"{BASE}/")
    clicked = driver.js("""const a=[...document.querySelectorAll('.aculta-account-menu a[href]')]
      .find((item)=> /\\/entrar\\/janela/.test(item.getAttribute('href')));
      if (!a) { return false; } a.click(); return true;""")
    deadline = time.time() + 15
    while time.time() < deadline:
        state = driver.js("""return {dialog:!!document.querySelector('dialog[data-aculta-login-dialog][open]'),
          form:!!document.querySelector('#aculta-login-content form'),
          user:!!document.querySelector('#aculta-login-content input[name="name"]')};""")
        if state["dialog"] and state["form"] and state["user"]:
            return state
        time.sleep(0.2)
    raise SystemExit(f"O popup de login não carregou por HTMX (clicou: {clicked}).")


def inspect(driver):
    return driver.js("""return (()=>{const d=document.documentElement;
      const dialog=document.querySelector('dialog[data-aculta-search-dialog][open]');
      const form=dialog?.querySelector('form[data-portal-ui="search-popup"]');
      const input=form?.querySelector('input[type="search"]');
      const box=form?.querySelector('#aculta-search-preview');
      return {width:innerWidth,documentWidth:d.scrollWidth,theme:d.getAttribute('data-theme'),
        dialog:!!dialog,form:!!form,input:!!input,
        hxGet:input?.getAttribute('data-hx-get')||null,
        hxTrigger:input?.getAttribute('data-hx-trigger')||null,
        hxTarget:input?.getAttribute('data-hx-target')||null,
        hxSync:input?.getAttribute('data-hx-sync')||null,
        legacyScript:[...document.scripts].some(s=>(s.src||'').includes('search-preview')),
        previewText:(box?.textContent||'').trim().slice(0,120)};})();""")


def type_term(driver, term):
    driver.js("""const input=document.querySelector('dialog[data-aculta-search-dialog][open] input[type="search"]');
      input.focus(); input.value=arguments[0]; input.dispatchEvent(new Event('input',{bubbles:true})); return true;""", [term])
    deadline = time.time() + 15
    while time.time() < deadline:
        state = driver.js("""const box=document.querySelector('#aculta-search-preview');
          const entries=performance.getEntriesByType('resource').filter((e)=>e.name.includes('/busca/previa'));
          return {requests:entries.length,text:(box?.textContent||'').trim()};""")
        if state["requests"] > 0 and len(state["text"]) > 0:
            return state
        time.sleep(0.25)
    raise SystemExit("A prévia da busca não respondeu por HTMX.")


def zoom_in():
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()
    subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+plus"], check=True)


def reset_zoom():
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()
    if windows:
        subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
        subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+0"], check=True)


records = []
driver = Driver(bidi=True)
try:
    reset_zoom()
    open_search(driver)
    for mode in ("light", "dark"):
        driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
        for width in WIDTHS:
            set_viewport(driver, width)
            metrics = inspect(driver)
            metrics.update(mode=mode, passed=(
                metrics["dialog"] and metrics["form"] and metrics["input"]
                and metrics["hxGet"] == "/busca/previa"
                and metrics["hxTarget"] == "#aculta-search-preview"
                and metrics["hxSync"] == "this:replace"
                and "delay:300ms" in (metrics["hxTrigger"] or "")
                and not metrics["legacyScript"]
                and metrics["documentWidth"] <= width
            ))
            records.append(metrics)
            print(f"{'PASS' if metrics['passed'] else 'FAIL'} {mode} {width}px", flush=True)
            (OUT / f"{mode}-{width}.png").write_bytes(base64.b64decode(
                driver.req("GET", f"/session/{driver.session}/screenshot")
            ))

    set_viewport(driver, 390)
    preview = type_term(driver, "cannabis")
    preview["passed"] = preview["requests"] > 0 and len(preview["text"]) > 0
    print(f"{'PASS' if preview['passed'] else 'FAIL'} prévia por HTMX", preview, flush=True)
    (OUT / "preview.png").write_bytes(base64.b64decode(
        driver.req("GET", f"/session/{driver.session}/screenshot")
    ))

    keyboard = driver.js("""const input=document.querySelector('dialog[data-aculta-search-dialog][open] input[type="search"]');
      const s=getComputedStyle(input);
      return {focused:document.activeElement===input,outlineStyle:s.outlineStyle,outlineWidth:s.outlineWidth};""")
    keyboard["passed"] = keyboard["focused"]
    print(f"{'PASS' if keyboard['passed'] else 'FAIL'} foco no campo de busca", keyboard, flush=True)

    set_viewport(driver, 640)
    for _ in range(24):
        zoom = driver.js("return {width:innerWidth,dpr:devicePixelRatio,document:document.documentElement.scrollWidth}")
        if zoom["dpr"] >= 1.92:
            break
        zoom_in()
        time.sleep(0.12)
    zoom = driver.js("""return {width:innerWidth,dpr:devicePixelRatio,document:document.documentElement.scrollWidth,
      dialog:document.querySelector('dialog[data-aculta-search-dialog][open]')?.getBoundingClientRect().toJSON()};""")
    zoom["passed"] = zoom["dpr"] >= 1.92 and zoom["document"] <= zoom["width"] and bool(zoom["dialog"])
    print(f"{'PASS' if zoom['passed'] else 'FAIL'} zoom real 200%", zoom, flush=True)
    (OUT / "zoom-200.png").write_bytes(base64.b64decode(
        driver.req("GET", f"/session/{driver.session}/screenshot")
    ))

    reset_zoom()
    login_records = []
    open_login(driver)
    for mode in ("light", "dark"):
        driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [mode])
        for width in WIDTHS:
            set_viewport(driver, width)
            metrics = driver.js("""return (()=>{const d=document.documentElement;
              const dialog=document.querySelector('dialog[data-aculta-login-dialog][open]');
              const form=dialog?.querySelector('#aculta-login-content form');
              return {width:innerWidth,documentWidth:d.scrollWidth,mode:d.getAttribute('data-theme'),
                dialog:!!dialog,form:!!form,
                user:!!form?.querySelector('input[name="name"]'),
                pass:!!form?.querySelector('input[name="pass"]'),
                hxPost:form?.getAttribute('data-hx-post')??null};})();""")
            metrics.update(passed=(
                metrics["dialog"] and metrics["form"] and metrics["user"] and metrics["pass"]
                and metrics["hxPost"] == ""
                and metrics["documentWidth"] <= width
            ))
            login_records.append(metrics)
            print(f"{'PASS' if metrics['passed'] else 'FAIL'} login {mode} {width}px", flush=True)
            (OUT / f"login-{mode}-{width}.png").write_bytes(base64.b64decode(
                driver.req("GET", f"/session/{driver.session}/screenshot")
            ))
finally:
    reset_zoom()
    driver.close()

# No-JavaScript fallback: the search link is a normal link to /busca/popup.
driver_nojs = Driver(prefs={"javascript.enabled": False})
try:
    driver_nojs.get(f"{BASE}/")
    driver_nojs.js("""document.querySelector('a[data-portal-ui="search-utility"]').click(); return true;""")
    deadline = time.time() + 15
    fallback = {}
    while time.time() < deadline:
        fallback = driver_nojs.js("""return {url:location.href,
          form:!!document.querySelector('form[data-portal-ui="search-popup"]'),
          input:!!document.querySelector('form[data-portal-ui="search-popup"] input[type="search"]')};""")
        if fallback["form"] and fallback["input"]:
            break
        time.sleep(0.25)
    fallback["passed"] = "/busca/popup" in fallback["url"] and fallback["form"] and fallback["input"]
    print(f"{'PASS' if fallback['passed'] else 'FAIL'} fallback sem JavaScript", fallback, flush=True)
    (OUT / "no-js.png").write_bytes(base64.b64decode(
        driver_nojs.req("GET", f"/session/{driver_nojs.session}/screenshot")
    ))
finally:
    driver_nojs.close()

result = {
    "host": HOST,
    "matrix": records,
    "preview": preview,
    "keyboard": keyboard,
    "zoom": zoom,
    "login_matrix": login_records,
    "no_js": fallback,
}
(OUT / "results.json").write_text(json.dumps(result, ensure_ascii=False, indent=2))
if not all(item["passed"] for item in records + login_records) or not all(
    item["passed"] for item in (preview, keyboard, zoom, fallback)
):
    raise SystemExit("Falha na validação visual da busca HTMX.")
print("ACULTA HTMX F1 SEARCH: PASS", flush=True)
