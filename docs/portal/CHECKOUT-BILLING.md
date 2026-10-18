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

## Pendências do checkout (atualizado em 2026-10-12)

- **Implementado em 0.3.0-dev.15:** pane `aculta_billing_address` (`BillingAddressPane`) no passo `order_information`, com o autocomplete por CEP `cep_autocomplete/viacep`. Substitui o `billing_information` removido pelo Commerce Payment. O gateway Mercado Pago voltou para `collect_billing_information: false`, porque o pane agora coleta o endereço. Verificado: campo de CEP renderizado no pedido 5. **Pendente:** teste de gravação pelo navegador e passagem ao Mercado Pago.
- Título "Payment" e rótulo "Quantity": traduzidos em 0.3.0-dev.15 (`aculta_ui.pt-br.po`).
- Coluna "Item" do carrinho: nome do produto incluído em 0.3.0-dev.15 (display `cart` de `camiseta_tamanho`).
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


## Pagamento bloqueado: "Pagar" desabilitado com cartão salvo (2026-10-11)

- **Preferência analisada (leitura pela API):** site MLB, vendedor e comprador são contas de teste distintas, moeda BRL, um item de R$ 30,00, não expirada, `binary_mode` true, `installments` 1, `auto_return` approved, `payer.identification` vazio, `payer.email` com domínio `.invalid`.
- **Alteração no servidor de testes:** e-mail do usuário `teste.comum` e do pedido 5 trocados para `teste.comum@testuser.com`. A troca pelo fluxo do Drupal exige confirmação na caixa de entrada, por isso foi aplicada direto na tabela `users_field_data`, só no servidor de testes. Confirmação de conta e aceite dos termos permanecem.
- **Limitações (contrib, não alteradas):** o módulo `commerce_mercado_pago` envia `binary_mode` fixo em true e não envia CPF no pagador. O envio de `excluded_payment_methods` vazio também é decidido pelo módulo. Mudar qualquer um desses pontos exige alteração no módulo ou um ajuste no Portal, o que ainda não foi decidido.
- **Pendente:** se "Pagar" continuar desabilitado com o novo e-mail, o caminho seguinte é avaliar CPF no pagador (módulo) e binary_mode.

## Endereço único (decisão do responsável, 2026-10-12)

- **Decisão:** o site tem um único endereço, chamado "Endereço". Não há endereço de cobrança nem de envio separados. O pagamento é feito no Mercado Pago.
- **Como fica no código:** o pane `aculta_billing_address` (rótulo "Endereço") usa o perfil `customer` do cliente quando o pedido ainda não tem um. Assim o checkout e Meus dados > Endereço editam o mesmo registro. O Commerce exibe "Billing information" como "Endereço" (tradução em `aculta_ui.pt-br.po`).
- **Dados existentes:** pedidos já criados (ex.: 5 e 6) mantêm o perfil que já tinham. Nenhuma migração foi feita.
- **Mercado Pago:** o módulo contrib usa o perfil de cobrança do pedido para o payer (nome, e-mail, endereço). Como o pedido aponta para o endereço do cliente, esse dado continua chegando.
- **Pendente:** o tipo de perfil `participante` tem um campo de endereço (`field_address`) que as telas atuais não usam. Antes de remover, conferir se há dados nele. O Commerce Shipping está instalado desde 2026-10-12 (ver a seção "Envio com o endereço único").

## Meios de pagamento e histórico de pagamentos (decisão do responsável, 2026-10-12)

- O pagamento é feito e gerenciado no **Mercado Pago**. O portal não guarda cartões nem métodos de pagamento salvos e
  não exibe o histórico de pagamentos da pessoa.
- Removido da Minha conta: a aba/seção "Meus pagamentos" (menu lateral, sanfona do celular e navegação AJAX).
- `/pagamentos` responde **301** para `/pedidos`. `/pagamentos/novo`, `/pagamentos/{id}/editar`, `/pagamentos/{id}/excluir`
  e `/pagamentos/{id}/padrao` respondem 404 (rotas do Commerce removidas pelo `DomainRouteSubscriber`).
- Permissão `manage own commerce_payment_method` removida do papel autenticado (config/sync e runtime).
- Em "Meus pedidos" há a caixa "Todos os nossos pagamentos são processados pelo Mercado Pago" com link para a conta
  do Mercado Pago. A URL é a configuração `aculta_portal.settings:mercado_pago_account_url` (vazia oculta o link).
  A logo é um asset de marca do tema (`assets/brands/mercado-pago/`).
- Não alterado: o apoio (Meu Apoio e contribuições do apoio, que usam o histórico de pagamentos do Commerce), a etapa
  de pagamento do checkout (`/finalizar-compra/{id}/pagamento`), o webhook do gateway e a view administrativa
  `commerce_order_payments` (`/admin/commerce/orders/{pedido}/payments`).

## Campos do endereço (lista final, 2026-10-12)

Endereço único do cliente (Meus dados > Endereço, checkout, revisão, pedido concluído, detalhe do pedido). Campos usados:

| Campo | Rótulo | Onde fica |
| --- | --- | --- |
| CEP | CEP | `postal_code` (autocomplete ViaCEP, com fallback BrasilAPI) |
| Nome | Nome | `given_name` |
| Sobrenome | Sobrenome | `family_name` |
| Logradouro | Logradouro | `address_line1` (o número fica junto, ver abaixo) |
| Número / S/N | Número, marca S/N | subcampos do endereço (`StreetNumberField`); gravado no logradouro como "Rua, 1068" ou "Rua, S/N" |
| Complemento | Logradouro, linha 2 | `address_line2` |
| Bairro | Bairro | `dependent_locality` |
| Cidade | Cidade | `locality` |
| Estado | Estado | `administrative_area` |
| País | País | `country_code` (Brasil) |

**Removidos:** Empresa (`organization`). Ficou configurado como `hidden` em `field_overrides` da instância
`profile.customer.address` (e `profile.participante.field_address`), exportado em `config/sync`. Não é CSS e não é
`#access` em código. Assim o campo some da edição, do checkout, da revisão, do detalhe do pedido e de qualquer exibição
do Commerce (inclusive o Commerce Shipping quando instalado, que usa o mesmo tipo de perfil). Também saiu `addressLine3`
(já estava oculto). Valores antigos de Empresa em pedidos de teste foram descartados pela regra; não aparecem mais.

**Payer do Mercado Pago:** o módulo `commerce_mercado_pago` monta o payer com nome, sobrenome e endereço (CEP, logradouro,
bairro, cidade, estado, país). Não envia Empresa.

**Teste da próxima rodada:** conferir que "Empresa" não aparece em Meus dados > Endereço, no checkout (informações e
revisão), no detalhe de /pedidos/{id} e nos e-mails/recibos; e que o endereço do payer no Mercado Pago tem só os campos acima.

## Campos obrigatórios do endereço (2026-10-12)

- **Obrigatórios:** CEP, Nome, Sobrenome, Logradouro, Número (ou marca S/N), Bairro, Cidade, Estado e País.
- **Opcionais:** Complemento.
- **Bairro:** `field_overrides.dependentLocality = required` na instância `profile.customer.address` (e no participante),
  em `config/sync`. A validação é do servidor e a mensagem é "O campo Bairro é obrigatório.". O rótulo leva o asterisco
  (`form-required`, do Bootstrap). O preenchimento pelo CEP continua; se o ViaCEP não trouxer bairro, a pessoa digita.
- **Checkout:** "Continuar para a revisão" bloqueia com endereço salvo sem bairro e reabre a etapa em edição (igual ao
  Número/S/N). Se o campo não vier no envio, `validatePaneForm` confere o endereço salvo.
- **Atenção ao configurar:** no módulo `address`, quando a instância tem a lista `fields`, `field_overrides` é ignorado.
  Por isso a lista `fields` foi removida e tudo está em `field_overrides` (oculto ou obrigatório).
- **Teste:** Bairro vazio em Meus dados > Endereço não salva; no checkout, Bairro vazio bloqueia e o asterisco aparece.


## Envio com o endereço único (2026-10-12)

**Decisão do responsável:** o envio usa o mesmo endereço único do cliente. Não existe endereço de envio separado nem a
opção "cobrança igual ao envio": o formulário é um só (pane "Endereço") e o pane "Envio" o reaproveita.

- **Pane "Envio"** (`aculta_shipping_information`, classe `AcultaShippingInformation`, subclasse do pane do Commerce
  Shipping): mostra a escolha do método de envio. O formulário de endereço do Commerce não aparece. O perfil de envio é a
  cópia do endereço de cobrança do pedido (`BillingAddressPane::billingCopy()`), que segue o perfil do cliente.
- **Campos Número/S/N e Bairro**: só existem no pane "Endereço". Por isso valem também para o envio.
- **Mercado Pago**: o payer continua vindo da mesma cópia de cobrança.
- **Produtos físicos**: a variação de camiseta (`camiseta_tamanho`) tem o trait `purchasable_entity_shippable`. Apoio,
  doação e cursos não têm o trait, então o pedido só pede envio quando há item físico.
- **Método de envio:** taxa fixa "Envio", criada no runtime com valor **R$ 0,00 (a definir pelo responsável)**. Enquanto o
  valor não for definido, o frete aparece como grátis.
- **Loja:** países de envio = Brasil (`shipping_countries`).
- **Dependências:** `aculta_portal` depende de `commerce_shipping` e `physical` (composer: `drupal/commerce_shipping` 3.0.5,
  `drupal/physical` 1.5.0).

**Configuração de loja e de método é conteúdo, não configuração exportável.** O `commerce_store` e o
`commerce_shipping_method` são entidades de conteúdo: não entram em `config/sync`. Produção precisa criá-los no
provisionamento (país de envio BR, método "Envio" com o valor definido).

**Pendências:** valor real do frete; teste de navegador com pedido novo (aguardando o pagamento do responsável);
configuração em produção.
