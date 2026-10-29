#!/usr/bin/env python3
"""Read-only theme color-scheme and reduced-motion visual probe."""
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
WIDTHS = tuple(map(int, os.environ.get("OCS_MODE_WIDTHS", "320,1440").split(",")))
MODE = os.environ.get("OCS_COLOR_SCHEME", "auto").lower()
REDUCED = os.environ.get("OCS_REDUCED_MOTION", "0") == "1"
if MODE not in {"auto", "light", "dark"}:
    raise ValueError("OCS_COLOR_SCHEME must be auto, light, or dark.")


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


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use Firefox ESR visível com DISPLAY=:0.")
    host = subprocess.check_output(["hostname"], text=True).strip()
    status = drush("status", "--fields=uri,root,db-name")
    if host != "toca" or "aculta_runtime" not in status or str(ROOT / "web") not in status:
        raise RuntimeError("Probe permitido apenas no toca/aculta_runtime do checkout atual.")
    login = drush("uli", "--no-browser", f"--name={USER}", f"--uri={URI}").splitlines()[-1]
    prefs = {}
    if MODE != "auto":
        prefs["ui.systemUsesDarkTheme"] = 1 if MODE == "dark" else 0
    if REDUCED:
        prefs["ui.prefersReducedMotion"] = 1
    driver = Driver(prefs=prefs, bidi=True)
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
                if MODE in {"light", "dark"}:
                    driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0]); return true", [MODE])
                else:
                    driver.js("document.documentElement.removeAttribute('data-bs-theme'); return true")
                data = json.loads(driver.js("""
const root=getComputedStyle(document.documentElement);
return JSON.stringify({title:document.title,url:location.href,width:innerWidth,documentWidth:document.documentElement.clientWidth,documentScrollWidth:document.documentElement.scrollWidth,bodyScrollWidth:document.body.scrollWidth,themeAttribute:document.documentElement.getAttribute('data-bs-theme'),prefersDark:matchMedia('(prefers-color-scheme: dark)').matches,prefersReducedMotion:matchMedia('(prefers-reduced-motion: reduce)').matches,surfacePage:root.getPropertyValue('--aculta-surface-page').trim(),textPrimary:root.getPropertyValue('--aculta-text-primary').trim(),institutionBar:!!document.querySelector('.aculta-institution-bar'),domainHeader:!!document.querySelector('.aculta-header')});
"""))
                name = f"{url.rstrip('/').split('/')[-1] or 'hub'}-{width}.png"
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / name).write_bytes(base64.b64decode(shot))
                data.update({"mode": MODE, "reducedMotionRequested": REDUCED, "screenshot": name})
                records.append(data)
        (OUT / "theme-modes.json").write_text(json.dumps(records, indent=2) + "\n")
        print(json.dumps({"captures": len(records), "mode": MODE, "reduced_motion_requested": REDUCED, "dark_matches": sum(r["prefersDark"] for r in records), "reduced_motion_matches": sum(r["prefersReducedMotion"] for r in records), "output": str(OUT / "theme-modes.json")}))
    finally:
        driver.close()


if __name__ == "__main__":
    main()
