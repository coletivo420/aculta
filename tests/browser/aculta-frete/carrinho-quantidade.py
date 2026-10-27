"""Teste visual do frete no carrinho (ME-0.1-C): cálculo no produto, carrinho e alteração de quantidade.

Fluxo no servidor de testes, com Firefox ESR visível (DISPLAY=:0):
1. Produto (loja.aculta.toca.net.br): digita um CEP válido e espera as opções (sem botão).
2. Adiciona o produto ao carrinho. O cálculo deve ir junto: o resumo do frete aparece na gaveta.
3. Carrinho completo (aculta.toca.net.br/carrinho, domínio principal): o bloco de frete mostra o último cálculo.
4. Altera a quantidade do item para 2 e atualiza o carrinho. O cálculo guardado é refeito e continua aparecendo.

Cria um item no carrinho do servidor de testes (dado de teste). Não altera tema nem configuração.
Saída: SUITE_OUT (padrão /tmp/aculta-frete). Limitação: a gaveta e o carrinho podem estar em domínios diferentes.
"""

import base64
import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = os.environ.get("SUITE_OUT", "/tmp/aculta-frete")
os.makedirs(OUT, exist_ok=True)
PRODUTO = "https://loja.aculta.toca.net.br/loja/produto/bloco-sativa/camiseta-abada-bloco-sativa-2026-regata"
CARRINHO = "https://aculta.toca.net.br/carrinho"
PREFS = {"network.dns.localDomains": "loja.aculta.toca.net.br,aculta.toca.net.br"}
CEP = "01310100"
D = Driver(prefs=PREFS)
S = lambda: f"/session/{D.session}"
R = {}


def shot(nome):
    png = D.req("GET", S() + "/screenshot")
    with open(os.path.join(OUT, nome), "wb") as fh:
        fh.write(base64.b64decode(png))


def espera(cond, timeout=60):
    fim = time.time() + timeout
    while time.time() < fim:
        if D.js(f"return !!({cond});"):
            return True
        time.sleep(0.5)
    return False


CAMPO = '[data-aculta-shipping-calculator] input[name="cep"]'
OPCOES = '[data-aculta-shipping-calculator] .aculta-shipping-calculator__opcao'

try:
    D.req("POST", S() + "/window/rect", {"width": 1280, "height": 900})
    D.get(PRODUTO)
    espera("document.querySelector('" + CAMPO + "')")
    D.js("const c = document.querySelector(arguments[0]); c.value=''; c.dispatchEvent(new Event('input'));", [CAMPO])
    D.type(CAMPO, CEP)
    R["produto_opcoes"] = espera(f"document.querySelectorAll('{OPCOES}').length > 0", 90)
    shot("10-produto-calculado.png")

    # Adiciona ao carrinho: escolhe o primeiro tamanho e envia o formulário de compra.
    D.js("""const sel = document.querySelector('form[id^="commerce-order-item-add-to-cart-form"] select');
            if (sel && sel.options.length > 1) { sel.selectedIndex = 1; sel.dispatchEvent(new Event('change')); }""")
    D.js("""const b = Array.from(document.querySelectorAll('form[id^="commerce-order-item-add-to-cart-form"] input[type=submit]'))[0];
            b.click();""")
    time.sleep(6)
    R["adicionado_url"] = D.js("return location.href;")
    R["gaveta_resumo_frete"] = espera("document.querySelectorAll('.aculta-shipping-summary__opcao').length > 0 || "
                                      "document.querySelector('.aculta-shipping-summary') !== null", 20)
    shot("11-apos-adicionar.png")

    # Carrinho completo, domínio principal: o frete calculado no produto precisa estar lá.
    D.get(CARRINHO)
    espera("document.querySelector('form[id*=commerce-cart]') || document.querySelector('[data-aculta-shipping-calculator]')", 60)
    R["carrinho_bloco"] = espera("document.querySelector('[data-aculta-shipping-calculator]')", 20)
    R["carrinho_opcoes_do_produto"] = espera(f"document.querySelectorAll('{OPCOES}').length > 0", 15)
    shot("12-carrinho.png")

    # Altera a quantidade para 2 e atualiza o carrinho.
    D.js("""const q = Array.from(document.querySelectorAll('input[name^="edit_quantity"]'))[0];
            if (q) { q.value = '2'; q.dispatchEvent(new Event('change')); }""")
    D.js("""const u = Array.from(document.querySelectorAll('input[type=submit],button[type=submit]'))
             .find(b => /atualiz|update/i.test(b.value || b.textContent));
            if (u) { u.click(); }""")
    time.sleep(8)
    R["quantidade_atual"] = D.js("""const q = Array.from(document.querySelectorAll('input[name^="edit_quantity"]'))[0];
            return q ? q.value : null;""")
    R["apos_quantidade_opcoes"] = espera(f"document.querySelectorAll('{OPCOES}').length > 0", 30)
    shot("13-apos-quantidade.png")
finally:
    try:
        D.close()
    except Exception:
        pass

print(json.dumps(R, ensure_ascii=False, indent=2))
falhas = [k for k, v in R.items() if v is False or v in (None, "")]
print("FALHAS:", falhas or "nenhuma")
sys.exit(1 if falhas else 0)
