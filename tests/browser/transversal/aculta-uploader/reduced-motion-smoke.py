"""Prefers-reduced-motion em tempo de execução (Firefox ESR, DISPLAY=:0).

Uso: SUITE_OUT=<fora do repo> LOGIN_URL=<link drush, de uso único> BASE=<host do fórum> FORM_PATH=/forum/anexos/adicionar/forum
Roda com ui.prefersReducedMotion=1 e confere que a transição da zona de arrastar fica em 0s.
Gere um link novo a cada execução (o link de login é de uso único).
"""
import json, os, pathlib, shutil, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

OUT = pathlib.Path(os.environ["SUITE_OUT"]) / "reduced-motion"
OUT.mkdir(parents=True, exist_ok=True)
o = Options()
o.binary_location = os.environ.get("FIREFOX_BIN") or shutil.which("firefox-esr") or shutil.which("firefox")
o.add_argument("--width=1280")
o.add_argument("--height=1000")
o.set_preference("ui.prefersReducedMotion", 1)
d = webdriver.Firefox(options=o, service=Service(os.environ.get("GECKODRIVER") or shutil.which("geckodriver"), port=int(os.environ.get("GECKO_PORT", "4455"))))
res = []
try:
    d.get(os.environ["LOGIN_URL"])
    time.sleep(2)
    ck = d.get_cookies()
    d.get(os.environ["BASE"] + "/")
    for c in ck:
        d.add_cookie({"name": c["name"], "value": c["value"], "path": "/", "secure": c.get("secure", True)})
    d.get(os.environ["BASE"] + os.environ["FORM_PATH"])
    time.sleep(4)
    media = d.execute_script("return matchMedia('(prefers-reduced-motion: reduce)').matches")
    z = d.find_elements(By.CSS_SELECTOR, ".aculta-dropzone")
    dur = d.execute_script("return getComputedStyle(arguments[0]).transitionDuration", z[0]) if z else None
    res.append({"check": "preferência reduzida ativa", "ok": bool(media), "detail": str(media)})
    res.append({"check": "transição da zona em 0s", "ok": bool(z) and dur in ("0s", "0.0s"), "detail": str(dur)})
    d.save_screenshot(str(OUT / "01-reduzido.png"))
finally:
    (OUT / "resultado.json").write_text(json.dumps(res, ensure_ascii=False, indent=2), encoding="utf-8")
    d.quit()
print(json.dumps(res, ensure_ascii=False, indent=2))
sys.exit(0 if res and all(r["ok"] for r in res) else 1)
