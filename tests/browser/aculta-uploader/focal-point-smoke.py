"""Smoke do ponto focal de imagem (Firefox ESR visível, DISPLAY=:0). Não salva o formulário.

Uso: SUITE_OUT=<fora do repo> LOGIN_URL=<link do drush user:login> BASE=<host> EDIT_PATH=/node/<id>/edit
Confere: contêiner [data-aculta-focal] presente, clique move o marcador e atualiza focal_x/focal_y,
e setas movem 1% (teclado).
"""
import json, os, pathlib, shutil, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

OUT = pathlib.Path(os.environ["SUITE_OUT"]) / "focal"
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
driver = webdriver.Firefox(options=opts, service=Service(gecko, port=int(os.environ.get("GECKO_PORT", "4449"))))
try:
    driver.get(os.environ["LOGIN_URL"])
    time.sleep(2)
    cookies = driver.get_cookies()
    driver.get(BASE + "/")
    for c in cookies:
        driver.add_cookie({"name": c["name"], "value": c["value"], "path": "/", "secure": c.get("secure", True)})
    driver.get(BASE + os.environ["EDIT_PATH"])
    time.sleep(4)
    cont = driver.find_elements(By.CSS_SELECTOR, "[data-aculta-focal]")
    check("contêiner de ponto focal presente", len(cont) > 0, str(len(cont)))
    if cont:
        c = cont[0]
        # Abre ancestrais fechados (details) e ativa a aba que contém o contêiner.
        driver.execute_script("""
          let el = arguments[0];
          while (el) {
            if (el.tagName === 'DETAILS') el.open = true;
            if (el.getAttribute && el.getAttribute('role') === 'tabpanel') {
              const id = el.id; const tab = document.querySelector('[aria-controls="' + id + '"]');
              if (tab) tab.click();
            }
            el = el.parentElement;
          }
        """, c)
        time.sleep(1)
        driver.execute_script("arguments[0].scrollIntoView({block:'center'});", c)
        time.sleep(1)
        fx = lambda: driver.execute_script("return (document.querySelector('[data-focal-x]')||{}).value||''")
        fy = lambda: driver.execute_script("return (document.querySelector('[data-focal-y]')||{}).value||''")
        x0, y0 = fx(), fy()
        ActionChains(driver).move_to_element_with_offset(c, 10, 10).click().perform()
        time.sleep(0.5)
        x1, y1 = fx(), fy()
        check("clique altera focal_x/focal_y", (x1, y1) != (x0, y0), f"{x0},{y0} -> {x1},{y1}")
        driver.save_screenshot(str(OUT / "01-clique.png"))
        c.send_keys(Keys.ARROW_RIGHT)
        time.sleep(0.4)
        x2 = fx()
        check("seta direita move focal_x (1%)", x2 != x1, f"{x1} -> {x2}")
        driver.save_screenshot(str(OUT / "02-seta.png"))
finally:
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    driver.quit()
print(json.dumps(results, ensure_ascii=False, indent=2))
sys.exit(0 if results and all(r["ok"] for r in results) else 1)
