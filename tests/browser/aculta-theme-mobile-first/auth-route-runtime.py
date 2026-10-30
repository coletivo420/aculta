#!/usr/bin/env python3
"""Read-only Firefox check of the real anonymous login page with candidate CSS."""

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

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
BASE = os.environ.get("AUTH_ROUTE_BASE_URL", "http://default").rstrip("/")
ROUTE = os.environ.get("AUTH_ROUTE_PATH", "/entrar")
if not ROUTE.startswith("/") or "?" in ROUTE or "#" in ROUTE:
    raise SystemExit("AUTH_ROUTE_PATH deve ser um path absoluto simples.")
EXPECTED_PATH_CLASS = "path-" + ROUTE.strip("/").replace("/", "-")
WIDTHS = [int(width) for width in os.environ.get(
    "AUTH_ROUTE_WIDTHS", "320,360,390,575,576,599,600,768,1024,1280,1440",
).split(",")]
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


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

measure = """const challenge=document.querySelector('.captcha-type-challenge--turnstile .cf-turnstile');
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,bodyWidth:document.body.scrollWidth,clientWidth:document.documentElement.clientWidth,
rootOverflowX:getComputedStyle(document.documentElement).overflowX,scrollX:window.scrollX,
bodyClass:document.body.className,
headingPadding:getComputedStyle(document.querySelector('.aculta-page-heading')).padding,
contentPadding:getComputedStyle(document.querySelector('.aculta-content')).padding,
challengeWidth:challenge?.getBoundingClientRect().width||0,challengeScrollWidth:challenge?.scrollWidth||0,
challengeClientWidth:challenge?.clientWidth||0,
hasHeading:!!document.querySelector('.aculta-page-heading h1'),
hasAuthForm:!!document.querySelector('.aculta-main form'),
loggedIn:document.body.classList.contains('user-logged-in')};"""
driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get(f"{BASE}{ROUTE}")
    initial = driver.js(measure)
    route_ok = EXPECTED_PATH_CLASS in initial["bodyClass"].split()
    record(f"rota real anônima {ROUTE}", route_ok and initial["hasHeading"] and initial["hasAuthForm"]
           and initial["challengeWidth"] > 0 and not initial["loggedIn"],
           {key: initial[key] for key in ("bodyClass", "hasHeading", "hasAuthForm", "challengeWidth", "loggedIn")})
    driver.js("""const style=document.createElement('style');style.id='auth-candidate';
      style.textContent=arguments[0];document.head.append(style);return true;""", [CSS])

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        driver.js("window.scrollTo(8, 0)")
        data = driver.js(measure)
        png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        path_name = ROUTE.strip("/").replace("/", "-") or "front"
        (OUT / f"candidate-{path_name}-{width}.png").write_bytes(png)
        expected_heading = "24px 20px 4px" if width < 576 else "32px 32px 4px"
        expected_content = "0px 20px 24px" if width < 576 else "0px 32px 32px"
        horizontal_scroll_blocked = data["documentWidth"] <= width or (
            data["rootOverflowX"] == "clip" and data["scrollX"] == 0
        )
        valid = (actual_width == width and EXPECTED_PATH_CLASS in data["bodyClass"].split()
                 and data["hasHeading"] and data["hasAuthForm"] and data["challengeWidth"] > 0 and not data["loggedIn"]
                 and horizontal_scroll_blocked and data["headingPadding"] == expected_heading
                 and data["contentPadding"] == expected_content
                 and (data["challengeWidth"] == 0 or data["challengeScrollWidth"] <= data["challengeWidth"] + 1))
        record(f"Runtime {ROUTE} {width}px: estilos candidatos e sem overflow", valid,
               {key: data[key] for key in ("documentWidth", "bodyWidth", "clientWidth", "rootOverflowX", "scrollX", "headingPadding", "contentPadding", "challengeWidth", "challengeScrollWidth", "challengeClientWidth")})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
