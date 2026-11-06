"""Smoke da capa da Revista (field_media_image, widget de biblioteca de mídia). Firefox ESR, DISPLAY=:0.

Uso: SUITE_OUT=<fora do repo> LOGIN_URL=<link drush> BASE=<host principal> FORM_PATH=/node/add/revista
Abre o diálogo da biblioteca de mídia, envia MIME incorreto no campo de arquivo do diálogo e confere a recusa.
Não salva o formulário.
"""
import json, os, pathlib, shutil, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

OUT = pathlib.Path(os.environ["SUITE_OUT"]) / "revista-capa"
OUT.mkdir(parents=True, exist_ok=True)
BASE = os.environ["BASE"]
results = []

def check(name, ok, detail=""):
    results.append({"check": name, "ok": bool(ok), "detail": detail})

firefox = os.environ.get("FIREFOX_BIN") or shutil.which("firefox-esr") or shutil.which("firefox")
gecko = os.environ.get("GECKODRIVER") or shutil.which("geckodriver")
opts = Options()
opts.binary_location = firefox
opts.add_argument("--width=1280")
opts.add_argument("--height=1000")
driver = webdriver.Firefox(options=opts, service=Service(gecko, port=int(os.environ.get("GECKO_PORT", "4450"))))
try:
    driver.get(os.environ["LOGIN_URL"])
    time.sleep(2)
    cookies = driver.get_cookies()
    driver.get(BASE + "/")
    for c in cookies:
        driver.add_cookie({"name": c["name"], "value": c["value"], "path": "/", "secure": c.get("secure", True)})
    driver.get(BASE + os.environ["FORM_PATH"])
    time.sleep(4)
    btn = driver.find_elements(By.CSS_SELECTOR, "[data-drupal-selector='edit-field-media-image-open-button'], input[name='field_media_image-media-library-open-button']")
    check("botão de abrir biblioteca da capa presente", len(btn) > 0, str(len(btn)))
    if btn:
        # O botão da biblioteca de mídia abre por mousedown (comportamento do Drupal).
        driver.execute_script("arguments[0].scrollIntoView({block:'center'}); arguments[0].dispatchEvent(new MouseEvent('mousedown',{bubbles:true,cancelable:true,view:window})); arguments[0].click();", btn[0])
        from selenium.webdriver.support.ui import WebDriverWait
        from selenium.webdriver.support import expected_conditions as EC
        try:
            WebDriverWait(driver, 15).until(lambda d: d.find_elements(By.CSS_SELECTOR, ".ui-dialog, .media-library-wrapper--dialog, [role=dialog]"))
        except Exception:
            pass
        time.sleep(2)
        check("diálogo da biblioteca abriu", len(driver.find_elements(By.CSS_SELECTOR, ".ui-dialog, [role=dialog]")) > 0, str(len(driver.find_elements(By.CSS_SELECTOR, ".ui-dialog, [role=dialog]"))))
        driver.save_screenshot(str(OUT / "01-biblioteca.png"))
        dlg = driver.find_elements(By.CSS_SELECTOR, ".ui-dialog, [role=dialog]")
        scope = dlg[-1] if dlg else driver
        inputs = scope.find_elements(By.CSS_SELECTOR, "input[type=file]")
        check("campo de arquivo no diálogo da biblioteca", len(inputs) > 0, str(len(inputs)))
        if inputs:
            bad = OUT / "teste-invalido.exe"
            bad.write_bytes(b"MZ" + b"\0" * 64)
            inputs[-1].send_keys(str(bad))
            time.sleep(4)
            driver.save_screenshot(str(OUT / "02-mime-incorreto.png"))
            errs = driver.find_elements(By.CSS_SELECTOR, ".messages--error, .form-item--error-message, .file-upload-error, [role=alert]")
            text = " | ".join(e.text for e in errs if e.text.strip())
            check("MIME incorreto recusado na capa", bool(text), text[:300])
finally:
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    driver.quit()
print(json.dumps(results, ensure_ascii=False, indent=2))
sys.exit(0 if results and all(r["ok"] for r in results) else 1)
