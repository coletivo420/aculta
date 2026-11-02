"""Smoke do upload de imagem no CKEditor do Wiki (Firefox ESR visível, DISPLAY=:0).

Uso: SUITE_OUT=<fora do repo> LOGIN_URL=<link único do drush user:login> BASE=<host do wiki>
     FORM_PATH=/wesativa/verbetes/novo python3 wiki-editor-smoke.py
Abre o diálogo de imagem do editor, envia um arquivo com MIME incorreto e confere a recusa.
"""
import json, os, pathlib, shutil, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

OUT = pathlib.Path(os.environ["SUITE_OUT"]) / "wiki-editor"
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
opts.add_argument("--height=900")
driver = webdriver.Firefox(options=opts, service=Service(gecko, port=int(os.environ.get("GECKO_PORT", "4448"))))
try:
    driver.get(os.environ["LOGIN_URL"])
    time.sleep(2)
    cookies = driver.get_cookies()
    driver.get(BASE + "/")
    for c in cookies:
        driver.add_cookie({"name": c["name"], "value": c["value"], "path": "/", "secure": c.get("secure", True)})
    driver.get(BASE + os.environ["FORM_PATH"])
    time.sleep(4)
    # Botão de imagem do CKEditor: procura pelo rótulo/tooltip em português.
    clicked = driver.execute_script("""
      const btns = [...document.querySelectorAll('button, .ck-button')];
      const b = btns.find(x => /imagem|image/i.test((x.getAttribute('aria-label')||'') + ' ' + (x.getAttribute('title')||'') + ' ' + (x.textContent||'')));
      if (b) { b.click(); return (b.getAttribute('aria-label')||b.getAttribute('title')||b.textContent||'').trim(); }
      return null;
    """)
    check("botão de imagem do editor encontrado", clicked is not None, str(clicked))
    time.sleep(2)
    driver.save_screenshot(str(OUT / "01-dialogo.png"))
    inputs = driver.find_elements(By.CSS_SELECTOR, "input[type=file]")
    check("campo de arquivo no diálogo do editor", len(inputs) > 0, str(len(inputs)))
    if inputs:
        bad = OUT / "teste-invalido.exe"
        bad.write_bytes(b"MZ" + b"\0" * 64)
        inputs[0].send_keys(str(bad))
        time.sleep(4)
        driver.save_screenshot(str(OUT / "02-mime-incorreto.png"))
        msgs = driver.find_elements(By.CSS_SELECTOR, ".ck-notification, .messages--error, .form-item--error-message, .ck-file-dialog-button, .ck-error")
        text = " | ".join(m.text for m in msgs if m.text.strip())
        check("MIME incorreto recusado no editor", bool(text), text[:300])
finally:
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    driver.quit()
print(json.dumps(results, ensure_ascii=False, indent=2))
sys.exit(0 if results and all(r["ok"] for r in results) else 1)
