"""Teste visual do simulador de frete na página do produto (ACULTA420, servidor de testes).

Abre a página do produto no Firefox ESR visível (DISPLAY=:0), preenche o CEP, espera a cotação
do sandbox do Melhor Envio e confere os três estados do bloco: disponível, CEP inválido e
indisponível. Mede overflow horizontal no celular. Não altera tema, configuração nem carrinho.

Ambiente: servidor de testes (loja.aculta.toca.net.br). Saída: SUITE_OUT (padrão /tmp/aculta-frete).
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
PRODUTO = os.environ.get(
    "FRETE_PRODUTO_URL",
    "https://loja.aculta.toca.net.br/loja/produto/bloco-sativa/camiseta-abada-bloco-sativa-2026-regata",
)
PREFS = {"network.dns.localDomains": "loja.aculta.toca.net.br"}
CEP_VALIDO = "01310-100"
CEP_INVALIDO = "123"

D = Driver(prefs=PREFS)
S = lambda: f"/session/{D.session}"
RESULTADO = {}


def shot(nome):
    png = D.req("GET", S() + "/screenshot")
    with open(os.path.join(OUT, nome), "wb") as fh:
        fh.write(base64.b64decode(png))


def tamanho(largura, altura):
    D.req("POST", S() + "/window/rect", {"width": largura, "height": altura})


def espera(condicao, timeout=60):
    fim = time.time() + timeout
    while time.time() < fim:
        if D.js(f"return !!({condicao});"):
            return True
        time.sleep(0.5)
    return False


def preenche_e_calcula(cep):
    D.js("""
      const campo = document.querySelector('[data-aculta-shipping-calculator] input[name="cep"]');
      campo.value = arguments[0];
    """, [cep])
    D.click("[data-aculta-shipping-calculator] button[type=submit]")


def resultado_texto():
    return D.js("""
      const area = document.querySelector('[data-aculta-shipping-calculator] [data-aculta-shipping-resultado]');
      return area ? area.innerText.trim() : '';
    """)


try:
    tamanho(1280, 900)
    D.get(PRODUTO)
    RESULTADO["bloco_presente"] = espera("document.querySelector('[data-aculta-shipping-calculator]')")
    RESULTADO["comportamento_js"] = espera("Drupal && Drupal.behaviors && Drupal.behaviors.acultaShippingCalculator")
    shot("01-produto-desktop.png")

    # Caso 1: CEP válido, cotação real no sandbox.
    preenche_e_calcula(CEP_VALIDO)
    RESULTADO["disponivel_opcoes"] = espera(
        "document.querySelectorAll('[data-aculta-shipping-calculator] .aculta-shipping-calculator__opcao').length > 0", 90)
    RESULTADO["disponivel_texto"] = resultado_texto()
    shot("02-disponivel-desktop.png")

    # Caso 2: CEP inválido, recusado antes de qualquer chamada.
    preenche_e_calcula(CEP_INVALIDO)
    RESULTADO["invalido_mensagem"] = espera(
        "/CEP válido/.test(document.querySelector('[data-aculta-shipping-calculator] [data-aculta-shipping-resultado]').innerText)", 20)
    shot("03-cep-invalido-desktop.png")

    # Celular: o Firefox desta sessão X não encolhe a janela abaixo de ~500 px, então a viewport de 390 px é um iframe
    # do mesmo domínio. Mede-se a largura real do documento dentro dele (innerWidth = 390).
    D.js("""
      document.body.innerHTML = '';
      const f = document.createElement('iframe');
      f.id = 'celular';
      f.style.width = '390px';
      f.style.height = '844px';
      f.style.border = '0';
      f.src = arguments[0];
      document.body.appendChild(f);
    """, [PRODUTO])
    RESULTADO["celular_bloco_presente"] = espera(
        "document.getElementById('celular') && document.getElementById('celular').contentDocument"
        " && document.getElementById('celular').contentDocument.querySelector('[data-aculta-shipping-calculator]')", 60)
    time.sleep(4)  # layout estável: a gaveta do carrinho fica fora da tela (escondida) e não conta como rolagem
    RESULTADO["celular_viewport_px"] = D.js("return document.getElementById('celular').contentWindow.innerWidth;")
    RESULTADO["celular_sem_overflow"] = D.js("""
      const w = document.getElementById('celular').contentWindow;
      return w.document.documentElement.scrollWidth <= w.innerWidth + 1;
    """)
    RESULTADO["celular_bloco_medidas"] = D.js("""
      const w = document.getElementById('celular').contentWindow;
      const bloco = w.document.querySelector('[data-aculta-shipping-calculator]');
      const caixa = bloco.getBoundingClientRect();
      const estoura = Array.from(bloco.querySelectorAll('.aculta-shipping-calculator__opcao, input, button'))
        .some(el => el.getBoundingClientRect().right > caixa.right + 1);
      return {largura: Math.round(caixa.width), esquerda: Math.round(caixa.left), direita: Math.round(caixa.right),
              viewport: w.innerWidth, dentroDaTela: caixa.right <= w.innerWidth + 1 && caixa.left >= -1, estoura: estoura};
    """)
    shot("04-produto-celular-390.png")
finally:
    try:
        D.close()
    except Exception:
        pass

print(json.dumps(RESULTADO, ensure_ascii=False, indent=2))
medidas = RESULTADO.get("celular_bloco_medidas") or {}
if RESULTADO.get("celular_viewport_px") != 390 or not (medidas.get("dentroDaTela") and not medidas.get("estoura")):
    RESULTADO["celular_medidas_ok"] = False
falhas = [k for k, v in RESULTADO.items() if v is False or v in ("", None)]
print("FALHAS:", falhas or "nenhuma")
sys.exit(1 if falhas else 0)
