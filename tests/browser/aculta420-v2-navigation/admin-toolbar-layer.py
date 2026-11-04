#!/usr/bin/env python3
"""Exercise shell stacking against Gin's fixed administration sidebar CSS."""

import base64
import json
import os
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com Firefox ESR visível em DISPLAY=:0.")

environment = os.environ.get("NAVIGATION_DOMAIN_ENV", "test")
if environment not in ("test", "production"):
    raise SystemExit("NAVIGATION_DOMAIN_ENV deve ser test ou production.")
deploy_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
deploy = json.loads(deploy_path.read_text())
main = next(entry for entry in deploy["domain_transition"]["entries"] if entry["domain_id"] == "aculta_org")
origin = f"https://{main[f'{environment}_host']}"
admin_css = "\n".join((
    (ROOT / "web/themes/contrib/gin/dist/css/layout/navigation.css").read_text(),
    (ROOT / "web/core/modules/toolbar/css/toolbar.module.css").read_text(),
))

driver = Driver(bidi=True, browser="firefox")
socket = connect(driver.websocket_url)
sequence = 0
context = None
results = []
try:
    driver.get(origin + "/")
    for _ in range(60):
        if driver.js("return document.readyState === 'complete' && !!document.querySelector('.aculta-institution-bar')"):
            break
        time.sleep(0.1)
    if not driver.js("return !!document.querySelector('.aculta-institution-bar')"):
        raise SystemExit("Institution Bar não apareceu na página de teste.")

    driver.js("""const style=document.createElement('style');
      style.dataset.adminSidebarFixture='true';style.textContent=arguments[0];document.head.append(style);return true;""", [admin_css])

    def set_width(width):
        global sequence, context
        if context is None:
            sequence += 1
            request_id = sequence
            socket.send(json.dumps({"id": request_id, "method": "browsingContext.getTree", "params": {}}))
            while True:
                response = json.loads(socket.recv())
                if response.get("id") == request_id:
                    context = response["result"]["contexts"][0]["context"]
                    break
        sequence += 1
        request_id = sequence
        socket.send(json.dumps({
            "id": request_id,
            "method": "browsingContext.setViewport",
            "params": {"context": context, "viewport": {"width": width, "height": 900}},
        }))
        while True:
            response = json.loads(socket.recv())
            if response.get("id") == request_id:
                break
        time.sleep(0.3)

    for width in (320, 390, 768, 1024, 1280):
        set_width(width)
        for variant in ("gin", "core"):
            driver.js("""document.querySelectorAll('[data-admin-sidebar-fixture]').forEach(el=>el.remove());
              document.body.classList.toggle('gin--navigation', arguments[0]==='gin');
              const sidebar=document.createElement(arguments[0]==='gin'?'aside':'div');
              sidebar.dataset.adminSidebarFixture=arguments[0];
              if(arguments[0]==='gin'){sidebar.className='admin-toolbar';sidebar.style.position='fixed';sidebar.style.zIndex='501';sidebar.textContent='Administração';
                sidebar.setAttribute('aria-label','Administração');}
              else {sidebar.id='toolbar-administration';
                sidebar.className='toolbar toolbar-oriented toolbar-vertical toolbar-tray-open toolbar-fixed';
                sidebar.setAttribute('data-drupal-admin-styles','');sidebar.innerHTML='<div class="toolbar-bar" style="position:fixed;z-index:1250;height:2.4375rem">Ações administrativas</div><div class="toolbar-tray toolbar-tray-vertical is-active" style="position:fixed;z-index:1250"><div class="toolbar-lining">Menu</div></div>';}
              document.body.prepend(sidebar);return true;""", [variant])
            time.sleep(0.1)
            result = driver.js("""return (() => {
          const bar=document.querySelector('.aculta-institution-bar');
          const fixture=document.querySelector('[data-admin-sidebar-fixture]');
          const sidebar=fixture.dataset.adminSidebarFixture==='gin'?fixture:fixture.querySelector('.toolbar-tray-vertical');
          const toolbarBar=fixture.dataset.adminSidebarFixture==='core'?fixture.querySelector('.toolbar-bar'):null;
          const barRect=bar.getBoundingClientRect(), sideRect=sidebar.getBoundingClientRect();
          const barStyle=getComputedStyle(bar), sideStyle=getComputedStyle(sidebar);
          const barPoint={x:Math.max(0,Math.min(innerWidth-1,Math.round(barRect.left+4))),
            y:Math.max(0,Math.min(innerHeight-1,Math.round((barRect.top+barRect.bottom)/2)))};
          const sidePoint={x:Math.max(0,Math.min(innerWidth-1,Math.round(sideRect.left+4))),
            y:Math.max(0,Math.min(innerHeight-1,Math.round(sideRect.top+4)))};
          const barHit=document.elementFromPoint(barPoint.x,barPoint.y);
          const sideHit=document.elementFromPoint(sidePoint.x,sidePoint.y);
          return {variant:fixture.dataset.adminSidebarFixture,viewport:innerWidth,barTop:Math.round(barRect.top),barBottom:Math.round(barRect.bottom),
            barZIndex:barStyle.zIndex,sidebarTop:Math.round(sideRect.top),sidebarBottom:Math.round(sideRect.bottom),
            sidebarZIndex:sideStyle.zIndex,barPointHit:Boolean(barHit?.closest('.aculta-institution-bar')),
            sidebarPointHit:Boolean(sideHit?.closest('[data-admin-sidebar-fixture]')),
            toolbarBarTop:toolbarBar?Math.round(toolbarBar.getBoundingClientRect().top):null,
            toolbarBarBottom:toolbarBar?Math.round(toolbarBar.getBoundingClientRect().bottom):null,
            toolbarBarHit:toolbarBar?Boolean(document.elementFromPoint(4,Math.round(toolbarBar.getBoundingClientRect().top+2))?.closest('.toolbar-bar')):null,
            toolbarBarZIndex:toolbarBar?getComputedStyle(toolbarBar).zIndex:null,
            documentWidth:document.documentElement.scrollWidth};
        })()""")
            correct_layer_order = (
                int(result["toolbarBarZIndex"]) < int(result["barZIndex"])
                if variant == "core"
                else int(result["sidebarZIndex"]) < int(result["barZIndex"])
            )
            result["passed"] = (
                result["viewport"] == width
                and result["barZIndex"] == "1300"
                and correct_layer_order
                and abs(result["sidebarTop"] - (
                    result["toolbarBarBottom"] if variant == "core" else result["barBottom"]
                )) <= 2
                and result["barPointHit"] and result["sidebarPointHit"]
                and (result["variant"] != "core" or (
                    abs(result["toolbarBarTop"] - result["barBottom"]) <= 2
                    and result["toolbarBarHit"]
                ))
                and result["documentWidth"] <= width
            )
            results.append(result)
            print(("PASS" if result["passed"] else "FAIL") + " " + json.dumps(result, ensure_ascii=False), flush=True)
            if variant == "core" and width in (320, 1280):
                screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"admin-sidebar-layer-{width}.png").write_bytes(base64.b64decode(screenshot))
    (OUT / "results.json").write_text(json.dumps({
        "environment": environment,
        "authState": "anonymous with Gin and Core toolbar CSS fixtures",
        "scope": "layer geometry and pointer access; no authenticated toolbar runtime",
        "checks": results,
    }, ensure_ascii=False, indent=2) + "\n")
finally:
    socket.close()
    driver.close()

failed = [result for result in results if not result["passed"]]
print(f"{len(results) - len(failed)}/{len(results)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
