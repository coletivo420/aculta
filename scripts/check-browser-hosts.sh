#!/usr/bin/env bash
# Verifica, no Firefox ESR visível na sessão X11 local, se as páginas de teste carregam.
# Controlado pelo geckodriver (WebDriver W3C). Não usa Chromium headless.
# Uso: scripts/check-browser-hosts.sh [URL ...]
# Sem argumentos, usa os hosts de teste (*.aculta.toca.net.br) e example.com como controle.
# Saída: tabela no terminal e, por URL, o HTML em OUT_DIR. Código 124 = tempo esgotado.

set -u

export DISPLAY="${DISPLAY:-:0}"
export TIMEOUT="${TIMEOUT:-15}"
export FIREFOX="${FIREFOX:-/usr/bin/firefox-esr}"
export GECKODRIVER="${GECKODRIVER:-geckodriver}"
export OUT_DIR="${OUT_DIR:-/tmp/aculta-browser-check-$(date +%Y%m%d-%H%M%S)}"

if ! command -v "$GECKODRIVER" >/dev/null 2>&1; then
  echo "geckodriver não encontrado: defina GECKODRIVER=/caminho/do/geckodriver" >&2
  exit 2
fi
if [ ! -x "$FIREFOX" ]; then
  echo "Firefox não encontrado: defina FIREFOX=/caminho/do/firefox" >&2
  exit 2
fi
if ! xprop -root -display "$DISPLAY" >/dev/null 2>&1; then
  echo "Sessão X11 $DISPLAY indisponível. Os testes precisam da sessão gráfica local." >&2
  exit 2
fi

exec python3 - "$@" <<'PY'
import json
import os
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

TIMEOUT = int(os.environ["TIMEOUT"])
FIREFOX = os.environ["FIREFOX"]
GECKODRIVER = os.environ["GECKODRIVER"]
OUT_DIR = os.environ["OUT_DIR"]
URLS = sys.argv[1:] or [
    "https://example.com/",
    "https://aculta.toca.net.br/",
    "https://links.aculta.toca.net.br/",
    "https://conta.aculta.toca.net.br/entrar",
    "https://apoio.aculta.toca.net.br/",
]


class ErroWebDriver(Exception):
    def __init__(self, codigo, mensagem):
        super().__init__(mensagem)
        self.codigo = codigo


def porta_livre():
    s = socket.socket()
    s.bind(("127.0.0.1", 0))
    porta = s.getsockname()[1]
    s.close()
    return porta


porta = porta_livre()
base = f"http://127.0.0.1:{porta}"
perfil = tempfile.mkdtemp(prefix="aculta-ff-", dir="/tmp")
driver = subprocess.Popen([GECKODRIVER, "--port", str(porta)],
                          stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def req(metodo, caminho, corpo=None, timeout=60):
    dados = json.dumps(corpo).encode() if corpo is not None else None
    pedido = urllib.request.Request(base + caminho, data=dados, method=metodo,
                                    headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(pedido, timeout=timeout) as resposta:
            return json.loads(resposta.read())["value"]
    except urllib.error.HTTPError as erro:
        valor = json.loads(erro.read())["value"]
        raise ErroWebDriver(valor.get("error", "erro"), valor.get("message", "erro WebDriver")) from erro


os.makedirs(OUT_DIR, exist_ok=True)
sumario = os.path.join(OUT_DIR, "resumo.txt")
sessao = None
linhas = []
try:
    for _ in range(50):
        try:
            req("GET", "/status", timeout=2)
            break
        except (urllib.error.URLError, OSError):
            time.sleep(0.2)
    caps = {"capabilities": {"alwaysMatch": {
        "browserName": "firefox",
        "moz:firefoxOptions": {"binary": FIREFOX, "args": ["-profile", perfil, "-no-remote"]},
    }}}
    sessao = req("POST", "/session", caps, timeout=120)["sessionId"]
    req("POST", f"/session/{sessao}/timeouts", {"pageLoad": TIMEOUT * 1000, "script": TIMEOUT * 1000})

    cabecalho = f"{'URL':<45} {'EXIT':<6} {'BYTES':<8} TITULO"
    print(cabecalho)
    linhas.append(cabecalho)
    for url in URLS:
        safe = "".join(c if c.isalnum() else "_" for c in url)
        html = ""
        codigo = 0
        titulo = "-"
        try:
            req("POST", f"/session/{sessao}/url", {"url": url}, timeout=TIMEOUT + 30)
            html = req("GET", f"/session/{sessao}/source")
            titulo = req("GET", f"/session/{sessao}/title") or "-"
        except ErroWebDriver as erro:
            codigo = 124 if erro.codigo == "timeout" else 1
            html = ""
        with open(os.path.join(OUT_DIR, safe + ".html"), "w", encoding="utf-8") as f:
            f.write(html)
        bytes_ = len(html.encode("utf-8"))
        linha = f"{url:<45} {codigo:<6} {bytes_:<8} {titulo}"
        print(linha)
        linhas.append(linha)
finally:
    if sessao:
        try:
            req("DELETE", f"/session/{sessao}")
        except (ErroWebDriver, urllib.error.URLError, OSError):
            pass
    driver.terminate()
    shutil.rmtree(perfil, ignore_errors=True)
    with open(sumario, "w", encoding="utf-8") as f:
        f.write("\n".join(linhas) + "\n")

print()
print(f"Código 124 = tempo esgotado ({TIMEOUT} s). Código 0 = página carregou no Firefox, sessão {os.environ['DISPLAY']}.")
print(f"Arquivos em: {OUT_DIR}")
PY
