import os, sys, time
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from wdlib import Driver, WDError

USER = os.environ["TESTE_COMUM_USER"]
PASS = os.environ["TESTE_COMUM_PASSWORD"]
EV = os.environ.get("SUITE_OUT", "/tmp/aculta-cpf-checkout")
os.makedirs(EV, exist_ok=True)
D = Driver()
try:
    print("== login")
    D.get("https://conta.aculta.toca.net.br/entrar")
    print("url:", D.url(), "| titulo:", D.title())
    inputs = D.js("return [...document.querySelectorAll('input')].map(i=>({id:i.id,name:i.name,type:i.type}))")
    for i in inputs:
        print("  input", i)

    def try_fill(selectors, value):
        for s in selectors:
            if D.exists(s):
                D.type(s, value)
                return s
        return None

    u = try_fill(["#edit-name", "input[name=name]", "#edit-login-returning-customer-name"], USER)
    p = try_fill(["#edit-pass", "input[type=password]", "#edit-login-returning-customer-password"], PASS)
    print("seletor usuario:", u, "| seletor senha:", "achado" if p else None)
    btns = D.js("return [...document.querySelectorAll('button, input[type=submit]')].map(b=>({id:b.id,name:b.name,type:b.type||'button',text:(b.innerText||b.value||'').trim().slice(0,60)}))")
    for b in btns:
        print("  botao", b)
    if D.exists("#edit-submit"):
        D.click("#edit-submit")
    elif D.exists("input[type=submit]"):
        D.click("input[type=submit]")
    else:
        D.submit("input[type=password]")

    for _ in range(40):
        time.sleep(0.5)
        if "/entrar" not in D.url():
            break
    print("apos login:", D.url(), "| titulo:", D.title())
    body = D.js("return document.body.innerText.slice(0,400)")
    print("texto inicial:", " | ".join(x.strip() for x in body.splitlines() if x.strip())[:300])

    print("== pagina de dados")
    D.get("https://conta.aculta.toca.net.br/dados")
    print("url:", D.url(), "| titulo:", D.title())
    txt = D.js("return document.body.innerText.slice(0,500)")
    print("contem 'Sair':", "Sair" in txt, "| contem 'teste':", "teste" in txt.lower())
    D.js("return document.title")
    with open(f"{EV}/ev_login.txt", "w") as f:
        f.write(D.source())
    D.get("https://conta.aculta.toca.net.br/dados")
    png = D.req("GET", f"/session/{D.session}/screenshot")
    import base64
    open(f"{EV}/ev_login.png", "wb").write(base64.b64decode(png))

    print("== produto da loja")
    D.get("https://loja.aculta.toca.net.br/loja/produto/bloco-sativa/camiseta-abada-bloco-sativa-2026-regata")
    print("url:", D.url(), "| titulo:", D.title())
    forms = D.js("""return [...document.querySelectorAll('form')].map(f=>({id:f.id,action:f.getAttribute('action'),cls:f.className.slice(0,60)}))""")
    for f in forms:
        print("  form", f)
    sel = D.js("""return [...document.querySelectorAll('select')].map(s=>({id:s.id,name:s.name,opts:[...s.options].map(o=>o.text.trim()).slice(0,12)}))""")
    for s in sel:
        print("  select", s)
    btns = D.js("""return [...document.querySelectorAll('button, input[type=submit]')].map(b=>({id:b.id,name:b.name,text:(b.innerText||b.value||'').trim().slice(0,60)}))""")
    for b in btns:
        print("  botao", b)
    png = D.req("GET", f"/session/{D.session}/screenshot")
    open(f"{EV}/ev_produto.png", "wb").write(base64.b64decode(png))
    open(f"{EV}/ev_produto.html", "w").write(D.source())
finally:
    D.close()
