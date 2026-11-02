"""Smoke do aculta_file_uploader no Firefox ESR visível (DISPLAY=:0, geckodriver).

Uso: SUITE_OUT=/fora/do/repo LOGIN_URL='<link único do drush user:login>' python3 dropzone-smoke.py
Confere a zona de arrastar (borda tracejada, .is-dragover ao arrastar, área de status)
e a rejeição de MIME incorreto. Não usa headless.
"""
import json, os, pathlib, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

OUT = pathlib.Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
BASE = os.environ.get("BASE", "https://aculta.toca.net.br")
FORMAT = os.environ.get("FILTER_FORMAT", "basic_html")
results = []

def check(name, ok, detail=""):
    results.append({"check": name, "ok": bool(ok), "detail": detail})

opts = Options()
opts.add_argument("--width=1280")
opts.add_argument("--height=900")
opts.binary_location = os.environ.get('FIREFOX_BIN', '/usr/bin/firefox-esr')
driver = webdriver.Firefox(options=opts, service=Service(os.environ.get('GECKODRIVER', '/usr/local/bin/geckodriver'), port=int(os.environ.get('GECKO_PORT', '4445'))))
try:
    # Login de teste pelo host da conta; a sessão é copiada para o host principal.
    driver.get(os.environ["LOGIN_URL"])
    time.sleep(2)
    cookies = driver.get_cookies()
    driver.get(BASE + "/")
    for c in cookies:
        driver.add_cookie({"name": c["name"], "value": c["value"], "path": c.get("path", "/"), "secure": c.get("secure", True)})
    driver.get(f"{BASE}/forum/anexos/adicionar/{FORMAT}")
    time.sleep(2)
    driver.save_screenshot(str(OUT / "01-dialogo.png"))
    zones = driver.find_elements(By.CSS_SELECTOR, "[data-aculta-dropzone]")
    check("zona de arrastar presente", len(zones) > 0, f"{len(zones)} elemento(s)")
    if zones:
        zone = zones[0]
        inner = zone.find_element(By.CSS_SELECTOR, ".aculta-dropzone")
        style = driver.execute_script("return getComputedStyle(arguments[0]).borderTopStyle;", inner)
        check("borda tracejada", style == "dashed", style)
        driver.execute_script("arguments[0].dispatchEvent(new DragEvent('dragenter', {bubbles:true}));", zone)
        time.sleep(0.3)
        check("is-dragover ao arrastar", "is-dragover" in zone.get_attribute("class"), zone.get_attribute("class"))
        driver.save_screenshot(str(OUT / "02-dragover.png"))
        driver.execute_script("arguments[0].dispatchEvent(new DragEvent('dragleave', {bubbles:true}));", zone)
        status = driver.find_elements(By.CSS_SELECTOR, "[data-aculta-dropzone] .aculta-dropzone__status")
        check("área de status presente", len(status) > 0)
    inputs = driver.find_elements(By.CSS_SELECTOR, "input[type=file]")
    check("campo de arquivo presente", len(inputs) > 0, f"{len(inputs)}")
    if inputs:
        bad = OUT / "teste-invalido.exe"
        bad.write_bytes(b"MZ" + b"\0" * 64)
        inputs[0].send_keys(str(bad))
        time.sleep(3)
        driver.save_screenshot(str(OUT / "03-mime-incorreto.png"))
        errors = driver.find_elements(By.CSS_SELECTOR, ".messages--error, .form-item--error-message, .file-upload-error")
        err_text = " | ".join(e.text for e in errors if e.text.strip())
        check("MIME incorreto recusado (mensagem de erro do campo)", len(errors) > 0 and bool(err_text), err_text[:300])
finally:
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    driver.quit()
print(json.dumps(results, ensure_ascii=False, indent=2))
sys.exit(0 if all(r["ok"] for r in results) else 1)
