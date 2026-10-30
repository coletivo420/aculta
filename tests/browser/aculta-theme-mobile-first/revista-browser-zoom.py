#!/usr/bin/env python3
"""Exercise actual Firefox page zoom on the public Magazine landing page."""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time

ROOT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3])).resolve()
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

URL = os.environ.get("REVISTA_BASE_URL", "https://revista.aculta.toca.net.br/")
WIDTH = int(os.environ.get("REVISTA_ZOOM_DEVICE_WIDTH", "1280"))
TARGETS = (2.0, 4.0)


def set_viewport(driver, width, height):
    node = r"""
const ws = new WebSocket(process.argv[1]);
ws.onopen = () => ws.send(JSON.stringify({id: 1, method: 'browsingContext.getTree', params: {}}));
ws.onmessage = event => {
  const m = JSON.parse(event.data);
  if (m.id === 1) {
    ws.send(JSON.stringify({id: 2, method: 'browsingContext.setViewport', params: {
      context: m.result.contexts[0].context,
      viewport: {width: Number(process.argv[2]), height: Number(process.argv[3])}
    }}));
  } else if (m.id === 2) {
    if (m.error) { console.error(m.error.message); process.exitCode = 1; }
    ws.close();
  }
};
"""
    subprocess.run(["node", "-e", node, driver.websocket_url, str(width), "900"], check=True)


def press_zoom_in(driver):
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()
    if not windows:
        raise RuntimeError("Firefox ESR visível não foi encontrado pelo xdotool.")
    window = windows[-1]
    subprocess.run(["xdotool", "windowactivate", "--sync", window], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+plus"], check=True)


def inspect(driver):
    return json.loads(driver.js("""
const d=document.documentElement, b=document.body;
const cover=document.querySelector('.aculta-revista-teaser');
const coverRect=cover?.getBoundingClientRect();
const coverImage=cover?.querySelector('img')?.getBoundingClientRect();
return JSON.stringify({url:location.href, title:document.title, innerWidth, devicePixelRatio,
  documentWidth:d.clientWidth, documentScrollWidth:d.scrollWidth,
  bodyScrollWidth:b.scrollWidth, imageCount:document.querySelectorAll('.aculta-revista-teaser img').length,
  h1:Array.from(document.querySelectorAll('h1'), e=>e.innerText.trim()),
  firstCover:coverRect?{x:coverRect.x,y:coverRect.y,width:coverRect.width,height:coverRect.height}:null,
  firstCoverImage:coverImage?{x:coverImage.x,y:coverImage.y,width:coverImage.width,height:coverImage.height}:null,
  institutionBarCount:document.querySelectorAll('.aculta-institution-bar').length,
  domainHeaderCount:document.querySelectorAll('.aculta-header').length});
"""))


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use Firefox ESR visível com DISPLAY=:0.")
    if subprocess.check_output(["hostname"], text=True).strip() != "toca":
        raise RuntimeError("Este roteiro foi definido para o servidor de desenvolvimento toca.")
    status = subprocess.check_output([
        "php", "vendor/drush/drush/drush.php", "status", "--fields=uri,root,db-name"
    ], cwd=ROOT, text=True)
    if "aculta_runtime" not in status or str(ROOT / "web") not in status:
        raise RuntimeError("Drush não confirmou aculta_runtime no checkout esperado.")

    driver = Driver(bidi=True)
    records = []
    try:
        driver.get(URL)
        time.sleep(1)
        set_viewport(driver, WIDTH, 900)
        baseline = inspect(driver)
        records.append({"targetZoom": 1.0, **baseline})
        for target in TARGETS:
            current = inspect(driver)["devicePixelRatio"]
            for _ in range(24):
                if current >= target - 0.08:
                    break
                press_zoom_in(driver)
                time.sleep(0.12)
                current = inspect(driver)["devicePixelRatio"]
            data = inspect(driver)
            data["targetZoom"] = target
            data["reachedZoom"] = current
            data["overflow"] = data["documentScrollWidth"] > data["documentWidth"] or data["bodyScrollWidth"] > data["innerWidth"]
            records.append(data)
            if current < target - 0.08:
                data["status"] = "DEFERRED"
                data["reason"] = "Firefox keyboard shortcut did not reach requested page zoom."
                break
            driver.js("document.querySelector('.aculta-revista-teaser')?.scrollIntoView({block:'start'}); return true")
            time.sleep(0.2)
            shot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"revista-zoom-{round(target * 100)}.png").write_bytes(base64.b64decode(shot))
            driver.js("window.scrollTo(0,0); return true")
    finally:
        driver.close()

    (OUT / "summary.json").write_text(json.dumps(records, indent=2, ensure_ascii=False) + "\n")
    print(json.dumps({"records": len(records), "targets": [r.get("reachedZoom") for r in records if "targetZoom" in r],
                      "overflow": [r.get("overflow") for r in records if "targetZoom" in r and r.get("targetZoom") >= 2],
                      "output": str(OUT / "summary.json")}, ensure_ascii=False))
    if any(r.get("status") == "DEFERRED" for r in records):
        raise SystemExit(3)
    if any(r.get("overflow") for r in records):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
