# Mercado Pago — login sandbox do comprador

Este script valida que o comprador sandbox autorizado consegue autenticar no
Mercado Pago. Ele não cria preferência, pedido ou pagamento e não executa
estorno.

`checkout-smoke.py` percorre a página de um produto, adiciona uma unidade ao
carrinho anônimo, abre o checkout central, verifica por AJAX o cadastro para
um e-mail reservado `.invalid` e avança até os campos de endereço usados para
cotar frete. Confirma a ausência de CAPTCHA/Turnstile em cada tela, inclusive
produto, carrinho, identificação e endereço/frete. Para antes de enviar o
endereço ou o pedido; não calcula uma cotação externa, cria conta, confirma
pagamento nem entra no Mercado Pago. O carrinho/pedido Drupal temporário pode
permanecer no Runtime para inspeção posterior.

O roteiro também verifica a regressão em que o simulador no carrinho bloqueava
“Fechar pedido”: o CEP da cotação precisa ser opcional e o componente não pode
emitir um formulário aninhado no form do Commerce.

Na execução de 2026-10-27, Firefox ESR carregou o produto e carrinho sem
CAPTCHA, mostrou Google antes do e-mail, revelou o cadastro AJAX sem senha nem
desafio para novo e-mail e avançou à etapa de endereço e cotação de frete sem
CAPTCHA. O formulário de endereço não foi enviado, nenhuma cotação foi
solicitada e o pedido não foi concluído. Preferência e autenticação do
comprador Mercado Pago continuam pendentes de uma sessão sandbox acessível.

Após corrigir o campo CEP obrigatório no simulador, a execução de 2026-10-27
retornou `guest_registration_and_shipping_stage_without_captcha`: produto,
carrinho, cadastro silencioso e etapa de endereço carregaram; nenhuma cotação
ou pagamento foi iniciado.

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
