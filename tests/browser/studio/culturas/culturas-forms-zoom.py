#!/usr/bin/env python3
"""Measure actual Firefox zoom on the authenticated Culturas Form API forms."""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlsplit, urlunsplit

ROOT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3])).resolve()
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

LOGIN_URI = os.environ.get("CULTURAS_FORMS_LOGIN_URI", "https://conta.aculta.toca.net.br/")
DRUSH_URI = os.environ.get("CULTURAS_FORMS_DRUSH_URI", "http://default")
ADMIN_UID = int(os.environ.get("CULTURAS_FORMS_ADMIN_UID", "257"))
DEVICE_WIDTH = int(os.environ.get("CULTURAS_FORMS_DEVICE_WIDTH", "1280"))
DEVICE_HEIGHT = int(os.environ.get("CULTURAS_FORMS_DEVICE_HEIGHT", "900"))
ZOOM_TARGETS = tuple(map(float, os.environ.get("CULTURAS_FORMS_ZOOM_TARGETS", "1,2,4").split(",")))
ROUTES = [item.strip() for item in os.environ.get(
    "CULTURAS_FORMS_ROUTES",
    "https://eventos.aculta.toca.net.br/culturas/eventos/novo,"
    "https://eventos.aculta.toca.net.br/culturas/locais/novo",
).split(",") if item.strip()]
FORM_IDS = {
    "/culturas/eventos/novo": "aculta-event-add-form",
    "/culturas/locais/novo": "aculta-event-location-add-form",
}
RESULTS = []


def record(name, passed, detail):
    RESULTS.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)


def drush_uli():
    process = subprocess.run(
        [str(ROOT / "vendor/bin/drush"), "uli", "/", "--no-browser", f"--uid={ADMIN_UID}",
         f"--uri={DRUSH_URI}"],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
        timeout=45,
        env={**os.environ, "DISPLAY": ""},
    )
    lines = [line.strip() for line in process.stdout.splitlines() if line.strip()]
    generated = lines[-1] if lines else ""
    if not urlsplit(generated).path.startswith("/"):
        raise RuntimeError("Drush não retornou o link ULI esperado.")
    source = urlsplit(generated)
    destination = urlsplit(LOGIN_URI)
    if not destination.scheme or not destination.netloc:
        raise RuntimeError("CULTURAS_FORMS_LOGIN_URI deve ser uma URL absoluta.")
    return urlunsplit((destination.scheme, destination.netloc, source.path, source.query, ""))


class Viewport:
    def __init__(self, driver):
        from websockets.sync.client import connect

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
                    raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
                return response.get("result", {})

    def set(self, width, height):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": height},
        })
        time.sleep(0.2)
        return int(self.driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


def firefox_window():
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True,
    ).splitlines()
    if not windows:
        raise RuntimeError("Firefox ESR visível não foi encontrado pelo xdotool.")
    window = windows[-1]
    subprocess.run(["xdotool", "windowactivate", "--sync", window], check=True)
    return window


def zoom_in():
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+plus"], check=True)
    time.sleep(0.12)


def reset_zoom():
    firefox_window()
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+0"], check=True)
    time.sleep(0.25)


def set_zoom(driver, target):
    reset_zoom()
    if target <= 1:
        return
    for _ in range(24):
        zoom = DEVICE_WIDTH / max(1, int(driver.js("return innerWidth")))
        if zoom >= target - 0.07:
            return
        firefox_window()
        zoom_in()
    raise RuntimeError(f"Firefox não atingiu {target:.0%} de zoom.")


def inspect(driver, form_id):
    return json.loads(driver.js("""return JSON.stringify((()=>{
      const form=document.getElementById(arguments[0]);
      const box=e=>{if(!e)return null;const r=e.getBoundingClientRect();return {
        left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height};};
      const line2=form?.querySelector('input[name$="[address_line2]"]');
      const sn=form?.querySelector('input[name$="[street_number_sn]"]');
      const labels=sn?.labels?[...sn.labels]:[];
      const rects=[...(sn?[sn]:[]),...labels].map(e=>e.getBoundingClientRect());
      const target=rects.length?{left:Math.min(...rects.map(r=>r.left)),
        right:Math.max(...rects.map(r=>r.right)),top:Math.min(...rects.map(r=>r.top)),
        bottom:Math.max(...rects.map(r=>r.bottom))}:null;
      return {formPresent:!!form,formId:form?.id||'',innerWidth,innerHeight,devicePixelRatio,
        zoomRatio:arguments[1]/innerWidth,documentWidth:document.documentElement.clientWidth,
        documentScrollWidth:document.documentElement.scrollWidth,
        bodyScrollWidth:document.body.scrollWidth,documentHeight:document.documentElement.scrollHeight,
        institutionBars:document.querySelectorAll('.aculta-institution-bar').length,
        domainHeaders:document.querySelectorAll('.aculta-header').length,
        line2:box(line2),sn:box(sn),snLabels:labels.map(label=>({width:label.getBoundingClientRect().width,
          height:label.getBoundingClientRect().height})),snTarget:target,
        visibleControlCount:form?[...form.querySelectorAll('input:not([type="hidden"]):not([disabled]),'
          +'select:not([disabled]),textarea:not([disabled]),button:not([disabled])')]
          .filter(e=>e.tabIndex>=0&&e.getClientRects().length>0&&getComputedStyle(e).visibility!=='hidden').length:0,
        focusedId:document.activeElement?.id||'',focusedVisible:document.activeElement?.matches(':focus-visible')||false,
        focusedOutline:getComputedStyle(document.activeElement).outlineStyle,
        focusedOutlineWidth:getComputedStyle(document.activeElement).outlineWidth,
        focusedShadow:getComputedStyle(document.activeElement).boxShadow,
        focusedRect:box(document.activeElement)};
    })());""", [form_id, DEVICE_WIDTH]))


def tab_to_targets(driver, form_id):
    driver.js("document.activeElement.blur();window.scrollTo(0,0);document.body.tabIndex=-1;document.body.focus();return true")
    # Prime Gecko's keyboard modality with an actual X11 key event after real
    # Firefox zoom shortcuts. W3C actions alone can retain pointer modality
    # across the Ctrl+0/Ctrl+plus sequence on some ESR builds.
    firefox_window()
    subprocess.run(["xdotool", "key", "--clearmodifiers", "Tab"], check=True)
    time.sleep(0.08)
    targets = {}
    for _ in range(220):
        driver.req("POST", f"/session/{driver.session}/actions", {
            "actions": [{"type": "key", "id": "culturas-forms-zoom-tab", "actions": [
                {"type": "keyDown", "value": "\ue004"},
                {"type": "keyUp", "value": "\ue004"},
            ]}],
        })
        state = driver.js("""return (()=>{
          const form=document.getElementById(arguments[0]),e=document.activeElement;
          if(!form?.contains(e))return null;
          const name=e.getAttribute('name')||'';
          let targetName='';
          if(name.endsWith('[address_line2]'))targetName='address_line2';
          else if(name.endsWith('[street_number_sn]'))targetName='street_number_sn';
          else if(form.id==='aculta-event-add-form'
            &&e===form.querySelector('input:not([type="hidden"]):not([disabled]),select:not([disabled]),textarea:not([disabled])'))targetName='primary';
          if(!targetName)return null;
          const r=e.getBoundingClientRect(),style=getComputedStyle(e);
          const visibleHeight=Math.max(0,Math.min(r.bottom,innerHeight)-Math.max(r.top,0));
          const visibleWidth=Math.max(0,Math.min(r.right,innerWidth)-Math.max(r.left,0));
          return {name:targetName,
            focusVisible:e.matches(':focus-visible'),outlineStyle:style.outlineStyle,
            outlineWidth:style.outlineWidth,boxShadow:style.boxShadow,
            left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height,
            viewport:innerWidth,viewportHeight:innerHeight,
            visibleFraction:Math.min(visibleHeight/r.height,visibleWidth/r.width)};
        })();""", [form_id])
        if state:
            targets[state["name"]] = state
        if len(targets) == (2 if form_id == "aculta-event-location-add-form" else 1):
            break
    # Let Firefox finish its native scroll-to-focused-control before asserting
    # the viewport bounds (notably with the short CSS viewport at 400% zoom).
    time.sleep(0.35)
    final = driver.js("""return (()=>{
      const form=document.getElementById(arguments[0]),e=document.activeElement;
      if(!form?.contains(e))return null;
      const name=e.getAttribute('name')||'';
      let targetName='';
      if(name.endsWith('[address_line2]'))targetName='address_line2';
      else if(name.endsWith('[street_number_sn]'))targetName='street_number_sn';
      else if(form.id==='aculta-event-add-form'
        &&e===form.querySelector('input:not([type="hidden"]):not([disabled]),select:not([disabled]),textarea:not([disabled])'))targetName='primary';
      if(!targetName)return null;
      const r=e.getBoundingClientRect(),style=getComputedStyle(e);
      const visibleHeight=Math.max(0,Math.min(r.bottom,innerHeight)-Math.max(r.top,0));
      const visibleWidth=Math.max(0,Math.min(r.right,innerWidth)-Math.max(r.left,0));
      return {name:targetName,focusVisible:e.matches(':focus-visible'),
        outlineStyle:style.outlineStyle,outlineWidth:style.outlineWidth,boxShadow:style.boxShadow,
        left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height,
        viewport:innerWidth,viewportHeight:innerHeight,
        visibleFraction:Math.min(visibleHeight/r.height,visibleWidth/r.width)};
    })();""", [form_id])
    if final and final["name"] in targets:
        targets[final["name"]] = final
    return targets


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
    driver = Driver(bidi=True)
    viewport = None
    try:
        viewport = Viewport(driver)
        link = drush_uli()
        driver.get(link)
        for _ in range(100):
            if driver.js("return document.readyState==='complete'"):
                break
            time.sleep(0.1)

        for route in ROUTES:
            path = urlsplit(route).path.rstrip("/")
            form_id = FORM_IDS.get(path)
            if not form_id:
                raise RuntimeError(f"Rota não autorizada nesta suíte: {path}")
            driver.get(route)
            for _ in range(100):
                if driver.js("return document.readyState==='complete'"):
                    break
                time.sleep(0.1)
            actual_width = viewport.set(DEVICE_WIDTH, DEVICE_HEIGHT)
            record(f"{path}: viewport base", actual_width == DEVICE_WIDTH,
                   {"actualWidth": actual_width, "expectedWidth": DEVICE_WIDTH})

            for target in ZOOM_TARGETS:
                set_zoom(driver, target)
                time.sleep(0.3)
                baseline = inspect(driver, form_id)
                base_ok = (
                    baseline["formPresent"]
                    and baseline["institutionBars"] == 1
                    and baseline["domainHeaders"] == 1
                    and baseline["documentScrollWidth"] <= baseline["documentWidth"] + 1
                    and baseline["bodyScrollWidth"] <= baseline["innerWidth"] + 1
                    and abs(baseline["zoomRatio"] - target) <= 0.12
                )
                if path == "/culturas/locais/novo":
                    base_ok = base_ok and baseline["line2"] is not None and baseline["line2"]["width"] >= 44
                    base_ok = base_ok and baseline["sn"] is not None and baseline["snTarget"] is not None
                    base_ok = base_ok and baseline["snTarget"]["right"] - baseline["snTarget"]["left"] >= 44
                    base_ok = base_ok and baseline["snTarget"]["bottom"] - baseline["snTarget"]["top"] >= 44
                name = f"{path} zoom {target:.0%} layout"
                record(name, base_ok, baseline)
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                slug = path.strip("/").replace("/", "-")
                (OUT / f"{slug}-zoom-{round(target * 100)}.png").write_bytes(base64.b64decode(shot))

                if target >= 2:
                    keyboard = tab_to_targets(driver, form_id)
                    keyboard_ok = bool(keyboard) and all(
                        item["focusVisible"]
                        and (item["outlineStyle"] not in ("none", "")
                             and float(item["outlineWidth"].removesuffix("px")) >= 2
                             or item["boxShadow"] not in ("none", ""))
                        and item["left"] >= -1 and item["right"] <= item["viewport"] + 1
                        and item["visibleFraction"] >= 0.85
                        for item in keyboard.values()
                    )
                    if path == "/culturas/locais/novo":
                        keyboard_ok = keyboard_ok and len(keyboard) == 2
                    record(f"{path} zoom {target:.0%} keyboard focus", keyboard_ok, keyboard)
                    focused_shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    (OUT / f"{slug}-zoom-{round(target * 100)}-focus.png").write_bytes(
                        base64.b64decode(focused_shot)
                    )
                reset_zoom()
    finally:
        try:
            reset_zoom()
        except Exception:
            pass
        if viewport:
            viewport.close()
        driver.close()

    report = {"results": RESULTS, "passed": sum(item["passed"] for item in RESULTS),
              "failed": sum(not item["passed"] for item in RESULTS),
              "routes": [urlsplit(route).path for route in ROUTES],
              "deviceWidth": DEVICE_WIDTH, "zoomTargets": ZOOM_TARGETS}
    (OUT / "report.json").write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n")
    print(json.dumps({"passed": report["passed"], "failed": report["failed"]}, ensure_ascii=False))
    if report["failed"]:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
