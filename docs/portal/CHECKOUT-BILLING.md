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

## Incidente de ambiente: redirecionamento do Mercado Pago com token de teste (2026-10-11)

- **Sintoma:** ao pagar o pedido 5 no servidor de testes, o Mercado Pago abriu a tela de revisão no domínio `mercadopago.com.br`, com a conta logada do responsável (com saldo). Nada foi pago.
- **Verificado:** o token do ambiente (`MERCADOPAGO_TEST_ACCESS_TOKEN`) começa com `APP_USR` e pertence a uma **conta de teste** do Mercado Pago (`test_data.test_user = true`, apelido `TESTUSER…`, tag `test_user`). A captura do painel "Credenciais de teste" mostra o mesmo formato. Portanto, `APP_USR` **não** indica produção. Não há orientação de rotação para essas credenciais.
- **Causa provável do domínio `mercadopago.com.br`:** o código usa `init_point`. A tela de pagamento abriu no navegador onde a sessão do responsável estava logada no Mercado Pago. Para testar, o comprador precisa ser o usuário de teste do Mercado Pago, e não uma conta real.
- **Ação:** gateway religado em 2026-10-11 para o teste de compra simulada. Antes de cada teste, o navegador deve estar **deslogado** do Mercado Pago ou logado no comprador de teste.
- **Pendente de confirmação:** o redirecionamento de teste deve ir para o fluxo de teste do Mercado Pago. Se houver dúvida sobre o domínio exibido, conferir o modo da conta de teste antes de pagar.

## Métodos de pagamento do Mercado Pago na preferência (2026-10-11)

- **Sintoma:** "Pagar e concluir a compra" abria a tela do Mercado Pago só com saldo ("Você não tem saldo suficiente") ou só com "Cartão de Débito Virtual CAIXA". Cartão de crédito não aparecia.
- **Causa:** a configuração do gateway estava com `payment_types` vazio e `payment_method` vazio. Pelo módulo `commerce_mercado_pago`, isso gera `excluded_payment_types` com todos os tipos configuráveis e `excluded_payment_methods` com todas as bandeiras (visa, master, amex, hipercard, elo, caixa). Sobrava apenas a conta de saldo.
- **Correção aplicada só no servidor de testes:** `payment_types = {credit_card, debit_card}` e `payment_method` com as bandeiras desses tipos para o Brasil (visa, master, amex, hipercard, elo, caixa). Resultado: nenhuma exclusão de métodos; excluídos apenas boleto, transferência e pré-pago.
- **Repositório:** a mesma configuração foi levada ao `config/sync` (ver seção seguinte).

## Crédito, débito e Pix no Checkout Pro (2026-10-11)

- Tipos habilitados (`payment_types`): `credit_card`, `debit_card` e `bank_transfer` (Pix).
- Métodos habilitados (`payment_method`, para o Brasil): `visa`, `master`, `amex`, `hipercard`, `elo` (crédito); `caixa`, `elo` (débito); `pix` (transferência).
- Validado pela função `CheckoutPro::getExcludedPaymentTypesForPreference`: excluídos apenas `ticket` (boleto) e `prepaid_card`. `getExcludedPaymentMethodsForPreference`: nenhum.
- Parcelamento: `max_installments` = 1 (a preferência envia 1 parcela). Não alterado, porque o pedido não pediu parcelas.
- Pix: o módulo mapeia Pix como o método `pix` do tipo `bank_transfer`. **Não foi possível confirmar pela API** se a conta vendedora de teste tem a chave Pix cadastrada. Esse dado está no painel do Mercado Pago (conta de teste) e precisa ser confirmado pelo Felipe; se a opção não aparecer no checkout, é esse o motivo provável.
- Configuração aplicada no servidor de testes e levada ao `config/sync/commerce_payment.commerce_payment_gateway.mercado_pago.yml`.
- **Resultado do reteste (d277f2a):** a tela "Como você prefere pagar?" mostra cartões de crédito salvos (Mastercard e Visa de teste), "Novo cartão – Crédito" e "Cartão de Débito Virtual CAIXA". Crédito e débito confirmados. **Pix não aparece** no teste: provável falta de chave Pix na conta vendedora de teste.
- **Decisão do responsável:** Pix **mantido ativado** na configuração (servidor de testes e `config/sync`). Não remover. A validação do Pix será feita em produção, com chave real cadastrada.

