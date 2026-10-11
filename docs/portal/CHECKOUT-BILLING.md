# Checkout: endereço de cobrança exigido pelo Mercado Pago (incidente 2026-10-11)

## Sintoma

- Pedido: **5** (usuário de teste `teste.comum`, uid 5, rascunho, 1 item M, R$ 30,00).
- Etapa: pagamento. URL: `https://aculta.toca.net.br/checkout/5/payment`.
- Mensagem: "Billing information is required to proceed." (sem tradução). Título da etapa: "Payment" (sem tradução).
- Antes: `/checkout/5/order_information` mostrava só o resumo e "Continuar para a revisão", sem campos de nome, endereço ou cobrança.
- Não houve redirecionamento ao Mercado Pago. Console sem erros.

## Comportamento esperado (orientação do responsável)

- Usuário sem endereço cadastrado: o checkout abre a tela de endereço e dados de cobrança.
- O preenchimento usa o autocomplete por CEP, do mesmo jeito de "Meus endereços" no painel da conta, sem abas e somente com o endereço.
- Depois de salvo, o checkout segue para a revisão e para o Mercado Pago.

## Causa (três camadas, verificadas no runtime)

1. **Dado da loja:** `commerce_store` 1 tinha `billing_countries` vazio. O formulário de cobrança do Commerce usa esses países, então sem país não renderiza campos. Corrigido no runtime: `billing_countries = [BR]`. O campo não está no `config/sync` (é dado de entidade, não configuração exportada); o responsável deve manter esse valor no ambiente de produção.
2. **Pane removido pelo Commerce:** `commerce_payment` implementa `hook_commerce_checkout_pane_info_alter` com `unset($definitions['billing_information'])`. O pane `payment_information` substitui o `billing_information`. O fluxo `default` ainda lista `billing_information` em `order_information`, e o plugin não está registrado. Quem coleta cobrança passa a ser o pane de pagamento.
3. **Gateway de redirecionamento:** o Mercado Pago (`commerce_mercado_pago`, contrib, não editado) exige no formulário de redirecionamento um perfil de cobrança com endereço (`RedirectCheckoutForm`). O pane de pagamento só monta o formulário de cobrança quando `collectsBillingInformation()` é verdadeiro. Ativado em `config/sync/commerce_payment.commerce_payment_gateway.mercado_pago.yml` (`collect_billing_information: true`) e no runtime. **Mesmo assim**, na etapa `payment` o pane não renderiza o formulário; o pedido 5 segue sem perfil de cobrança e o erro continua.

## Correções aplicadas (0.3.0-dev.14)

- Loja 1: `billing_countries = [BR]` (runtime).
- Gateway Mercado Pago: `collect_billing_information: true` (config/sync e runtime).
- Acordo `aculta_account_terms`: `settings.destination` vazio (config/sync e runtime). Antes, `/` mandava o usuário para a home do host onde aceitou (no caso da loja, `/loja-da-aculta`) e perdia o produto pedido. Agora o retorno usa o destino salvo, com `<front>` do host como alternativa.

## Pendente (não resolvido)

- **Implementado em 0.3.0-dev.15:** pane `aculta_billing_address` (`BillingAddressPane`) no passo `order_information`, com o autocomplete por CEP `cep_autocomplete/viacep`. Substitui o `billing_information` removido pelo Commerce Payment. O gateway Mercado Pago voltou para `collect_billing_information: false`, porque o pane agora coleta o endereço. Verificado: campo de CEP renderizado no pedido 5. **Pendente:** teste de gravação pelo navegador e passagem ao Mercado Pago.
- Título "Payment" e rótulo "Quantity" sem tradução no checkout e no carrinho.
- Coluna "Item" do carrinho mostra só preço e tamanho, sem o nome do produto.
- Não houve reprodução, por HTTP, do aceite repetido no host da loja relatado pelo Felipe (com sessão logada, `/loja/produto/...` respondeu 200). A causa provável é o destino fixo do acordo, já corrigido.

## Onde está o código

- `web/modules/contrib/commerce/modules/payment/src/Hook/CommercePaymentHooks.php` (unset do `billing_information`; contrib, não editar).
- `web/modules/contrib/commerce_mercado_pago/src/PluginForm/Checkout/RedirectCheckoutForm.php` (mensagem de erro; contrib).
- `web/modules/contrib/commerce/modules/payment/src/Plugin/Commerce/CheckoutPane/PaymentInformation.php` (formulário de cobrança).
- `web/modules/contrib/agreement/src/Form/AgreementForm.php` (`settings.destination` e destino salvo).

## Incidente de ambiente: credencial de produção no token de teste (2026-10-11)

- **Sintoma:** ao pagar o pedido 5 no servidor de testes, o Mercado Pago abriu `mercadopago.com.br/checkout/v1/payment/redirect/...` (domínio de produção), logado na conta real do responsável, com "Saldo em conta" como método recomendado. Não houve pagamento: o usuário parou na tela de revisão do Mercado Pago.
- **Causa verificada:** o gateway estava em `mode: test`, mas o token de teste vem da variável de ambiente `MERCADOPAGO_TEST_ACCESS_TOKEN` (`key.key.mercadopago_test_access_token`, provedor env). O valor começa com `APP_USR`, que é o formato de credencial de produção. Credenciais de teste começam com `TEST-`. Com token de produção, o `RedirectCheckoutForm` usa o `init_point` de produção, e não o `sandbox_init_point`.
- **Contenção aplicada:** gateway `mercado_pago` desligado no runtime (`status: 0`). Já estava `false` no `config/sync`.
- **Pendente (responsável):** confirmar que o token usado no ambiente de testes é de conta de teste do Mercado Pago (credencial de teste; não há necessidade de rotação, conforme orientação do responsável). O prefixo atual é `APP_USR`, e o redirecionamento caiu no domínio de produção; por isso o gateway segue desligado. Antes de religar, o token de teste deve ser confirmado como de conta de teste e o redirecionamento deve ir para `sandbox.mercadopago.com.br`. Credenciais não podem estar no banco de configuração (ver AGENTS.md, seção de Keys).
- **Pendente (código):** o `RedirectCheckoutForm` usa `init_point`. Com credencial de teste, o Mercado Pago deve devolver URLs de sandbox. Verificar que o domínio do redirecionamento é `sandbox.mercadopago.com.br` antes de religar. Credenciais não podem estar no banco de configuração (ver AGENTS.md, seção de Keys).
