"""Inspeciona o produto Loja e percorre o checkout sem confirmar pagamento."""

import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(HERE), "aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

PRODUCT_BASE = os.environ.get("ACULTA_PRODUCT_BASE_URL", "https://loja.aculta.toca.net.br")
CHECKOUT_BASE = os.environ.get("ACULTA_CHECKOUT_BASE_URL", "https://aculta.toca.net.br")
PRODUCT = os.environ.get(
    "ACULTA_MP_PRODUCT_PATH",
    "/loja/produto/bloco-sativa/camiseta-abada-bloco-sativa-2026-regata",
)
OUT = os.environ.get("SUITE_OUT", "/tmp/aculta-mercadopago-checkout")
os.makedirs(OUT, exist_ok=True)


def visible_summary(driver):
    return driver.js("""return {
      title: document.title,
      path: location.pathname,
      headings: [...document.querySelectorAll('main h1, main h2')]
        .filter(e=>e.offsetParent!==null).map(e=>e.innerText.trim()).slice(0,8),
      buttons: [...document.querySelectorAll('main button, main input[type=submit], main a')]
        .filter(e=>e.offsetParent!==null)
        .map(e=>({tag:e.tagName,label:(e.innerText||e.value||'').trim().slice(0,80),
                  href:e.tagName==='A'?new URL(e.href).pathname:''})).slice(0,25),
      fields: [...document.querySelectorAll('main input:not([type=hidden]), main select, main textarea')]
        .filter(e=>e.offsetParent!==null)
        .map(e=>({id:e.id||'',name:e.name||'',type:e.type||e.tagName,
                  required:e.required, label:(e.labels?.[0]?.innerText||'').trim().slice(0,80),
                  options:e.tagName==='SELECT'?[...e.options].map(o=>({value:o.value,text:o.text,disabled:o.disabled})):[]}))
        .slice(0,30),
      forms:[...document.querySelectorAll('main form')].map(f=>({id:f.id,form_id:f.querySelector('input[name=form_id]')?.value||''})),
      captcha_elements:[...document.querySelectorAll('main [data-drupal-selector*=captcha], main .captcha')].map(e=>({id:e.id||'',type:e.getAttribute('data-drupal-selector')||'',name:e.getAttribute('name')||''})),
      purchase_captcha:!!document.querySelector('.captcha, [data-drupal-selector*=captcha], [data-sitekey], iframe[src*="challenges.cloudflare.com"], script[src*="challenges.cloudflare.com"]'),
      message:(document.querySelector('main')?.innerText||'').trim().slice(0,600),
      google:!!document.querySelector('main [data-portal-ui="auth-google"]')
    }""")


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("DISPLAY=:0 é obrigatório para Firefox visível.")
    driver = Driver()
    try:
        driver.get(PRODUCT_BASE + PRODUCT)
        time.sleep(2)
        product = visible_summary(driver)
        print("product:", product)
        if product["purchase_captcha"]:
            print("RESULT=captcha_present_on_product")
            return
        with open(os.path.join(OUT, "product.png"), "wb") as image:
            import base64
            image.write(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))

        add_button = driver.js("""return [...document.querySelectorAll('main form button, main form input[type=submit]')]
          .filter(e=>e.offsetParent!==null)
          .map(e=>({tag:e.tagName,id:e.id||'',name:e.name||'',value:e.value||'',
                    label:(e.innerText||e.value||'').trim()}))""")
        print("cart actions:", add_button)
        if not add_button:
            print("RESULT=product_loaded_no_add_to_cart_action")
            return
        result = driver.js("""for (const s of document.querySelectorAll('main select[required]')) {
            const o=[...s.options].find(o=>o.value && !o.disabled);
            if(o){s.value=o.value; s.dispatchEvent(new Event('input',{bubbles:true}));
                  s.dispatchEvent(new Event('change',{bubbles:true}));}
          }
          return [...document.querySelectorAll('main form button, main form input[type=submit]')]
            .some(e=>e.offsetParent!==null && /adicionar ao carrinho/i.test(e.innerText||e.value||''))""")
        if not result:
            print("RESULT=add_to_cart_action_not_found")
            return
        driver.click("#edit-submit")
        time.sleep(3)
        after_add = visible_summary(driver)
        print("after add:", after_add)
        if after_add["purchase_captcha"]:
            print("RESULT=captcha_present_after_add_to_cart")
            return
        with open(os.path.join(OUT, "after-add.png"), "wb") as image:
            import base64
            image.write(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
        driver.get(CHECKOUT_BASE + "/carrinho")
        time.sleep(2)
        cart = visible_summary(driver)
        print("cart:", cart)
        if cart["purchase_captcha"]:
            print("RESULT=captcha_present_on_cart")
            return
        with open(os.path.join(OUT, "cart.png"), "wb") as image:
            import base64
            image.write(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
        if "Camiseta Abadá Bloco Sativa 2026" not in cart["message"]:
            print("RESULT=add_to_cart_not_confirmed")
            return
        if not driver.exists("#edit-checkout"):
            print("RESULT=cart_checkout_button_missing")
            return
        driver.click("#edit-checkout")
        time.sleep(4)
        checkout = visible_summary(driver)
        print("checkout:", checkout)
        if checkout["purchase_captcha"]:
            print("RESULT=captcha_present_on_checkout_identity")
            return
        with open(os.path.join(OUT, "checkout.png"), "wb") as image:
            import base64
            image.write(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
        if checkout["path"].startswith("/finalizar-compra/") and checkout["path"].endswith("/entrar"):
            email = "checkout-smoke-" + str(int(time.time())) + "@example.invalid"
            email_selector = "input[type=email][name*=returning_customer]"
            if driver.exists(email_selector):
                driver.type(email_selector, email)
                driver.js("document.querySelector(arguments[0]).dispatchEvent(new Event('blur',{bubbles:true}))", [email_selector])
                time.sleep(2)
                identity = driver.js("""return {
                  email_stage:!!document.querySelector('#aculta-checkout-identity-stage'),
                  new_customer_visible:!!document.querySelector('#aculta-checkout-new-customer:not([hidden])'),
                  password_visible:!!document.querySelector('#aculta-checkout-identity-stage input[type=password]:not([hidden])'),
                  google_before_email:(()=>{const g=document.querySelector('#aculta-checkout-identity-stage [data-portal-ui="auth-google"]')
                    || document.querySelector('[data-portal-ui="auth-google"]');
                    const e=document.querySelector(arguments[0]);return !!g&&!!e&&!!(g.compareDocumentPosition(e)&Node.DOCUMENT_POSITION_FOLLOWING)})(),
                  captcha:!!document.querySelector('.captcha, [data-drupal-selector*=captcha], [data-sitekey], iframe[src*="challenges.cloudflare.com"], script[src*="challenges.cloudflare.com"]')
                }""", [email_selector])
                print("identity:", identity)
                with open(os.path.join(OUT, "checkout-identity.png"), "wb") as image:
                    import base64
                    image.write(base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot")))
                if identity["new_customer_visible"] and not identity["password_visible"] and not identity["captcha"] and identity["google_before_email"]:
                    continue_new = driver.js("""const b=[...document.querySelectorAll('main button, main input[type=submit]')]
                      .find(e=>/continuar como novo cliente/i.test(e.innerText||e.value||''));
                      if(b){b.click();return true;}return false;""")
                    if continue_new:
                        time.sleep(4)
                        shipping = visible_summary(driver)
                        print("shipping stage:", shipping)
                        if shipping["purchase_captcha"]:
                            print("RESULT=captcha_present_on_address_or_freight")
                        elif shipping["path"].startswith("/finalizar-compra/") and shipping["forms"]:
                            print("RESULT=guest_registration_and_shipping_stage_without_captcha")
                        else:
                            print("RESULT=guest_registration_without_captcha_shipping_stage_not_reached")
                    else:
                        print("RESULT=guest_registration_ajax_without_captcha_continue_missing")
                else:
                    print("RESULT=guest_registration_ajax_incomplete")
            else:
                print("RESULT=checkout_email_field_missing")
        elif checkout["path"].startswith("/finalizar-compra/"):
            print("RESULT=checkout_loaded_before_payment_confirmation")
        else:
            print("RESULT=cart_checkout_button_did_not_advance")
    finally:
        driver.close()


if __name__ == "__main__":
    main()
