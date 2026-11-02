"""Smoke de um fluxo de upload do aculta_file_uploader no Firefox ESR visível (DISPLAY=:0).

Uso (variáveis; nenhum caminho de máquina fixo):
  SUITE_OUT=<fora do repositório> LOGIN_URL=<link único do drush user:login> \
  BASE=<https://host-de-teste> FORM_PATH=</rota> FLOW=<nome> python3 upload-flow-smoke.py

Confere: campo de arquivo presente, envio de MIME incorreto recusado com mensagem do campo.
Binários são resolvidos pelo PATH (FIREFOX_BIN e GECKODRIVER opcionais).
"""
import json, os, pathlib, shutil, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

OUT = pathlib.Path(os.environ["SUITE_OUT"]) / os.environ.get("FLOW", "fluxo")
OUT.mkdir(parents=True, exist_ok=True)
BASE = os.environ["BASE"]
results = []

def check(name, ok, detail=""):
    results.append({"check": name, "ok": bool(ok), "detail": detail})

firefox = os.environ.get("FIREFOX_BIN") or shutil.which("firefox-esr") or shutil.which("firefox")
gecko = os.environ.get("GECKODRIVER") or shutil.which("geckodriver")
if not firefox or not gecko:
    sys.exit("Firefox ou geckodriver não encontrado no PATH")
opts = Options()
opts.binary_location = firefox
opts.add_argument("--width=1280")
opts.add_argument("--height=900")
driver = webdriver.Firefox(options=opts, service=Service(gecko, port=int(os.environ.get("GECKO_PORT", "4447"))))
try:
    driver.get(os.environ["LOGIN_URL"])
    time.sleep(2)
    cookies = driver.get_cookies()
    driver.get(BASE + "/")
    for c in cookies:
        driver.add_cookie({"name": c["name"], "value": c["value"], "path": "/", "secure": c.get("secure", True)})
    driver.get(BASE + os.environ["FORM_PATH"])
    time.sleep(3)
    driver.save_screenshot(str(OUT / "01-pagina.png"))
    inputs = driver.find_elements(By.CSS_SELECTOR, "input[type=file]")
    check("campo de arquivo presente", len(inputs) > 0, str(len(inputs)))
    if inputs:
        send = os.environ.get("SEND_FILE")
        if send:
            inputs[0].send_keys(send)
        else:
            bad = OUT / "teste-invalido.exe"
            bad.write_bytes(b"MZ" + b"\0" * 64)
            inputs[0].send_keys(str(bad))
        time.sleep(4)
        driver.save_screenshot(str(OUT / "02-mime-incorreto.png"))
        errors = driver.find_elements(By.CSS_SELECTOR, ".messages--error, .form-item--error-message, .file-upload-error")
        text = " | ".join(e.text for e in errors if e.text.strip())
        check("MIME incorreto recusado", bool(text), text[:300])
finally:
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    driver.quit()
print(json.dumps(results, ensure_ascii=False, indent=2))
sys.exit(0 if results and all(r["ok"] for r in results) else 1)
