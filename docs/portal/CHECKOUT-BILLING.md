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

- O passo de endereço de cobrança com CEP, conforme o comportamento esperado acima, ainda **não está implementado**. O caminho mais coerente com o Commerce é um pane próprio do Portal (`CheckoutPane`) no passo `order_information`, que reaproveita o autocomplete por CEP de "Meus endereços". Depende de decisão do responsável.
- Título "Payment" e rótulo "Quantity" sem tradução no checkout e no carrinho.
- Coluna "Item" do carrinho mostra só preço e tamanho, sem o nome do produto.
- Não houve reprodução, por HTTP, do aceite repetido no host da loja relatado pelo Felipe (com sessão logada, `/loja/produto/...` respondeu 200). A causa provável é o destino fixo do acordo, já corrigido.

## Onde está o código

- `web/modules/contrib/commerce/modules/payment/src/Hook/CommercePaymentHooks.php` (unset do `billing_information`; contrib, não editar).
- `web/modules/contrib/commerce_mercado_pago/src/PluginForm/Checkout/RedirectCheckoutForm.php` (mensagem de erro; contrib).
- `web/modules/contrib/commerce/modules/payment/src/Plugin/Commerce/CheckoutPane/PaymentInformation.php` (formulário de cobrança).
- `web/modules/contrib/agreement/src/Form/AgreementForm.php` (`settings.destination` e destino salvo).
