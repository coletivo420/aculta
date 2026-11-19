#!/usr/bin/env python3
"""Smoke-test the public Commerce catalog after UI Patterns grid cutover."""

import base64
import datetime
import hashlib
import json
import os
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[4]
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("ACULTA_BASE")
OUT = Path(os.environ["SUITE_OUT"])
if not BASE:
    raise SystemExit("Defina ACULTA_BASE para o host de desenvolvimento da Loja.")
OUT.mkdir(parents=True, exist_ok=True)
CAPTURES = OUT / "captures"
CAPTURES.mkdir(parents=True, exist_ok=True)
EVIDENCE = ROOT / "tests/browser/capturas/ui-patterns-shop-catalog" / datetime.date.today().isoformat()
EVIDENCE.mkdir(parents=True, exist_ok=True)
results = []
driver = Driver(headless=True, bidi=True)
socket = connect(driver.websocket_url)
command_id = 0


def bidi(method, params=None):
    global command_id
    command_id += 1
    socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
    while True:
        message = json.loads(socket.recv())
        if message.get("id") == command_id:
            if "error" in message:
                raise RuntimeError(f"WebDriver BiDi {method}: {message['error']}")
            return message.get("result", {})

try:
    for mode in ("light", "dark"):
        for width in (320, 390, 768, 1024, 1440):
            context = bidi("browsingContext.getTree")["contexts"][0]["context"]
            bidi("browsingContext.setViewport", {"context": context, "viewport": {"width": width, "height": 1000}})
            driver.get(f"{BASE.rstrip('/')}/produtos")
            deadline = time.monotonic() + 30
            ready = False
            while time.monotonic() < deadline:
                ready = driver.js('return document.readyState === "complete" && Boolean(document.querySelector("main .layout-grid > article.product-card .product-card__content"))')
                if ready:
                    break
                time.sleep(0.1)
            if not ready:
                raise RuntimeError(f"Página do catálogo não estabilizou em {width}px/{mode}.")
            driver.js('document.documentElement.setAttribute("data-theme", arguments[0]); return true', [mode])
            metrics = driver.js(r'''
              const main=document.querySelector('main');
              const grid=main?.querySelector('.layout-grid');
              const product=grid?.querySelector(':scope > article.product-card');
              const title=product?.querySelector('.product-card__content a[href]');
              const form=product?.querySelector('#commerce-product-add-to-cart-form');
              const price=product?.innerText||'';
              const bg=(el)=>{while(el){const c=getComputedStyle(el).backgroundColor;if(c&&c!=='transparent'&&c!=='rgba(0, 0, 0, 0)')return c;el=el.parentElement}return 'transparent'};
              const rgb=value=>(value.match(/[\d.]+/g)||[]).slice(0,3).map(Number);
              const lum=value=>{const c=rgb(value).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});return .2126*c[0]+.7152*c[1]+.0722*c[2]};
              const contrast=(fg,bgValue)=>{const a=lum(fg),b=lum(bgValue);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
              const titleBg=title?bg(title):'transparent';
              return {
                status: location.pathname === '/produtos' ? 200 : 0,
                width: innerWidth,
                mode: document.documentElement.getAttribute('data-theme'),
                h1: document.querySelectorAll('main h1').length,
                main: document.querySelectorAll('main').length,
                grid: Boolean(grid) && getComputedStyle(grid).display === 'grid',
                product_card: Boolean(product?.querySelector('.product-card__content')),
                products: grid?.querySelectorAll(':scope > article').length||0,
                overflow: document.documentElement.scrollWidth-document.documentElement.clientWidth,
                title_link: Boolean(title),
                add_to_cart_form: Boolean(form),
                submit_control: Boolean(form?.querySelector('[type="submit"]')),
                product_text: price.trim(),
                title_background: titleBg,
                title_color: title?getComputedStyle(title).color:null,
                title_contrast: title?contrast(getComputedStyle(title).color,titleBg):0
              };
            ''')
            if metrics["h1"] != 1 or metrics["main"] != 1 or not metrics["grid"] or not metrics["product_card"] or metrics["products"] != 1:
                raise AssertionError(f"Estrutura do catálogo inválida: {metrics}")
            if metrics["overflow"] > 0:
                raise AssertionError(f"Overflow horizontal: {metrics}")
            if metrics["width"] != width:
                raise AssertionError(f"Viewport aplicado difere do solicitado: {metrics}")
            if not metrics["title_link"] or not metrics["add_to_cart_form"] or not metrics["submit_control"]:
                raise AssertionError(f"Contrato Commerce ausente: {metrics}")
            if metrics["title_contrast"] < 4.5:
                raise AssertionError(f"Contraste do título abaixo de 4.5:1: {metrics}")

            image = driver.req("GET", f"/session/{driver.session}/screenshot")
            png = base64.b64decode(image)
            filename = f"{mode}-{width}.png"
            (CAPTURES / filename).write_bytes(png)
            (EVIDENCE / filename).write_bytes(png)
            metrics["screenshot"] = filename
            metrics["sha256"] = hashlib.sha256(png).hexdigest()
            results.append(metrics)

    # Keyboard navigation must reach a product link with visible focus.
    reached = False
    for _ in range(30):
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{"type": "key", "id": "kbd", "actions": [{"type": "keyDown", "value": "\uE004"}, {"type": "keyUp", "value": "\uE004"}]}]})
        reached = driver.js('''
          const el=document.activeElement;
          return Boolean(el && document.querySelector('.layout-grid')?.contains(el) && el.matches(':focus-visible'));
        ''')
        if reached:
            break
    if not reached:
        raise AssertionError("Tab não alcançou um link da grade com :focus-visible.")
    OUT.joinpath("summary.json").write_text(json.dumps({"route": "/produtos", "keyboard_product_focus": reached, "scenarios": results}, ensure_ascii=False, indent=2) + "\n")
    print(f"PASS — Firefox headless, {len(results)}/10 cenários; foco de teclado no produto PASS")
finally:
    driver.close()
