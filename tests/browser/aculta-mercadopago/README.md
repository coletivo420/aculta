# Mercado Pago — login sandbox do comprador

Este script valida que o comprador sandbox autorizado consegue autenticar no
Mercado Pago. Ele não cria preferência, pedido ou pagamento e não executa
estorno.

`checkout-smoke.py` percorre a página de um produto, adiciona uma unidade ao
carrinho anônimo, abre o checkout central e verifica por AJAX o estado de
cadastro para um e-mail reservado `.invalid`. Para antes de enviar o pedido;
não cria conta, confirma pagamento nem entra no Mercado Pago. O carrinho/pedido
Drupal temporário pode permanecer no Runtime para inspeção posterior.

Na execução de 2026-10-27, o produto e o carrinho foram renderizados, e o
checkout redirecionou para a etapa de login/cadastro Drupal
(`/finalizar-compra/{id}/entrar`). Não foi possível alcançar a preferência nem
a autenticação do comprador sem uma conta Drupal. A etapa do comprador Mercado
Pago ainda precisa de uma sessão da conta sandbox acessível.

## Ambiente

- Firefox ESR visível em `DISPLAY=:0`, controlado pelo geckodriver;
- navegação pela internet até `https://www.mercadopago.com.br/`;
- credenciais do comprador de teste no arquivo ignorado pelo Git
  `secrets/mercadolivre_test_user`;
- não usa credenciais Drupal, banco de dados, API token do vendedor ou
  configuração administrativa.

## Execução

```sh
cd /home/piradopirata/projetos/aculta
DISPLAY=:0 python3 -I tests/browser/aculta-mercadopago/buyer-login.py
DISPLAY=:0 python3 -I tests/browser/aculta-mercadopago/checkout-smoke.py
```

O segundo script salva screenshots somente em `$SUITE_OUT` (padrão
`/tmp/aculta-mercadopago-checkout`). `ACULTA_PRODUCT_BASE_URL`,
`ACULTA_CHECKOUT_BASE_URL` e `ACULTA_MP_PRODUCT_PATH` podem apontar para outro
site/produto de teste. Por padrão, o produto é carregado em
`loja.aculta.toca.net.br` e o carrinho/checkout central em
`aculta.toca.net.br`.

A saída informa somente o resultado categórico do login. O script não imprime
credenciais, URL de retorno, conteúdo da página, nome do comprador nem cookies.
Ele cria perfil temporário em `/tmp` por meio de `wdlib.py` e o remove ao
encerrar. Não contorna desafios de segurança. Não salva screenshots ou HTML,
para não registrar informações da conta.

## Limitações

- O login só prova que a conta sandbox autentica; não prova o checkout do
  ACULTA, autorização do pagamento, notificação assinada ou conciliação.
- O script encerra a sessão após o login. Um desafio de segurança exige
  interação manual do responsável em uma sessão visível.
- Nenhuma compra é iniciada. Pagamentos dependem de um pedido de teste e de
  confirmação do ambiente sandbox no checkout.
- O smoke de checkout usa um produto existente e pode criar carrinho/pedido
  anônimo de teste no Runtime. Ele não submete dados fiscais, não confirma o
  pedido nem inicia cobrança; a tela do cliente no Mercado Pago depende de a
  Loja disponibilizar o gateway e de o checkout avançar até a preferência.
