"""Galeria salva com upload pela biblioteca de mídia (Firefox ESR, DISPLAY=:0).

Uso: SUITE_OUT=<fora do repo> LOGIN_URL=<link drush, uso único> BASE=<host principal>
     FORM_PATH=/painel-administrativo/conteudo/media-gallery/add SEND_FILE=<png válido> TITLE=<título de teste>
Abre o diálogo 'Adicionar mídia' de imagens, envia o arquivo dentro do diálogo, confirma a seleção,
salva a galeria e confere que ela foi criada. A remoção do conteúdo de teste é feita depois, pelo operador.
"""
import json, os, pathlib, shutil, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service
from selenium.webdriver.support.ui import WebDriverWait

OUT = pathlib.Path(os.environ["SUITE_OUT"]) / "gallery-save"
OUT.mkdir(parents=True, exist_ok=True)
BASE = os.environ["BASE"]
results = []

def check(name, ok, detail=""):
    results.append({"check": name, "ok": bool(ok), "detail": str(detail)})

def click_mouse(d, el):
    d.execute_script("arguments[0].scrollIntoView({block:'center'}); arguments[0].dispatchEvent(new MouseEvent('mousedown',{bubbles:true,cancelable:true,view:window})); arguments[0].click();", el)

o = Options()
o.binary_location = os.environ.get("FIREFOX_BIN") or shutil.which("firefox-esr") or shutil.which("firefox")
o.add_argument("--width=1280"); o.add_argument("--height=1000")
d = webdriver.Firefox(options=o, service=Service(os.environ.get("GECKODRIVER") or shutil.which("geckodriver"), port=int(os.environ.get("GECKO_PORT", "4456"))))
try:
    d.get(os.environ["LOGIN_URL"]); time.sleep(2)
    ck = d.get_cookies(); d.get(BASE + "/")
    for c in ck:
        d.add_cookie({"name": c["name"], "value": c["value"], "path": "/", "secure": c.get("secure", True)})
    d.get(BASE + os.environ["FORM_PATH"]); time.sleep(4)
    title = os.environ.get("TITLE", "[TESTE] Galeria salva por upload")
    t = d.find_elements(By.CSS_SELECTOR, "input[name='title[0][value]'], input[name='name[0][value]']")
    check("campo de título presente", len(t) > 0, len(t))
    if t: t[0].send_keys(title)
    btn = d.find_elements(By.CSS_SELECTOR, "input[name='images-media-library-open-button']")
    check("botão 'Adicionar mídia' das imagens presente", len(btn) > 0, len(btn))
    if btn:
        click_mouse(d, btn[0])
        try:
            WebDriverWait(d, 15).until(lambda x: x.find_elements(By.CSS_SELECTOR, ".ui-dialog input[type=file], [role=dialog] input[type=file]"))
        except Exception:
            pass
        dlg = d.find_elements(By.CSS_SELECTOR, ".ui-dialog, [role=dialog]")
        check("diálogo da biblioteca abriu", len(dlg) > 0, len(dlg))
        files = d.find_elements(By.CSS_SELECTOR, ".ui-dialog input[type=file], [role=dialog] input[type=file]")
        check("campo de arquivo no diálogo", len(files) > 0, len(files))
        if files and os.environ.get("SEND_FILE"):
            files[-1].send_keys(os.environ["SEND_FILE"])
            time.sleep(6)
            d.save_screenshot(str(OUT / "01-upload.png"))
            alt = d.find_elements(By.CSS_SELECTOR, ".ui-dialog input[name*='alt'], [role=dialog] input[name*='alt'], .ui-dialog textarea[name*='alt']")
            check("campo de texto alternativo no diálogo", len(alt) > 0, len(alt))
            if alt: alt[0].send_keys("Imagem de teste da galeria")
            save_media = d.find_elements(By.CSS_SELECTOR, ".ui-dialog input[type=submit][value='Salvar'], .ui-dialog button")
            save_media = [b for b in save_media if (b.get_attribute('value') or b.text or '').strip() in ('Salvar','Save')]
            check("botão Salvar do diálogo de mídia", len(save_media) > 0, len(save_media))
            if save_media: click_mouse(d, save_media[0]); time.sleep(6)
            sel = d.find_elements(By.CSS_SELECTOR, ".ui-dialog input[type=submit], .ui-dialog button")
            sel = [b for b in sel if any(w in (b.get_attribute('value') or b.text or '') for w in ('Inserir','Selecion','Insert','Select'))]
            check("botão de inserir/selecionar no diálogo", len(sel) > 0, len(sel))
            if sel: click_mouse(d, sel[0]); time.sleep(5)
    save = d.find_elements(By.CSS_SELECTOR, "input[type=submit][value='Salvar'], input[type=submit][value='Save'], #edit-submit")
    check("botão de salvar presente", len(save) > 0, len(save))
    if save:
        click_mouse(d, save[0]); time.sleep(6)
    d.save_screenshot(str(OUT / "02-salva.png"))
    msg = d.find_elements(By.CSS_SELECTOR, ".messages--status, .messages--error")
    check("mensagem de salvamento", any("foi criad" in m.text or "criado" in m.text or "Salv" in m.text for m in msg), " | ".join(m.text[:90] for m in msg)[:200])
finally:
    (OUT / "resultado.json").write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    d.quit()
print(json.dumps(results, ensure_ascii=False, indent=2))
sys.exit(0 if results and all(r["ok"] for r in results) else 1)
