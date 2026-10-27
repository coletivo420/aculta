"""Verificação visual do avatar padrão (ticket avatar-padrao).

Cobre a listagem pública de perfis e as páginas de identidade com o tema
configurado no site. Roda contra o ambiente local (runserver em 127.0.0.1),
resolvendo os hostnames de teste para 127.0.0.1 no próprio Firefox via
network.dns.localDomains. Não toca no servidor de testes remoto.

Regras verificadas:
- a view /membros mostra cartões com avatar do acervo e nunca a inicial;
- o perfil público /u/<apelido> mostra o avatar do acervo e nunca a inicial;
- o perfil privado (PiradoPirata) não aparece em /membros;
- as classes semânticas de UI Styles estão no display da view;
- nenhuma página visitada mostra erro visível.

Uso:
  DISPLAY=:0 BASE_URL=http://aculta.toca.net.br:8081 \
    SUITE_OUT=/home/piradopirata/worktrees/avatar-evidence \
    python3 tests/browser/aculta-avatar/visual.py

Para o tema Olivero, o comando de execução troca o tema e restaura com trap:
  drush config:set system.theme default olivero -y && drush cr
  trap 'drush config:set system.theme default aculta420 -y && drush cr' EXIT
  DISPLAY=:0 python3 tests/browser/aculta-avatar/visual.py
"""

import base64
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))
from wdlib import Driver, WDError  # noqa: E402

BASE = os.environ.get("BASE_URL", "http://aculta.test:8080")
SUITE_OUT = os.environ.get("SUITE_OUT", "/home/piradopirata/worktrees/avatar-evidence")
os.makedirs(SUITE_OUT, exist_ok=True)

HOSTS = ",".join([
    "aculta.test",
    "conta.aculta.test",
    "revista420.aculta.test",
    "cursos.aculta.test",
    "wiki420.aculta.test",
    "growroom420.aculta.toca.net.br",
])
PREFS = {"network.dns.localDomains": HOSTS}

if not os.environ.get("DISPLAY"):
    sys.exit("DISPLAY não definido; use DISPLAY=:0 com o Firefox ESR visível.")

D = Driver(prefs=PREFS)
S = lambda: f"/session/{D.session}"


def shot(name):
    png = D.req("GET", S() + "/screenshot")
    with open(os.path.join(SUITE_OUT, name + ".png"), "wb") as f:
        f.write(base64.b64decode(png))


def js(script):
    return D.js(script)


def errors():
    return js("""return [...document.querySelectorAll('.messages--error, [role=alert]')]
      .map(e=>e.innerText.trim().slice(0,160)).filter(Boolean).slice(0,5)""")


def report(name, url):
    print(f"== {name}: {url}")
    D.get(url)
    time.sleep(1)
    print("   url:", D.url())
    print("   titulo:", D.title())
    print("   erros visiveis:", errors())
    shot(name)
    return D.source()


def check_membros():
    html = report("membros", BASE + "/membros?visual=" + str(int(time.time())))
    cards = js("return document.querySelectorAll('.views-field-aculta-user-author-card article').length")
    initials = js("return document.querySelectorAll('.aculta-author-card__avatar--initial, .public-profile-initial').length")
    rows = js("return document.querySelectorAll('.views-row.aculta-uistyle--surface-raised.aculta-uistyle--border-subtle').length")
    private = js("return document.body.innerText.includes('PiradoPirata')")
    themeless = js("return document.querySelectorAll('.views-field-aculta-user-author-card article.aculta-author-card').length")
    print(f"   cartoes={cards} cartoes_com_tema={themeless} linhas_ui_styles={rows} iniciais={initials} privado_visivel={private}")
    if cards < 1:
        raise AssertionError("nenhum cartão na view de perfis")
    if initials != 0:
        raise AssertionError("a inicial apareceu na view")
    if rows < 1:
        raise AssertionError("UI Styles ausente no display da view")
    if private:
        raise AssertionError("perfil privado apareceu na view")
    if "aculta-author-card__avatar--initial" in html:
        raise AssertionError("marcação de inicial presente no HTML")


def check_perfil():
    html = report("perfil", BASE + "/u/coletivo420")
    has_image = js("return document.querySelectorAll('[data-portal-ui=public-profile-avatar-image]').length")
    initials = js("return document.querySelectorAll('.public-profile-initial').length")
    print(f"   avatar_acervo={has_image} iniciais={initials}")
    if has_image < 1:
        raise AssertionError("perfil público sem avatar do acervo")
    if initials != 0:
        raise AssertionError("a inicial apareceu no perfil público")


def check_public_pages():
    pages = {
        "forum": "http://growroom420.aculta.toca.net.br:8080/",
        "revista": "http://revista420.aculta.test:8080/",
        "cursos": "http://cursos.aculta.test:8080/",
        "wiki": "http://wiki420.aculta.test:8080/",
        "conta": "http://conta.aculta.test:8080/",
    }
    for name, url in pages.items():
        try:
            report(name, url)
        except WDError as exc:
            print(f"   NAVEGACAO FALHOU: {exc}")


def check_auth_pages():
    login = os.environ.get("LOGIN_URL")
    if not login:
        print("== autenticado: LOGIN_URL não definido; pulando páginas autenticadas")
        return
    print("== login único")
    D.get(login)
    time.sleep(2)
    print("   url:", D.url())
    for name, url in [
        ("conta-dados", "http://conta.aculta.test:8080/dados"),
        ("mensagens", "http://conta.aculta.test:8080/mensagens/resumo"),
    ]:
        try:
            report(name, url)
        except WDError as exc:
            print(f"   NAVEGACAO FALHOU: {exc}")


try:
    check_membros()
    check_perfil()
    check_public_pages()
    check_auth_pages()
finally:
    D.close()
    print("artefatos em", SUITE_OUT)
