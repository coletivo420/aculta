#!/usr/bin/env python3
"""Anonymous, read-only responsive baseline for ACULTA420 public purposes."""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlparse

ROOT = Path(os.environ.get("ACULTA_ROOT", Path(__file__).resolve().parents[3])).resolve()
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

ORIGIN = os.environ.get("ACULTA_PUBLIC_ORIGIN", "https://aculta.toca.net.br")
WIDTHS = tuple(map(int, os.environ.get("OCS_WIDTHS", "320,360,390,599,600,768,1024,1280,1440").split(",")))
PURPOSES = [
    ("main", ORIGIN),
    ("account", "https://conta.aculta.toca.net.br"),
    ("support", "https://apoio.aculta.toca.net.br"),
    ("magazine", "https://revista.aculta.toca.net.br"),
    ("wiki", "https://wiki420.aculta.toca.net.br"),
    ("shop", "https://loja.aculta.toca.net.br"),
    ("courses", "https://cursos.aculta.toca.net.br"),
    ("forum", "https://growroom420.aculta.toca.net.br"),
    ("links", "https://links.aculta.toca.net.br"),
    ("eventos", "https://eventos.aculta.toca.net.br"),
]


def set_viewport(driver, width, height):
    node = r"""
const socket = new WebSocket(process.argv[1]);
socket.onopen = () => socket.send(JSON.stringify({id: 1, method: 'browsingContext.getTree', params: {}}));
socket.onmessage = (event) => {
  const message = JSON.parse(event.data);
  if (message.id === 1) {
    const context = message.result.contexts[0].context;
    socket.send(JSON.stringify({id: 2, method: 'browsingContext.setViewport', params: {context, viewport: {width: Number(process.argv[2]), height: Number(process.argv[3])}}}));
  } else if (message.id === 2) {
    if (message.error) { console.error(message.error.message); process.exitCode = 1; }
    socket.close();
  }
};
"""
    subprocess.run(["node", "-e", node, driver.websocket_url, str(width), str(height)], check=True)


def capture(driver, purpose, url, width):
    height = 1000 if width >= 1000 else 844
    set_viewport(driver, width, height)
    driver.get(url)
    for _ in range(100):
        if driver.js("return document.readyState === 'complete'"):
            break
        time.sleep(0.1)
    time.sleep(0.5)
    info = json.loads(driver.js("""
const d=document.documentElement,b=document.body;
return JSON.stringify({url:location.href,title:document.title,width:innerWidth,height:innerHeight,documentWidth:d.clientWidth,documentScrollWidth:d.scrollWidth,bodyScrollWidth:b.scrollWidth,scrollHeight:d.scrollHeight,bodyClasses:Array.from(b.classList),institutionBar:!!document.querySelector('.aculta-institution-bar'),domainHeader:!!document.querySelector('.aculta-header'),h1:Array.from(document.querySelectorAll('h1'),x=>x.innerText.trim()).slice(0,5),bodyText:b.innerText.slice(0,160),readyState:document.readyState});
"""))
    name = f"{purpose}-{width}.png"
    shot = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / name).write_bytes(base64.b64decode(shot))
    info.update({"purpose": purpose, "requestedUrl": url, "screenshot": name})
    print(json.dumps(info, ensure_ascii=False))
    return info


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use Firefox ESR visível com DISPLAY=:0.")
    driver = Driver(bidi=True)
    records = []
    try:
        for purpose, url in PURPOSES:
            for width in WIDTHS:
                try:
                    records.append(capture(driver, purpose, url, width))
                except Exception as error:
                    record = {"purpose": purpose, "requestedUrl": url, "width": width, "status": "DEFERRED", "reason": f"{type(error).__name__}: {error}"}
                    records.append(record)
                    print(json.dumps(record, ensure_ascii=False))
        (OUT / "purpose-matrix.json").write_text(json.dumps(records, indent=2, ensure_ascii=False) + "\n")
        overflows = sum(r.get("documentScrollWidth", 0) > r.get("documentWidth", 0) or r.get("bodyScrollWidth", 0) > r.get("width", 0) for r in records)
        print(json.dumps({"captures": len(records), "deferred": sum(r.get("status") == "DEFERRED" for r in records), "overflow_records": overflows, "output": str(OUT / "purpose-matrix.json")}))
    finally:
        driver.close()


if __name__ == "__main__":
    main()
