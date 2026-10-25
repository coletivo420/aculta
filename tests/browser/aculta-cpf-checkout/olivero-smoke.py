"""Smoke test do tema Olivero nas páginas públicas do Portal, Revista e Fórum.

Roda contra o ambiente LOCAL (Apache em 127.0.0.1), usando os mesmos
hostnames do servidor de testes resolvidos para 127.0.0.1 no perfil do
Firefox via network.dns.localDomains. O servidor de testes não é tocado.

Pré-requisito: o tema Olivero precisa estar como `system.theme.default`
no ambiente local (ver README.md desta pasta, que traz o comando com
restauração garantida).

Não autentica e não envia formulários. Saída: SUITE_OUT (padrão
/tmp/aculta-olivero), com um PNG por página.
"""

import base64
import os
import re
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from wdlib import Driver, WDError  # noqa: E402

EV = os.environ.get("SUITE_OUT", "/tmp/aculta-olivero")
os.makedirs(EV, exist_ok=True)

HOSTS = ",".join([
    "aculta.toca.net.br",
    "conta.aculta.toca.net.br",
    "revista420.aculta.toca.net.br",
    "growroom420.aculta.toca.net.br",
    "cursos.aculta.toca.net.br",
    "loja.aculta.toca.net.br",
])
PREFS = {"network.dns.localDomains": HOSTS}

PAGES = [
    ("portal_home", "https://aculta.toca.net.br/"),
    ("forum_discussoes", "https://growroom420.aculta.toca.net.br/"),
    ("revista", "https://revista420.aculta.toca.net.br/"),
]

D = Driver(prefs=PREFS)
S = lambda: f"/session/{D.session}"


def shot(name):
    png = D.req("GET", S() + "/screenshot")
    with open(os.path.join(EV, name + ".png"), "wb") as f:
        f.write(base64.b64decode(png))


def main_text(n=600):
    t = D.js("var m=document.querySelector('main')||document.querySelector('#main-content')||document.body; return m.innerText")
    return " ".join(t.split())[:n]


def theme_markers():
    src = D.source()
    return {
        "olivero_assets": len(re.findall(r"/core/themes/olivero/", src)),
        "aculta420_assets": len(re.findall(r"/themes/custom/aculta420/", src)),
        "body_class": D.js("return document.body.className")[:120],
    }


def errors():
    return D.js("""return [...document.querySelectorAll('.messages--error, [role=alert]')]
      .map(e=>e.innerText.trim().slice(0,160)).filter(Boolean).slice(0,5)""")


try:
    for name, url in PAGES:
        print(f"== {name}: {url}")
        try:
            D.get(url)
        except WDError as e:
            print("   NAVEGACAO FALHOU:", e)
            continue
        time.sleep(2)
        print("   url:", D.url())
        print("   titulo:", D.title())
        print("   tema:", theme_markers())
        print("   erros visiveis:", errors())
        print("   main:", main_text())
        if name == "forum_discussoes":
            print("   blocos:", D.js("""return [...document.querySelectorAll('main h1, main h2, main .views-row, main article')]
              .map(e=>e.innerText.trim().split('\\n')[0].slice(0,80)).filter(Boolean).slice(0,10)"""))
        if name == "revista":
            print("   blocos:", D.js("""return [...document.querySelectorAll('main h1, main h2, main h3, main article, main .views-row')]
              .map(e=>e.innerText.trim().split('\\n')[0].slice(0,80)).filter(Boolean).slice(0,10)"""))
        shot("olivero_" + name)
finally:
    D.close()
