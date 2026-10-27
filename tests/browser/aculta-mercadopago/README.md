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
solicitada e o pedido não foi concluído. Esse smoke não cria preferência pela
`RedirectCheckoutForm`; a preferência e a tela de comprador são verificadas no
roteiro separado abaixo.

Após corrigir o campo CEP obrigatório no simulador, a execução de 2026-10-27
retornou `guest_registration_and_shipping_stage_without_captcha`: produto,
carrinho, cadastro silencioso e etapa de endereço carregaram; nenhuma cotação
ou pagamento foi iniciado.

Reexecução após a integração dos totais ajustados no Checkout Pro também
retornou `guest_registration_and_shipping_stage_without_captcha` no Firefox
ESR 153.5.1 visível. O smoke não chegou à preferência nem ao pagamento; o
carrinho anônimo de rascunho gerado pelo teste foi removido após confirmar que
era o único item criado por essa sessão.

`preference-customer-stage.py` abre um `init_point` de preferência sandbox
criado pelo cliente/credencial de teste e confirma se a página do provedor
carrega ou se apresenta um desafio de segurança. Não autentica o comprador e
nunca submete pagamento. Em 2026-10-27, uma preferência de R$ 1,00 com item e
referência sintéticos foi criada no modo test, o `init_point` abriu a tela
“Escolha como pagar” com HTTP 200 e sem CAPTCHA, e “Continuar” chegou à tela de
identificação do comprador com HTTP 200. O helper tentou o login autorizado
partindo dessa etapa, mas retornou `login_incomplete`; não houve sessão
confirmada ou pagamento.

## Ambiente

- Firefox ESR visível em `DISPLAY=:0`, controlado pelo geckodriver;
- navegação pela internet até `https://www.mercadopago.com.br/`;
- `buyer-login.py` lê em memória as credenciais do comprador de teste no
  arquivo ignorado pelo Git `secrets/mercadolivre_test_user`;
- `create-sandbox-preference.php` usa o gateway do Runtime somente quando ativo,
  em modo `test` e com token presente. Envia item/referência sintéticos, não usa
  credenciais de produção e grava somente o `init_point` em `$SUITE_OUT`;
- os scripts de navegador não usam credenciais Drupal ou acesso direto ao banco.

## Execução

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/tmp/aculta-mp-preference ACULTA_MP_INIT_POINT_FILE=/tmp/aculta-mp-preference/init-point.url vendor/bin/drush php:script tests/browser/aculta-mercadopago/create-sandbox-preference.php
DISPLAY=:0 SUITE_OUT=/tmp/aculta-mp-preference ACULTA_MP_INIT_POINT_FILE=/tmp/aculta-mp-preference/init-point.url ACULTA_MP_ADVANCE_TO_CUSTOMER=1 python3 -I tests/browser/aculta-mercadopago/preference-customer-stage.py
DISPLAY=:0 ACULTA_MP_INIT_POINT_FILE=/tmp/aculta-mp-preference/init-point.url python3 -I tests/browser/aculta-mercadopago/buyer-login.py
DISPLAY=:0 python3 -I tests/browser/aculta-mercadopago/checkout-smoke.py
```

Os scripts de smoke salvam screenshots somente em `$SUITE_OUT` (padrão
`/tmp/aculta-mercadopago-checkout`). `ACULTA_PRODUCT_BASE_URL`,
`ACULTA_CHECKOUT_BASE_URL` e `ACULTA_MP_PRODUCT_PATH` podem apontar para outro
site/produto de teste. Por padrão, o produto é carregado em
`loja.aculta.toca.net.br` e o carrinho/checkout central em
`aculta.toca.net.br`.

Para o teste de preferência, o arquivo indicado por `ACULTA_MP_INIT_POINT_FILE`
deve conter somente a URL de retorno `init_point` produzida por uma preferência
sandbox; guarde-o em `$SUITE_OUT`, nunca no repositório. A URL não é impressa.
O helper de login usa esse endereço para abrir diretamente a autenticação
iniciada pelo checkout; sem a variável, continua iniciando pela página principal.

A saída do helper de login informa somente um resultado categórico. Ele não
imprime credenciais, URL de retorno, conteúdo da página, nome do comprador ou
cookies; usa perfil temporário do `wdlib.py` e não salva imagens/HTML da conta.
O smoke da preferência imprime somente host, path com o ID da preferência
redigido, título, status HTTP e indicadores de formulário/CAPTCHA; a captura da tela do provedor fica em
`$SUITE_OUT`. O teste que cria preferência deixa um objeto de teste no painel
Mercado Pago, mas não cria pedido nem cobrança. Nenhum helper contorna desafios
de segurança.

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
