#!/usr/bin/env python3
"""Read-only Firefox validation of the real public editorial carousel."""

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
BASE = os.environ.get("EDITORIAL_CAROUSEL_BASE_URL", "https://aculta.toca.net.br").rstrip("/")
WIDTHS = [int(width) for width in os.environ.get(
    "EDITORIAL_CAROUSEL_RUNTIME_WIDTHS", "320,360,390,575,576,599,600,768,769,1024,1280,1440",
).split(",")]
CSS = (ROOT / "web/themes/custom/aculta420/css/components/editorial-carousel.css").read_text()
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

measure = """const view=document.querySelector('.view-home-editorial-highlights');
const wrapper=view?.querySelector('.vvjb-carousel-wrapper');
const item=view?.querySelector('.vvjb-item');
return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
clientWidth:document.documentElement.clientWidth,overflowX:getComputedStyle(document.documentElement).overflowX,
scrollX:window.scrollX,scrollY:window.scrollY,viewTop:view?.getBoundingClientRect().top||0,
viewVisible:view?(()=>{const r=view.getBoundingClientRect();return r.top<innerHeight&&r.bottom>0})():false,
viewText:view?.innerText?.slice(0,80)||'',view:!!view,wrapper:!!wrapper,items:view?.querySelectorAll('.vvjb-item').length||0,
initialized:view?.querySelector('.vvjb-inner')?.dataset.vvjbInitialized==='true',
activeItems:view?.querySelectorAll('.vvjb-item:not(.non-active-slide)').length||0,
visibleItems:view?[...view.querySelectorAll('.vvjb-item')].filter(el=>getComputedStyle(el).visibility==='visible'&&getComputedStyle(el).display!=='none'&&(el.innerText.trim()||el.querySelector('img[src],video,svg')||getComputedStyle(el).backgroundImage!=='none')).length:0,
wrapperWidth:wrapper?.getBoundingClientRect().width||0,wrapperMax:wrapper?getComputedStyle(wrapper).maxWidth:'',
itemWidth:item?.getBoundingClientRect().width||0,
wrapperHeight:wrapper?.getBoundingClientRect().height||0,
itemsDetail:[...(view?.querySelectorAll('.vvjb-item')||[])].slice(0,3).map(el=>({classes:el.className,height:Math.round(el.getBoundingClientRect().height),visibility:getComputedStyle(el).visibility,visibleTextLength:el.innerText.trim().length,contentTextLength:el.textContent.trim().length,imageCount:el.querySelectorAll('img').length,loadedImages:[...el.querySelectorAll('img')].filter(image=>image.complete&&image.naturalWidth>0).length})),
controls:!!view?.querySelector('.vvjb-carousel-controls')};"""

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get(f"{BASE}/")
    initial = driver.js(measure)
    deadline = time.monotonic() + 10
    while time.monotonic() < deadline and (not initial["view"] or not initial["wrapper"]):
        time.sleep(0.25)
        initial = driver.js(measure)
    if initial["view"] and initial["wrapper"]:
        driver.js("document.querySelector('.view-home-editorial-highlights').scrollIntoView({block:'center',behavior:'instant'})")
        deadline = time.monotonic() + 10
        while time.monotonic() < deadline and not initial["initialized"]:
            time.sleep(0.25)
            initial = driver.js(measure)
    record("home MAIN tem consumer editorial real", initial["view"] and initial["wrapper"] and initial["items"] > 0,
           {key: initial[key] for key in ("view", "wrapper", "items", "controls")})
    record("VVJB inicializa após trazer a View à tela", initial["initialized"],
           {key: initial[key] for key in ("initialized", "activeItems", "visibleItems", "wrapperHeight", "itemsDetail")})
    record("home MAIN exibe conteúdo nos slides editoriais", initial["visibleItems"] > 0,
           {"visibleItems": initial["visibleItems"], "slideCount": initial["items"]})
    driver.js("""const style=document.createElement('style');style.id='editorial-candidate';
      style.textContent=arguments[0];document.head.append(style);return true;""", [CSS])

    for width in WIDTHS:
        actual_width = viewport.set(driver, width)
        for _ in range(8):
            driver.js("document.querySelector('.view-home-editorial-highlights').scrollIntoView({block:'start',behavior:'instant'})")
            time.sleep(0.1)
            if driver.js("const r=document.querySelector('.view-home-editorial-highlights').getBoundingClientRect();return r.top<innerHeight&&r.bottom>0"):
                break
        driver.js("window.scrollTo({left:8,top:window.scrollY,behavior:'instant'})")
        data = driver.js(measure)
        png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"candidate-{width}.png").write_bytes(png)
        wrapper_cap = 390 if width <= 768 else 764
        expected_item = data["wrapperWidth"] if width <= 768 else min(370, data["wrapperWidth"])
        no_horizontal_scroll = data["documentWidth"] <= width or (
            data["overflowX"] == "clip" and data["scrollX"] == 0
        )
        passed = (actual_width == width and data["view"] and data["wrapper"] and data["items"] > 0
                  and data["controls"] and data["viewVisible"] and no_horizontal_scroll and data["wrapperWidth"] <= min(width, wrapper_cap)
                  and abs(data["itemWidth"] - expected_item) < 1)
        record(f"home MAIN {width}px: carrossel real mobile-first sem overflow", passed,
               {key: data[key] for key in ("documentWidth", "overflowX", "scrollX", "scrollY", "viewTop", "viewVisible", "wrapperMax", "wrapperWidth", "itemWidth", "items")})
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
