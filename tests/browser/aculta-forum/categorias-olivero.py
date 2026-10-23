"""Revisão somente leitura de /categorias com o tema Olivero no ambiente local.

O Apache local responde pelos mesmos hostnames do servidor de testes; o perfil
do Firefox resolve esses nomes para 127.0.0.1 via network.dns.localDomains.
O script não troca o tema, não autentica e não envia formulários: ele apenas
mede a página e salva capturas. Ative o Olivero antes e restaure com trap.

O driver é reaproveitado de tests/browser/aculta-cpf-checkout/wdlib.py, que já
implementa a sessão WebDriver visível em X11.
"""

import base64
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(HERE), "aculta-cpf-checkout"))
from wdlib import Driver, WDError  # noqa: E402

PAGE = "https://growroom420.aculta.toca.net.br/categorias"
OUT = os.environ.get("SUITE_OUT")
DISPLAY = os.environ.get("DISPLAY")
if not OUT or not os.path.isabs(OUT) or not DISPLAY:
    raise SystemExit("Defina DISPLAY da sessão X11 e SUITE_OUT absoluto fora do checkout.")
os.umask(0o077)
os.makedirs(OUT, exist_ok=True)

PREFS = {"network.dns.localDomains": "aculta.toca.net.br,growroom420.aculta.toca.net.br"}
checks = []


def check(name, passed, detail=""):
    checks.append({"name": name, "pass": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


def screenshot(driver, name):
    png = driver.req("GET", f"/session/{driver.session}/screenshot")
    path = os.path.join(OUT, name)
    with open(path, "wb") as handle:
        handle.write(base64.b64decode(png))
    os.chmod(path, 0o600)


driver = Driver(prefs=PREFS)
try:
    driver.get(PAGE)
    source = driver.source()
    state = driver.js("""return {
      host: location.hostname,
      path: location.pathname,
      title: document.title,
      h1: [...document.querySelectorAll('h1')].map(e => e.innerText.trim()),
      main: !!document.querySelector('main, [role="main"]'),
      categories: document.querySelectorAll('.aculta-forum-category').length,
      rows: document.querySelectorAll('.view-aculta-forum-categories .views-row').length,
      links: [...document.querySelectorAll('a[href*="categoria="]')].length,
      errors: [...document.querySelectorAll('.messages--error, [role="alert"]')]
        .map(e => e.innerText.trim()).filter(Boolean).slice(0, 5),
      overflow: document.documentElement.scrollWidth - innerWidth,
    }""")
    markers = {
        "olivero_assets": len(re.findall(r"/core/themes/olivero/", source)),
        "aculta420_assets": len(re.findall(r"/themes/custom/aculta420/", source)),
    }
    detail = json.dumps({**state, **markers}, ensure_ascii=False)

    check("Página /categorias abriu no host local simulado", state["host"] == "growroom420.aculta.toca.net.br" and state["path"] == "/categorias", detail)
    check("Título principal e região de conteúdo presentes", state["h1"] == ["Categorias"] and state["main"], detail)
    check("Olivero está ativo e o tema ACULTA420 não é carregado", markers["olivero_assets"] > 0 and markers["aculta420_assets"] == 0, detail)
    check("Categorias continuam renderizando sem o tema ACULTA420", state["links"] > 0 and (state["categories"] + state["rows"]) > 0, detail)
    check("Nenhum erro visível na página", not state["errors"] and "The website encountered an unexpected error" not in driver.source(), detail)
    check("Sem overflow horizontal no viewport atual", state["overflow"] <= 1, detail)

    # O Firefox impõe uma largura mínima de janela; o segundo caso registra a
    # largura real entregue em vez de fingir 390 CSS px.
    for width, label in ((1024, "1024px"), (390, "estreito")):
        driver.req("POST", f"/session/{driver.session}/window/rect", {"width": width, "height": 844})
        metrics = driver.js("""return {
          innerWidth,
          document: document.documentElement.scrollWidth,
          links: [...document.querySelectorAll('a[href*="categoria="]')].length,
        }""")
        check(f"Viewport {label} sem overflow horizontal",
              metrics["document"] <= metrics["innerWidth"] + 1 and metrics["links"] > 0,
              json.dumps(metrics))
        screenshot(driver, f"forum-categorias-olivero-{label}.png")
finally:
    driver.close()
    passed = sum(1 for item in checks if item["pass"])
    with open(os.path.join(OUT, "results.json"), "w", encoding="utf-8") as handle:
        json.dump({"page": PAGE, "display": DISPLAY, "checks": checks}, handle, ensure_ascii=False, indent=2)
    print(f"Resultado: {passed}/{len(checks)} verificações; capturas em {OUT}")
    if passed != len(checks):
        sys.exit(1)
