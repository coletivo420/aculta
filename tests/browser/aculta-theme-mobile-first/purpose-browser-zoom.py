#!/usr/bin/env python3
"""Exercise real Firefox page zoom on the ten public ACULTA purposes."""

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

WIDTH = int(os.environ.get("PURPOSE_ZOOM_DEVICE_WIDTH", "1280"))
TARGETS = (2.0, 4.0)
DOMAIN_ENV = os.environ.get("PURPOSE_ZOOM_DOMAIN_ENV", "test")
DOMAIN_IDS = {
    "main": "aculta_org",
    "account": "conta_aculta_org",
    "support": "apoio_aculta_org",
    "magazine": "revista420_aculta_org",
    "wiki": "wiki420_aculta_org",
    "shop": "loja_aculta_org",
    "courses": "cursos_aculta_org",
    "forum": "forum_aculta_org",
    "links": "links_aculta_org",
    "eventos": "eventos_aculta_org",
}


def drush(*args):
    return subprocess.check_output(
        ["php", "vendor/drush/drush/drush.php", *args], cwd=ROOT, text=True,
    ).strip()


def verify_runtime():
    host = subprocess.check_output(["hostname"], text=True).strip()
    status = drush("status", "--fields=uri,root,db-name")
    if host != "toca" or "aculta_runtime" not in status or str(ROOT / "web") not in status:
        raise RuntimeError("Zoom matrix permitted only against toca/aculta_runtime.")


def purpose_urls():
    if DOMAIN_ENV not in ("test", "production"):
        raise RuntimeError("PURPOSE_ZOOM_DOMAIN_ENV must be test or production.")
    config = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
    entries = json.loads(config.read_text())["domain_transition"]["entries"]
    hosts = {item["domain_id"]: item[f"{DOMAIN_ENV}_host"] for item in entries}
    missing = set(DOMAIN_IDS.values()) - set(hosts)
    if missing:
        raise RuntimeError(f"Domain hosts missing: {', '.join(sorted(missing))}")
    return [(purpose, f"https://{hosts[domain_id]}/") for purpose, domain_id in DOMAIN_IDS.items()]


def set_viewport(driver, width, height=900):
    script = r"""
const socket = new WebSocket(process.argv[1]);
socket.onopen = () => socket.send(JSON.stringify({id: 1, method: 'browsingContext.getTree', params: {}}));
socket.onmessage = event => {
  const message = JSON.parse(event.data);
  if (message.id === 1) {
    socket.send(JSON.stringify({id: 2, method: 'browsingContext.setViewport', params: {
      context: message.result.contexts[0].context,
      viewport: {width: Number(process.argv[2]), height: Number(process.argv[3])}
    }}));
  } else if (message.id === 2) {
    if (message.error) { console.error(message.error.message); process.exitCode = 1; }
    socket.close();
  }
};
"""
    subprocess.run(["node", "-e", script, driver.websocket_url, str(width), str(height)], check=True)


def press(driver, keys):
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True,
    ).splitlines()
    if not windows:
        raise RuntimeError("Visible Firefox ESR window not found by xdotool.")
    window = windows[-1]
    subprocess.run(["xdotool", "windowactivate", "--sync", window], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", keys], check=True)


def inspect(driver, device_width):
    return json.loads(driver.js("""
const d=document.documentElement, b=document.body;
return JSON.stringify({
  width:innerWidth, deviceWidth:Number(arguments[0]), zoomRatio:Number(arguments[0])/innerWidth,
  devicePixelRatio, documentWidth:d.clientWidth, documentScrollWidth:d.scrollWidth,
  bodyScrollWidth:b.scrollWidth,
  institutionBarCount:document.querySelectorAll('.aculta-institution-bar').length,
  domainHeaderCount:document.querySelectorAll('.aculta-header').length,
  mainCount:document.querySelectorAll('main#main-content').length,
  visibleH1Count:[...document.querySelectorAll('main#main-content h1')]
    .filter(node=>node.getClientRects().length && node.innerText.trim()).length
});
""", [device_width]))


def check(records, name, passed, detail):
    records.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}: {json.dumps(detail, ensure_ascii=False)}", flush=True)


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use visible Firefox ESR with DISPLAY=:0.")
    if subprocess.run(["which", "xdotool"], capture_output=True).returncode:
        raise RuntimeError("xdotool is required for real browser zoom.")
    verify_runtime()

    driver = Driver(bidi=True)
    records = []
    try:
        for purpose, url in purpose_urls():
            press(driver, "ctrl+0")
            driver.get(url)
            time.sleep(0.5)
            set_viewport(driver, WIDTH)
            baseline = inspect(driver, WIDTH)
            check(records, f"{purpose}: 100% baseline and unique shell",
                  baseline["width"] == WIDTH and abs(baseline["zoomRatio"] - 1.0) < 0.08
                  and baseline["documentScrollWidth"] <= baseline["documentWidth"]
                  and baseline["bodyScrollWidth"] <= baseline["width"]
                  and baseline["institutionBarCount"] == 1 and baseline["domainHeaderCount"] == 1
                  and baseline["mainCount"] == 1,
                  {key: baseline[key] for key in ("width", "zoomRatio", "documentScrollWidth",
                     "institutionBarCount", "domainHeaderCount", "mainCount", "visibleH1Count")})

            for target in TARGETS:
                data = inspect(driver, WIDTH)
                for _ in range(24):
                    if data["zoomRatio"] >= target - 0.08:
                        break
                    press(driver, "ctrl+plus")
                    time.sleep(0.12)
                    data = inspect(driver, WIDTH)
                data["targetZoom"] = target
                data["overflow"] = (data["documentScrollWidth"] > data["documentWidth"]
                                    or data["bodyScrollWidth"] > data["width"])
                reached = data["zoomRatio"] >= target - 0.08
                check(records, f"{purpose}: real browser zoom {round(target * 100)}%",
                      reached and not data["overflow"]
                      and data["institutionBarCount"] == 1 and data["domainHeaderCount"] == 1,
                      {key: data[key] for key in ("width", "zoomRatio", "devicePixelRatio",
                         "documentWidth", "documentScrollWidth", "bodyScrollWidth", "overflow",
                         "institutionBarCount", "domainHeaderCount", "mainCount", "visibleH1Count")})
                if reached:
                    shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    name = f"{purpose}-zoom-{round(target * 100)}.png"
                    (OUT / name).write_bytes(base64.b64decode(shot))
                if target == TARGETS[0]:
                    press(driver, "ctrl+plus")
                    time.sleep(0.12)
    finally:
        driver.close()

    (OUT / "summary.json").write_text(json.dumps({
        "runtime": "aculta_runtime",
        "domainEnvironment": DOMAIN_ENV,
        "deviceWidth": WIDTH,
        "purposes": [purpose for purpose, _ in purpose_urls()],
        "targets": TARGETS,
        "checks": records,
    }, ensure_ascii=False, indent=2) + "\n")
    failures = [record for record in records if not record["passed"]]
    print(f"\n{len(records) - len(failures)}/{len(records)} checks passed; evidence: {OUT}")
    raise SystemExit(1 if failures else 0)


if __name__ == "__main__":
    main()
