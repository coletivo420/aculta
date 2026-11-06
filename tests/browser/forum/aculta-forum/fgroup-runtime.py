import base64
import json
import os
import sys
import time

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ["SUITE_BASE"].rstrip("/")
OUT = os.environ["SUITE_OUT"]
TOPIC_URL = os.environ.get("SUITE_TOPIC_URL", f"{BASE}/node/534")
os.makedirs(OUT, mode=0o700, exist_ok=True)

results = {}
for label, env_name in (
    ("ativo_membro", "SUITE_LOGIN_ATIVO"),
    ("suspenso_membro", "SUITE_LOGIN_SUSPENSO"),
):
    driver = Driver()
    try:
        driver.get(os.environ[env_name])
        time.sleep(2)
        login_url = driver.url()
        login_title = driver.title() or ""
        login_html = driver.js("return document.documentElement.outerHTML;") or ""
        driver.get(TOPIC_URL)
        time.sleep(1)
        html = driver.js("return document.documentElement.outerHTML;") or ""
        title = driver.title() or ""
        result = {
            "login_url": login_url,
            "login_title": login_title,
            "logged_in": "/user/logout" in login_html or "Sair" in login_html,
            "title": title,
            "has_topic_title": "TESTE F-GROUP" in html,
            "denied": "403" in title or "Acesso negado" in html,
            "uid_is_expected": f"/{label}/" not in login_url,
        }
        png = driver.req("GET", f"/session/{driver.session}/screenshot")
        path = os.path.join(OUT, f"fgroup-{label}.png")
        with open(path, "wb") as handle:
            handle.write(base64.b64decode(png))
        os.chmod(path, 0o600)
        result["screenshot"] = os.path.basename(path)

        driver.get(f"{BASE}/")
        time.sleep(1)
        listing_html = driver.js("return document.documentElement.outerHTML;") or ""
        result["listing_has_topic"] = "TESTE F-GROUP" in listing_html
        results[label] = result
    finally:
        driver.close()
        try:
            driver.proc.terminate()
        except Exception:
            pass

with open(os.path.join(OUT, "results.json"), "w", encoding="utf-8") as handle:
    json.dump(results, handle, ensure_ascii=False, indent=2)
    handle.flush()
    os.chmod(handle.name, 0o600)
print(json.dumps(results, ensure_ascii=False, indent=2))
