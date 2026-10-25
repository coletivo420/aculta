"""Múltiplas abas, carrinho/checkout compartilhados e LMS, no servidor de testes.

Pré-requisitos (ver README.md desta pasta):
  - Firefox ESR visível em DISPLAY=:0 e geckodriver no PATH;
  - TESTE_COMUM_USER e TESTE_COMUM_PASSWORD no ambiente, carregados de
    secrets/teste_comum.env (nunca impressos por este script).

O script não conclui pagamento. No servidor de testes o gateway está
desabilitado, então o checkout para em "informacoes-do-pedido" com a
mensagem de gateway indisponível; isso é registrado, não contornado.

Saída: SUITE_OUT (padrão /tmp/aculta-cpf-checkout), com PNGs e HTML.
"""

import base64
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from wdlib import Driver, WDError  # noqa: E402

USER = os.environ["TESTE_COMUM_USER"]
PASS = os.environ["TESTE_COMUM_PASSWORD"]
EV = os.environ.get("SUITE_OUT", "/tmp/aculta-cpf-checkout")
os.makedirs(EV, exist_ok=True)
D = Driver()


def path():
    return f"/session/{D.session}"


def shot(name):
    png = D.req("GET", path() + "/screenshot")
    with open(os.path.join(EV, name + ".png"), "wb") as f:
        f.write(base64.b64decode(png))


def main_text(n=400):
    text = D.js("var m=document.querySelector('main')||document.body; return m.innerText")
    return " ".join(text.split())[:n]


def login():
    D.get("https://conta.aculta.toca.net.br/entrar")
    for _ in range(15):
        time.sleep(1)
        if D.js("var e=document.querySelector('[name=cf-turnstile-response]'); return e? e.value.length : 0") > 0:
            break
    D.type("#edit-name", USER)
    D.type("#edit-pass", PASS)
    D.click("#edit-submit")
    for _ in range(40):
        time.sleep(0.5)
        if "/entrar" not in D.url():
            break


def session_cookies():
    out = []
    for c in D.req("GET", path() + "/cookie"):
        expiry = c.get("expiry")
        iso = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(expiry)) if expiry else "sessao"
        out.append({
            "name": c["name"],
            "expiry": iso,
            "secure": c.get("secure"),
            "httpOnly": c.get("httpOnly"),
        })
    return out


def visible_fields():
    return D.js("""return [...document.querySelectorAll('main input, main select, main textarea')]
      .filter(e=>e.type!='hidden' && e.offsetParent!==null)
      .map(e=>({id:e.id||'',name:e.name||'',type:e.type||e.tagName,
                val:e.type==='password'?'<oculto>':(e.value||''),ph:e.placeholder||''}))""")


def fiscal_fields():
    keys = ("cpf", "cnpj", "documento", "fiscal", "associado")
    return [c for c in visible_fields()
            if any(k in (c["id"] + c["name"] + c["ph"]).lower() for k in keys)]


try:
    login()
    print("login:", D.url(), "|", D.title())
    for c in session_cookies():
        print("cookie:", c)

    tab_a = D.req("GET", path() + "/window")

    print("== aba A: carrinho autenticado")
    D.get("https://aculta.toca.net.br/carrinho")
    print("A:", D.url(), "| autenticado:", USER in D.js("return document.body.innerText"))
    print("A texto:", main_text(220))
    shot("ev_mt_cart_A")

    print("== aba B: checkout do mesmo pedido")
    tab_b = D.new_tab()["handle"]
    D.switch(tab_b)
    D.get("https://aculta.toca.net.br/carrinho/finalizar")
    time.sleep(2)
    for step in range(2):
        print(f"-- passo {step + 1}: {D.url()} | {D.title()}")
        print("   texto:", main_text(350))
        print("   campos fiscais:", len(fiscal_fields()))
        alert = D.js("""var a=document.querySelector('main .alert-danger, main [role=alert]');
                        return a? a.innerText.trim().slice(0,200):null""")
        print("   alerta:", alert)
        shot(f"ev_mt_checkout_passo_{step + 1}")
        if step == 0:
            with open(os.path.join(EV, "ev_mt_checkout_passo_1.html"), "w") as f:
                f.write(D.source())
        if not D.js("return document.querySelector('#edit-actions-next')?true:false"):
            break
        D.js("var b=document.querySelector('main .btn-close'); if(b)b.click();")
        D.js("document.querySelector('#edit-actions-next').click()")
        time.sleep(4)

    print("== aba A: carrinho depois do checkout na aba B")
    D.switch(tab_a)
    D.get("https://aculta.toca.net.br/carrinho")
    print("A:", D.url(), "| autenticado:", USER in D.js("return document.body.innerText"))
    print("A texto:", main_text(220))
    shot("ev_mt_cart_A_pos")

    print("== aba C: LMS")
    tab_c = D.new_tab()["handle"]
    D.switch(tab_c)
    D.get("https://cursos.aculta.toca.net.br/group/1")
    time.sleep(2)
    print("C:", D.url(), "|", D.title())
    print("C texto:", main_text(500))
    cta = D.js("""return [...document.querySelectorAll('main a, main button, main input[type=submit]')]
      .filter(e=>e.offsetParent!==null)
      .map(e=>({tag:e.tagName,text:(e.innerText||e.value||'').trim().slice(0,60),href:e.href||''}))
      .filter(x=>/matric|inscrev|particip|comprar|adquirir|acessar|pre[cç]o/i.test(x.text+x.href))""")
    print("C CTAs de matrícula/compra:", cta)
    shot("ev_mt_lms_curso")
finally:
    D.close()
