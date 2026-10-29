#!/usr/bin/env python3
"""Compare public-profile layout before/after its mobile-first CSS migration."""

import base64
import hashlib
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import quote

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
CSS_PATH = "web/themes/custom/aculta420/css/components/public-profile.css"
BASELINE_REF = os.environ.get("PUBLIC_PROFILE_BASELINE_REF", "HEAD^")
WIDTHS = [int(width) for width in os.environ.get(
    "PUBLIC_PROFILE_WIDTHS", "320,360,390,575,576,599,600,768,1024,1280,1440",
).split(",")]
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


def source_css(ref):
    process = subprocess.run(
        ["git", "show", f"{ref}:{CSS_PATH}"], cwd=ROOT, capture_output=True,
        text=True, check=True,
    )
    return process.stdout


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
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
                return response.get("result", {})

    def set(self, driver, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.15)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

fixture = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<style>:root{--aculta-border-default:#777;--aculta-border-subtle:#aaa;--aculta-border-strong:#333;
--aculta-surface-page:#fff;--aculta-surface-muted:#eee;--aculta-interactive-border:#145c39;
--aculta-interactive-text:#145c39;--aculta-space-section:1.5rem}*{box-sizing:border-box}
body{margin:0;font:16px sans-serif}.portal-profile{width:min(100%,48rem);margin:auto}
.portal-profile__avatar img,.portal-profile__initial{background:#ccc}.portal-profile__link{min-height:44px}
.portal-profile__achievement-image img{background:#ddd}</style></head><body>
<main class="portal-profile"><header class="portal-profile__header"><div class="portal-profile__avatar"><img alt="" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="></div>
<div class="portal-profile__identity"><h1 class="portal-profile__name">Nome público de exemplo para pessoa colaboradora</h1></div>
<div class="portal-profile__actions"><a href="#" class="portal-profile__link">Seguir perfil</a></div></header>
<p class="portal-profile__bio">Biografia com texto para verificar a largura, quebra de linha e espaçamento. Conteúdo do perfil público.</p>
<nav class="portal-profile__links"><a class="portal-profile__link" href="#">Rede social longa de exemplo</a><a class="portal-profile__link" href="#">Contato</a></nav>
<section class="portal-profile__achievements"><h2 class="portal-profile__achievements-title">Conquistas</h2><div class="portal-profile__achievements-list">
<article class="portal-profile__achievement"><div class="portal-profile__achievement-image"><img alt="" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="></div><div class="portal-profile__achievement-content"><h3 class="portal-profile__achievement-name">Conquista de exemplo</h3><p class="portal-profile__achievement-description">Descrição da conquista apresentada no perfil.</p><span class="portal-profile__achievement-highlight">Destaque</span></div></article>
<article class="portal-profile__achievement"><div class="portal-profile__achievement-image"><img alt="" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="></div><div class="portal-profile__achievement-content"><h3 class="portal-profile__achievement-name">Segunda conquista</h3><p class="portal-profile__achievement-description">Outra descrição comprida para reflow.</p></div></article></div></section></main></body></html>"""

measure = """return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
profileWidth:document.querySelector('.portal-profile').getBoundingClientRect().width,
headerDirection:getComputedStyle(document.querySelector('.portal-profile__header')).flexDirection,
headerAlign:getComputedStyle(document.querySelector('.portal-profile__header')).textAlign,
headerPadding:getComputedStyle(document.querySelector('.portal-profile__header')).padding,
actionsMargin:getComputedStyle(document.querySelector('.portal-profile__actions')).marginInlineStart,
bioMargin:getComputedStyle(document.querySelector('.portal-profile__bio')).marginInline,
linksPadding:getComputedStyle(document.querySelector('.portal-profile__links')).padding,
achievementPadding:getComputedStyle(document.querySelector('.portal-profile__achievements')).padding,
achievementColumns:getComputedStyle(document.querySelector('.portal-profile__achievements-list')).gridTemplateColumns};"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get("data:text/html;charset=utf-8," + quote(fixture))
    baseline = source_css(BASELINE_REF)
    candidate = (ROOT / CSS_PATH).read_text()
    driver.js("""const style=document.createElement('style');style.id='theme-candidate';
      style.textContent=arguments[0];document.head.append(style);return true;""", [baseline])

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        driver.js("window.scrollTo(0, 0)")
        time.sleep(0.1)
        before = driver.js(measure)
        before_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"baseline-{width}.png").write_bytes(before_png)
        driver.js("document.querySelector('#theme-candidate').textContent=arguments[0]", [candidate])
        driver.js("window.scrollTo(0, 0)")
        time.sleep(0.1)
        after = driver.js(measure)
        after_png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"candidate-{width}.png").write_bytes(after_png)
        no_overflow = before["documentWidth"] <= width and after["documentWidth"] <= width
        same_pixels = hashlib.sha256(before_png).hexdigest() == hashlib.sha256(after_png).hexdigest()
        record(f"{width}px: geometria e captura preservadas", actual_width == width and before == after and no_overflow and same_pixels,
               {"documentWidth": after["documentWidth"], "headerDirection": after["headerDirection"],
                "achievementColumns": after["achievementColumns"], "sameScreenshot": same_pixels})
        expected = after["headerDirection"] == ("column" if width < 576 else "row")
        record(f"{width}px: aprimoramento mobile-first correto", expected,
               {"expectedHeaderDirection": "column" if width < 576 else "row", "actual": after["headerDirection"]})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
