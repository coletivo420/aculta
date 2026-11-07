#!/usr/bin/env python3
"""Captura visual do Growroom420 no Firefox ESR visível (DISPLAY=:0), via geckodriver.

Usa só a biblioteca padrão: fala com o geckodriver pelo protocolo W3C. Não usa headless.

Uso: DISPLAY=:0 python3 capture_gecko.py <pasta_de_saida>

O host público não resolve por DNS neste ambiente. Por isso o Firefox é configurado
com network.dns.localDomains, que mapeia o domínio para o loopback, onde o Apache responde.
"""
import base64
import json
import os
import subprocess
import sys
import time
import urllib.request

HOST = os.environ.get("ACULTA_BROWSER_HOST", "growroom420.aculta.toca.net.br")
ORIGIN = f"https://{HOST}"
PORT = 4444
OUT = sys.argv[1] if len(sys.argv) > 1 else "."

PAGES = [
    ("home", "/"),
    ("categorias", "/categorias"),
    ("pesquisar", "/pesquisar"),
]
VIEWPORTS = [(390, 844), (1440, 900)]
THEMES = ["light", "dark"]


def call(method, path, body=None):
    url = f"http://127.0.0.1:{PORT}{path}"
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method, headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode())


def main():
    os.makedirs(OUT, exist_ok=True)
    driver = subprocess.Popen(["geckodriver", "--port", str(PORT)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:
                call("GET", "/status")
                break
            except Exception:
                time.sleep(0.2)
        caps = {
            "capabilities": {
                "alwaysMatch": {
                    "browserName": "firefox",
                    "acceptInsecureCerts": True,
                    "moz:firefoxOptions": {
                        "binary": "/usr/bin/firefox-esr",
                        "prefs": {"network.dns.localDomains": HOST},
                    },
                }
            }
        }
        sid = call("POST", "/session", caps)["value"]["sessionId"]
        base = f"/session/{sid}"
        index = []
        for vw, vh in VIEWPORTS:
            call("POST", f"{base}/window/rect", {"width": vw, "height": vh})
            for theme in THEMES:
                for name, path in PAGES:
                    call("POST", f"{base}/url", {"url": ORIGIN + path})
                    # Define o modo de cor pela mesma chave que o Portal usa para o tema.
                    call("POST", f"{base}/execute/sync", {
                        "script": "document.documentElement.dataset.theme = arguments[0];",
                        "args": [theme],
                    })
                    time.sleep(1.5)
                    shot = call("GET", f"{base}/screenshot")["value"]
                    fname = f"{name}-{vw}-{theme}.png"
                    with open(os.path.join(OUT, fname), "wb") as fh:
                        fh.write(base64.b64decode(shot))
                    overflow = call("POST", f"{base}/execute/sync", {
                        "script": "return document.documentElement.scrollWidth > document.documentElement.clientWidth;",
                        "args": [],
                    })["value"]
                    index.append({"arquivo": fname, "pagina": path, "viewport": vw, "tema": theme,
                                  "rolagem_horizontal": bool(overflow), "host": HOST})
        call("DELETE", base)
        with open(os.path.join(OUT, "manifesto.json"), "w", encoding="utf-8") as fh:
            json.dump(index, fh, ensure_ascii=False, indent=2)
        print(json.dumps(index, ensure_ascii=False, indent=2))
    finally:
        driver.terminate()


if __name__ == "__main__":
    main()
