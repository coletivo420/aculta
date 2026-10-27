# Navegador — simulador de frete na página do produto

Teste visual do simulador de frete por CEP (componente `shipping-calculator`, ACULTA420), com cotação real no sandbox do
Melhor Envio. Frente: ME-0.1-C (`aculta_commerce_melhorenvio`).

## Ambiente

- **Servidor de testes** (`loja.aculta.toca.net.br`). Não é o ambiente local.
- Firefox ESR visível (`DISPLAY=:0`), geckodriver via `wdlib.py` da frente `aculta-cpf-checkout`. Nunca headless.
- Não altera tema, configuração nem carrinho. O cenário cria uma sessão anônima de teste e grava nela somente o CEP e a cotação sintéticos usados para verificar persistência; não autentica nem modifica dados de conta.

## Execução

```bash
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/tmp/aculta-frete DISPLAY=:0 python3 -I tests/browser/aculta-frete/frete-visual.py
```

Variável opcional: `FRETE_PRODUTO_URL` (padrão: produto `bloco-sativa/camiseta-abada-bloco-sativa-2026-regata`).
Saída: JSON com os resultados e `FALHAS: nenhuma` quando tudo passa. PNG em `SUITE_OUT` (fora do repositório).

## O que cobre

1. Bloco presente e comportamento JavaScript (`Drupal.behaviors.acultaShippingCalculator`).
2. CEP válido (`01310-100`): segura temporariamente a resposta do endpoint, verifica ícone animado, texto acessível e `aria-busy`, depois libera a cotação real do sandbox e confere opções, preço em reais e prazo. Recarrega a página para confirmar que o CEP anônimo voltou da sessão e que a cotação inicia automaticamente.
3. CEP inválido (`123`): mensagem de CEP inválido, sem chamar a API.
4. Celular de 390 px: a viewport é um `iframe` de 390 px do mesmo domínio. Sem rolagem horizontal e com o bloco
   dentro da tela (16 px de margem de cada lado).

## Limitações conhecidas

- **Largura mínima da janela:** o Firefox desta sessão X não encolhe a janela abaixo de ~500 px. Por isso o celular é
  medido em `iframe`, e não redimensionando a janela.
- **Olivero não coberto:** trocar o tema é mudança global. A verificação com Olivero fica para um roteiro com
  `trap` de restauração do tema.
- **Carrinho não coberto:** adicionar produto ao carrinho cria dados no servidor de testes. Fica fora deste roteiro.
- **Conta autenticada:** persistência via `user.data` e preenchimento inicial pelo CEP do perfil cobertos em Unit; este roteiro não autentica usuário nem altera dados de conta.
- **Pacote padrão:** o teste assume o pacote cadastrado (16 × 2 × 11 cm, 0,3 kg). Se ele mudar, o resultado da cotação
  muda, mas o teste continua válido.

## carrinho-quantidade.py: BLOQUEADO por separação de domínios (2026-10-27)

Estado: **falha esperada**. O roteiro não consegue validar "o cálculo vai junto" nem o recálculo ao mudar quantidade.

Evidência (sondagem no navegador):
- O cálculo no produto (`loja.aculta.toca.net.br`) funciona.
- Depois de "Adicionar ao carrinho" na loja, o carrinho do domínio principal (`aculta.toca.net.br/carrinho`) aparece
  vazio: "Seu carrinho de compras está vazio."
- O cookie de sessão é por domínio. O item e o cálculo ficam na sessão da loja; o carrinho principal não os enxerga.
- Não há captcha no formulário de compra (está na lista de exceção do Turnstile). Não há erro no log do Drupal.
- As violações de CSP no log são `style-src-attr` em modo `report`. Não bloqueiam nada.

Decisão necessária: o carrinho fica no domínio principal (regra do projeto) ou na loja? Enquanto a sessão não for
compartilhada, o cálculo feito na página do produto não chega ao carrinho.
