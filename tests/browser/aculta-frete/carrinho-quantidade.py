"""Teste visual do frete no carrinho (ME-0.1-C): cálculo no produto, carrinho e alteração de quantidade.

Fluxo no servidor de testes, com Firefox ESR visível (DISPLAY=:0) e CLIQUES REAIS do WebDriver:
1. Produto (loja.aculta.toca.net.br): digita um CEP válido e espera as opções (sem botão).
2. Adiciona o produto ao carrinho com clique real. O cookie de sessão é compartilhado entre os
   subdomínios (cookie_domain do ambiente), então o carrinho do domínio principal enxerga o item.
3. Carrinho (aculta.toca.net.br/carrinho): item presente e bloco de frete com o último cálculo.
4. Digita quantidade 2 e clica em atualizar. O cálculo guardado é refeito e continua aparecendo.

Cria um item no carrinho do servidor de testes (dado de teste). Não altera tema nem configuração.
Saída: SUITE_OUT (padrão /tmp/aculta-frete).
"""

import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

PRODUTO = "https://loja.aculta.toca.net.br/loja/produto/bloco-sativa/camiseta-abada-bloco-sativa-2026-regata"
CARRINHO = "https://aculta.toca.net.br/carrinho"
PREFS = {"network.dns.localDomains": "loja.aculta.toca.net.br,aculta.toca.net.br"}
CEP = "01310100"
CAMPO_CEP = '[data-aculta-shipping-calculator] input[name="cep"]'
OPCOES = '[data-aculta-shipping-calculator] .aculta-shipping-calculator__opcao'
D = Driver(prefs=PREFS)
R = {}


def espera(cond, timeout=60):
    fim = time.time() + timeout
    while time.time() < fim:
        if D.js(f"return !!({cond});"):
            return True
        time.sleep(0.5)
    return False


def marca(seletor_js, id_marca):
    """Marca o primeiro elemento que casa a condição com um id temporário, para o clique real."""
    return D.js(f"""const e = Array.from(document.querySelectorAll('input,button')).find(e => {seletor_js});
                    if (e) {{ e.id = '{id_marca}'; return true; }} return false;""")


try:
    D.req("POST", f"/session/{D.session}/window/rect", {"width": 1280, "height": 900})
    D.get(PRODUTO)
    espera(f"document.querySelector('{CAMPO_CEP}')")
    D.type(CAMPO_CEP, CEP)  # digitação real: o cálculo dispara sozinho
    R["produto_opcoes"] = espera(f"document.querySelectorAll('{OPCOES}').length > 0", 90)

    D.js("const s = document.querySelector('form[id^=\"commerce-order-item-add-to-cart-form\"] select');"
         "s.value = s.options[1].value; s.dispatchEvent(new Event('change', {bubbles: true}));")
    time.sleep(3)
    D.click("form[id^='commerce-order-item-add-to-cart-form'] input[type=submit]")
    time.sleep(8)
    R["contador_apos_adicionar"] = D.js("const c = document.querySelector('[data-aculta-cart-count]');"
                                        "return c ? parseInt(c.textContent, 10) || 0 : 0;")

    D.get(CARRINHO)
    R["carrinho_item"] = espera("document.body.innerText.includes('Camiseta Abadá Bloco Sativa')", 60)
    R["carrinho_bloco_frete"] = espera("document.querySelector('[data-aculta-shipping-calculator]')", 20)
    R["carrinho_opcoes_do_calculo"] = espera(f"document.querySelectorAll('{OPCOES}').length > 0", 20)

    # Altera a quantidade para 2 com digitação real e clica em atualizar.
    marca("e.name && e.name.startsWith('edit_quantity')", "frete-teste-qtd")
    D.js("const q = document.getElementById('frete-teste-qtd'); q.value = '';")
    D.type("#frete-teste-qtd", "2")
    marca("/atualiz|update/i.test(e.value || e.textContent || '') && (e.type === 'submit' || e.tagName === 'BUTTON')",
          "frete-teste-atualizar")
    D.click("#frete-teste-atualizar")
    time.sleep(8)
    R["quantidade_apos_atualizar"] = D.js("return document.getElementById('frete-teste-qtd').value;")
    R["opcoes_apos_quantidade"] = espera(f"document.querySelectorAll('{OPCOES}').length > 0", 30)
finally:
    try:
        D.close()
    except Exception:
        pass

print(json.dumps(R, ensure_ascii=False, indent=2))
falhas = [k for k, v in R.items() if v is False or v in (None, "") or (k == "contador_apos_adicionar" and v == 0)]
print("FALHAS:", falhas or "nenhuma")
sys.exit(1 if falhas else 0)
