#!/usr/bin/env python3
"""Check the global responsive navigation in visible Firefox ESR."""

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

BASE = os.environ.get("NAVIGATION_BASE", "https://aculta.toca.net.br").rstrip("/")
OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
WIDTHS = [320, 360, 390, 575, 576, 599, 600, 768, 1024, 1099, 1100, 1280, 1440]
NO_JS_WIDTHS = [320, 768, 1099, 1100, 1440]
checks = []
candidate = None
if os.environ.get("NAVIGATION_CSS_OVERRIDE") == "1":
    candidate = (ROOT / "web/themes/custom/aculta420/css/components/navigation.css").read_text()


def check(name, ok, detail=""):
    checks.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'} {name}" + (f" — {detail}" if detail else ""), flush=True)


class Viewport:
    def __init__(self, driver):
        self.driver = driver
        self.socket = connect(driver.websocket_url)
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
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 1000},
        })
        time.sleep(0.2)
        return int(self.driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


def apply_candidate(driver):
    if candidate is not None:
        driver.js("""const style=document.createElement('style');
          style.dataset.navigationCandidate='true';
          style.textContent=arguments[0];document.head.append(style);return true;""", [candidate])


def wait_for(driver, predicate, timeout=2):
    end = time.time() + timeout
    while time.time() < end:
        if predicate():
            return True
        time.sleep(0.05)
    return bool(predicate())


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    for width in WIDTHS:
        actual = viewport.set(width)
        driver.get(f"{BASE}/?aculta_navigation_viewport={width}")
        time.sleep(0.5)
        apply_candidate(driver)
        driver.js("""const navbar=document.querySelector('.aculta-navbar');
          const list=navbar?.querySelector('.navbar-nav');
          if(list && innerWidth < 1100 && !list.querySelector('[data-navigation-wrap-fixture]')){
            const item=document.createElement('li');item.className='nav-item';
            const link=document.createElement('a');link.className='nav-link';
            link.href='#navigation-wrap-fixture';
            link.dataset.navigationWrapFixture='true';
            link.textContent='Programa de formação e desenvolvimento comunitário antiproibicionista';
            item.append(link);list.append(item);
            const menu=navbar.querySelector('#aculta-primary-menu');
            const wasShown=menu?.classList.contains('show') ?? false;
            menu?.classList.add('show');
            link.dataset.measuredWhiteSpace=getComputedStyle(link).whiteSpace;
            link.dataset.measuredFits=String(link.scrollWidth <= link.clientWidth + 1);
            if(menu && !wasShown)menu.classList.remove('show');
          }
          return true;""")
        time.sleep(0.1)
        metrics = driver.js("""return (() => {
          const navbar=document.querySelector('.aculta-navbar');
          const toggle=navbar?.querySelector('.aculta-menu-toggle');
          const menu=navbar?.querySelector('#aculta-primary-menu');
          const link=menu?.querySelector('.navbar-nav .nav-link');
          const wrapFixture=navbar?.querySelector('[data-navigation-wrap-fixture]');
          const container=navbar?.querySelector(':scope > .container');
          return {
            width:innerWidth,
            hasNavbar:!!navbar,
            ready:navbar?.classList.contains('aculta-navigation-ready') ?? false,
            links:menu?.querySelectorAll('.navbar-nav .nav-link').length ?? 0,
            toggleDisplay:toggle ? getComputedStyle(toggle).display : null,
            menuDisplay:menu ? getComputedStyle(menu).display : null,
            expanded:toggle?.getAttribute('aria-expanded') ?? null,
            columnGap:container ? getComputedStyle(container).columnGap : null,
            linkMinHeight:link ? getComputedStyle(link).minHeight : null,
            linkDisplay:link ? getComputedStyle(link).display : null,
            wrapWhiteSpace:wrapFixture?.dataset.measuredWhiteSpace ?? (link ? getComputedStyle(link).whiteSpace : null),
            wrapFits:wrapFixture ? wrapFixture.dataset.measuredFits === 'true'
              : (link ? link.scrollWidth <= link.clientWidth + 1 : false),
            menuMaxHeight:menu ? getComputedStyle(menu).maxHeight : null,
            documentWidth:document.documentElement.scrollWidth,
            institutionBar:document.querySelectorAll('.aculta-institution-bar').length,
            domainHeader:document.querySelectorAll('.aculta-header').length
          };
        })();""")
        desktop = width >= 1100
        expected_gap = "16px" if desktop else ("12px" if width < 576 else "32px")
        expected = (
            metrics["hasNavbar"]
            and metrics["ready"]
            and metrics["links"] > 0
            and metrics["toggleDisplay"] == ("none" if desktop else "flex")
            and metrics["menuDisplay"] == ("flex" if desktop else "none")
            and metrics["expanded"] == "false"
            and metrics["columnGap"] == expected_gap
            and metrics["linkMinHeight"] == ("0px" if desktop else "44px")
            and metrics["linkDisplay"] == ("block" if desktop else "flex")
            and metrics["wrapWhiteSpace"] == ("nowrap" if desktop else "normal")
            and (desktop or metrics["wrapFits"])
            and ((metrics["menuMaxHeight"] == "none") if desktop
                 else (metrics["menuMaxHeight"] != "none"))
            and metrics["documentWidth"] <= width
            and metrics["institutionBar"] == 1
            and metrics["domainHeader"] == 1
        )
        check(f"{width}px: shell e geometria do menu", actual == width and expected,
              json.dumps(metrics, ensure_ascii=False))

        if not desktop and metrics["toggleDisplay"] != "none":
            driver.click(".aculta-menu-toggle")
            opened = wait_for(driver, lambda: driver.js("""const menu=document.querySelector('#aculta-primary-menu');
              const toggle=document.querySelector('.aculta-menu-toggle');
              return !!menu?.classList.contains('show') && toggle?.getAttribute('aria-expanded')==='true';"""))
            check(f"{width}px: botão abre menu", opened)

        if width in (320, 768, 1280):
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"navigation-{width}.png").write_bytes(base64.b64decode(screenshot))

    driver.close()
    driver = Driver(prefs={"javascript.enabled": False}, bidi=True)
    viewport.close()
    viewport = Viewport(driver)
    for width in NO_JS_WIDTHS:
        actual = viewport.set(width)
        driver.get(f"{BASE}/?aculta_navigation_nojs={width}")
        time.sleep(0.5)
        apply_candidate(driver)
        metrics = driver.js("""return (() => {
          const navbar=document.querySelector('.aculta-navbar');
          const toggle=navbar?.querySelector('.aculta-menu-toggle');
          const menu=navbar?.querySelector('#aculta-primary-menu');
          return {
            width:innerWidth,
            ready:navbar?.classList.contains('aculta-navigation-ready') ?? false,
            toggleDisplay:toggle ? getComputedStyle(toggle).display : null,
            menuDisplay:menu ? getComputedStyle(menu).display : null,
            links:menu?.querySelectorAll('.navbar-nav .nav-link').length ?? 0,
            documentWidth:document.documentElement.scrollWidth
          };
        })();""")
        expected = (not metrics["ready"] and metrics["toggleDisplay"] == "none"
                    and metrics["menuDisplay"] == "block" and metrics["links"] > 0
                    and metrics["documentWidth"] <= width)
        check(f"{width}px: navegação permanece visível sem JavaScript",
              actual == width and expected, json.dumps(metrics, ensure_ascii=False))
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "base": BASE,
    "mode": "candidate" if candidate is not None else "served baseline",
    "checks": checks,
}, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in checks if not result["ok"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
