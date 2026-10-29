#!/usr/bin/env python3
"""Read-only keyboard focus probe for authenticated Studio pages."""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time

ROOT = Path(os.environ.get("ACULTA_ROOT", Path(__file__).resolve().parents[3])).resolve()
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

URI = os.environ.get("STUDIO_LOGIN_URI", "https://conta.aculta.toca.net.br")
USER = os.environ.get("STUDIO_USER", "teste.comum")
PAGES = os.environ.get("STUDIO_ROUTES", "/studio/marley,/studio/conta,/studio/perfil").split(",")
WIDTHS = tuple(map(int, os.environ.get("OCS_FOCUS_WIDTHS", "320,1440").split(",")))
TAB = "\uE004"


def drush(*args):
    return subprocess.check_output(
        ["php", "vendor/drush/drush/drush.php", *args], cwd=ROOT, text=True
    ).strip()


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


def press_tab(driver):
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{"type": "key", "id": "studio-focus-probe", "actions": [
            {"type": "keyDown", "value": TAB}, {"type": "keyUp", "value": TAB}
        ]}]
    })


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use Firefox ESR visível com DISPLAY=:0.")
    host = subprocess.check_output(["hostname"], text=True).strip()
    status = drush("status", "--fields=uri,root,db-name")
    if host != "toca" or "aculta_runtime" not in status or str(ROOT / "web") not in status:
        raise RuntimeError("Probe permitido apenas no toca/aculta_runtime do checkout atual.")
    login = drush("uli", "--no-browser", f"--name={USER}", f"--uri={URI}").splitlines()[-1]
    driver = Driver(bidi=True)
    records = []
    try:
        driver.get(login)
        time.sleep(2)
        for page in filter(None, (p.strip() for p in PAGES)):
            url = page if page.startswith(("http://", "https://")) else URI.rstrip("/") + "/" + page.lstrip("/")
            for width in WIDTHS:
                height = 1000 if width >= 1000 else 844
                set_viewport(driver, width, height)
                driver.get(url)
                time.sleep(1)
                for index in range(1, 13):
                    press_tab(driver)
                    time.sleep(0.05)
                    info = json.loads(driver.js("""
const el=document.activeElement, s=getComputedStyle(el), r=el.getBoundingClientRect();
return JSON.stringify({tag:el.tagName,id:el.id,className:typeof el.className==='string'?el.className:'',role:el.getAttribute('role'),ariaLabel:el.getAttribute('aria-label'),focusVisible:el.matches(':focus-visible'),outlineStyle:s.outlineStyle,outlineWidth:s.outlineWidth,outlineColor:s.outlineColor,boxShadow:s.boxShadow,left:r.left,top:r.top,width:r.width,height:r.height,scrollX:scrollX,scrollY:scrollY});
"""))
                    info.update({"url": url, "viewport": width, "tab": index})
                    if index == 1:
                        shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                        name = f"focus-{url.rstrip('/').split('/')[-1] or 'hub'}-{width}.png"
                        (OUT / name).write_bytes(base64.b64decode(shot))
                        info["screenshot"] = name
                    records.append(info)
        (OUT / "keyboard-focus.json").write_text(json.dumps(records, indent=2) + "\n")
        print(json.dumps({"records": len(records), "focus_visible": sum(r["focusVisible"] for r in records), "screenshots": sum("screenshot" in r for r in records), "output": str(OUT / "keyboard-focus.json")}))
    finally:
        driver.close()


if __name__ == "__main__":
    main()
