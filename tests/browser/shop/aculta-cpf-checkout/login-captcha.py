import os, sys, time, base64
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from wdlib import Driver, WDError
EKEY = "element-6066-11e4-a52e-4f735466cecf"
USER = os.environ["TESTE_COMUM_USER"]
PASS = os.environ["TESTE_COMUM_PASSWORD"]
EV = os.environ.get("SUITE_OUT", "/tmp/aculta-cpf-checkout")
os.makedirs(EV, exist_ok=True)
D = Driver()
S = lambda: f"/session/{D.session}"

def toklen():
    return D.js("var e=document.querySelector('[name=cf-turnstile-response]'); return e? e.value.length : -1")

try:
    D.get("https://conta.aculta.toca.net.br/entrar")
    for txt in ("Aceitar",):
        try:
            el = D.js("""var b=[...document.querySelectorAll('button')].find(x=>x.innerText.trim()==='%s'); if(b){b.click();return true} return false""" % txt)
            print("banner", txt, el)
        except WDError as e:
            print("banner err", e)
    print("token inicial:", toklen())
    print("iframes:", D.js("return [...document.querySelectorAll('iframe')].map(f=>f.src)"))

    for attempt in range(3):
        time.sleep(4)
        n = toklen()
        print(f"token apos {4*(attempt+1)}s:", n)
        if n > 0:
            break
        # tenta interagir com iframe do turnstile
        try:
            ifr = D.js("""var f=document.querySelector('iframe[src*="challenges.cloudflare.com"]'); return f? true:false""")
            print("  iframe turnstile:", ifr)
            if ifr:
                el = D.find('iframe[src*="challenges.cloudflare.com"]')
                D.req("POST", S() + "/frame", {"id": {EKEY: el[EKEY]}})
                inner = D.js("return document.body.innerText.slice(0,200)")
                print("  dentro do iframe:", " ".join(inner.split())[:120])
                clicked = D.js("""var c=document.querySelector('input[type=checkbox], label, .cb-c, #challenge-stage'); if(c){c.click();return c.tagName+':'+(c.className||'')} return null""")
                print("  clique interno:", clicked)
                D.req("POST", S() + "/frame", {"id": None})
        except WDError as e:
            print("  iframe erro:", e)
            try:
                D.req("POST", S() + "/frame", {"id": None})
            except WDError:
                pass

    print("token final:", toklen())
    png = D.req("GET", S() + "/screenshot")
    open(f"{EV}/ev_captcha.png", "wb").write(base64.b64decode(png))

    if toklen() > 0:
        D.type("#edit-name", USER)
        D.type("#edit-pass", PASS)
        D.click("#edit-submit")
        for _ in range(40):
            time.sleep(0.5)
            if "/entrar" not in D.url():
                break
        print("apos login:", D.url(), "|", D.title())
        print("texto:", " ".join(D.js("return document.body.innerText.slice(0,300)").split())[:200])
    else:
        print("SEM_TOKEN: captcha nao resolvido")
finally:
    D.close()
