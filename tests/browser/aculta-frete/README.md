# Navegador — simulador de frete na página do produto

Teste visual do simulador de frete por CEP (componente `shipping-calculator`, ACULTA420), com cotação real no sandbox do
Melhor Envio. Frente: ME-0.1-C (`aculta_commerce_melhorenvio`).

## Ambiente

- **Servidor de testes** (`loja.aculta.toca.net.br`). Não é o ambiente local.
- Firefox ESR visível (`DISPLAY=:0`), geckodriver via `wdlib.py` da frente `aculta-cpf-checkout`. Nunca headless.
- Não altera tema, configuração nem carrinho. Só lê a página do produto e preenche o campo de CEP.

## Execução

```bash
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/tmp/aculta-frete DISPLAY=:0 python3 -I tests/browser/aculta-frete/frete-visual.py
```

Variável opcional: `FRETE_PRODUTO_URL` (padrão: produto `bloco-sativa/camiseta-abada-bloco-sativa-2026-regata`).
Saída: JSON com os resultados e `FALHAS: nenhuma` quando tudo passa. PNG em `SUITE_OUT` (fora do repositório).

## O que cobre

1. Bloco presente e comportamento JavaScript (`Drupal.behaviors.acultaShippingCalculator`).
2. CEP válido (`01310-100`): cotação real no sandbox, com as opções, o preço em reais e o prazo.
3. CEP inválido (`123`): mensagem de CEP inválido, sem chamar a API.
4. Celular de 390 px: a viewport é um `iframe` de 390 px do mesmo domínio. Sem rolagem horizontal e com o bloco
   dentro da tela (16 px de margem de cada lado).

## Limitações conhecidas

- **Largura mínima da janela:** o Firefox desta sessão X não encolhe a janela abaixo de ~500 px. Por isso o celular é
  medido em `iframe`, e não redimensionando a janela.
- **Olivero não coberto:** trocar o tema é mudança global. A verificação com Olivero fica para um roteiro com
  `trap` de restauração do tema.
- **Carrinho não coberto:** adicionar produto ao carrinho cria dados no servidor de testes. Fica fora deste roteiro.
- **Pacote padrão:** o teste assume o pacote cadastrado (16 × 2 × 11 cm, 0,3 kg). Se ele mudar, o resultado da cotação
  muda, mas o teste continua válido.
