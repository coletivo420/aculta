#!/usr/bin/env python3
"""Compare profile-identity SDC baseline and mobile-first CSS in Firefox."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import quote

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
CSS_PATH = "web/themes/custom/aculta420/components/content/profile-identity/profile-identity.css"
BASELINE_REF = os.environ.get("PROFILE_IDENTITY_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "PROFILE_IDENTITY_WIDTHS", "320,352,353,360,390,575,576,599,600,767,768,1024,1280,1440",
).split(",")]
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    suffix = f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""
    print(f"{'PASS' if passed else 'FAIL'} {name}{suffix}", flush=True)


def source_css(ref):
    return subprocess.run(
        ["git", "show", f"{ref}:{CSS_PATH}"], cwd=ROOT,
        capture_output=True, text=True, check=True,
    ).stdout


class Viewport:
    def __init__(self, driver):
        from websockets.sync.client import connect

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
                    raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
                return response.get("result", {})

    def set(self, driver, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.12)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

fixture = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<style>
*{box-sizing:border-box}body{margin:0;font:16px sans-serif}
:root{--aculta-border-default:#777;--aculta-border-accent:#333;--aculta-surface-raised:#fff;
--aculta-text-primary:#111;--aculta-surface-muted:#eee;--aculta-text-secondary:#444;--bs-border-radius:.25rem}
.fixture{width:100%;padding:1rem}.aculta-profile-identity__name{max-width:100%}
</style></head><body><main class="fixture">
<section class="aculta-profile-identity"><div class="aculta-profile-identity__main">
<div class="aculta-profile-identity__avatar aculta-profile-identity__avatar--initial">M</div>
<div class="aculta-profile-identity__details"><h2 class="aculta-profile-identity__name">Maria Exemplo com um nome extenso</h2>
<p class="aculta-profile-identity__hint">O apelido público é independente dos dados cadastrais.</p></div></div>
<div class="aculta-profile-identity__actions"><a href="#publico">Ver perfil público</a>
<a href="#apelido">Editar apelido</a></div></section></main></body></html>"""

measure = """return (() => {
 const card=document.querySelector('.aculta-profile-identity');
 const main=document.querySelector('.aculta-profile-identity__main');
 const avatar=document.querySelector('.aculta-profile-identity__avatar');
 const rect=(el)=>{const r=el.getBoundingClientRect();return {top:Math.round(r.top),left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),height:Math.round(r.height)}};
 const mainStyle=getComputedStyle(main), avatarStyle=getComputedStyle(avatar);
 return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
   direction:getComputedStyle(card).flexDirection,mainAlign:mainStyle.alignItems,
   mainGap:mainStyle.gap,avatarBasis:avatarStyle.flexBasis,
   avatarWidth:avatarStyle.inlineSize,avatarHeight:avatarStyle.blockSize,
   regions:[...card.children].map(el=>el.className),domOrder:[...card.querySelectorAll('h2,p,a')].map(el=>el.textContent.trim()),
   card:rect(card),main:rect(main),avatar:rect(avatar),overflow:document.documentElement.scrollWidth>innerWidth};
})();"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text()
    driver.js("""const style=document.createElement('style');style.id='profile-identity-theme';
      document.head.append(style);return true;""")

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        driver.js("document.querySelector('#profile-identity-theme').textContent=arguments[0]", [baseline])
        time.sleep(0.08)
        before = driver.js(measure)
        (OUT / f"baseline-{width}.png").write_bytes(
            base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")),
        )
        driver.js("document.querySelector('#profile-identity-theme').textContent=arguments[0]", [candidate])
        time.sleep(0.08)
        after = driver.js(measure)
        (OUT / f"candidate-{width}.png").write_bytes(
            base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")),
        )

        compact = width <= 352
        record(f"{width}px viewport", actual_width == width, {"measured": actual_width})
        record(f"{width}px layout parity and overflow", after["overflow"] is False
               and after["direction"] == before["direction"]
               and after["mainAlign"] == before["mainAlign"]
               and after["avatarBasis"] == before["avatarBasis"]
               and after["avatarWidth"] == before["avatarWidth"]
               and after["avatarHeight"] == before["avatarHeight"],
               {"baseline": before, "candidate": after})
        record(f"{width}px intended profile sizing", after["avatarWidth"] == ("56px" if compact else "72px")
               and after["mainAlign"] == ("flex-start" if compact else "center")
               and after["direction"] == ("row" if width >= 768 else "column"))
        record(f"{width}px DOM and reading order", after["regions"] == before["regions"]
               and after["domOrder"] == before["domOrder"])

    (OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
finally:
    viewport.close()
    driver.close()

failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
