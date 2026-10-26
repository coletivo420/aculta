## 0.1.0-dev.11 — serviços de pagamento e recorrência no módulo próprio

- Serviços de pagamento, recorrência, gravação de assinaturas e notificações passam a ser declarados pelo módulo; IDs renomeados para `aculta_pagamentos_mercadopago.*`, sem alias.
- Canal de log passa a `aculta_pagamentos_mercadopago`. Os valores persistidos e regras da integração permanecem iguais.
- Testes do checkpoint executados; resultado final será registrado após validação.

## 0.1.0-dev.10 — extração das regras puras e fábrica do SDK

- Move `MercadoPagoApiException`, `RecurrenceRequestBuilder`, `MercadoPagoClientFactory` e `MercadoPagoPaymentPolicy` para o módulo Mercado Pago; seus três testes também passam a pertencer ao módulo.
- Mantidos IDs de serviço atuais até o passo 2; comportamento e valores persistidos não mudam.
- Verificações do checkpoint registradas após execução.

# Changelog — aculta_pagamentos_mercadopago

## Regressão PR #298 — 2026-10-26

- Unit: 11 testes / 22 assertions PASS. Kernel `CheckoutProTest`: 1 teste / 50 assertions PASS, 76 depreciações Core/contrib e 2 PHPUnit. A execução usou código do worktree e MariaDB dedicado; nenhum SDK remoto, credencial ou pagamento foi acionado.

## Revisão PHPCS M-0 (2026-10-26)

- PHPDoc de `CheckoutPro` atualizado para refletir os parâmetros existentes;
  removida a variável de iteração sem uso, sem alterar a seleção de métodos.
- PHPCS Drupal/DrupalPractice no arquivo: zero erros; permanecem avisos antigos
  de linhas longas e tradução dinâmica. Unit: 11 testes / 22 assertions PASS;
  Kernel: 1 teste / 50 assertions PASS, com depreciações Core/contrib.
- PHP lint PASS. Nenhum pagamento, credencial ou serviço externo foi utilizado.

## 0.1.0-dev.8 — compatibilidade explícita com retorno parcial — 2026-10-26

- A assinatura de `refundPayment()` declara `?Price`, conforme a interface do Commerce instalada, sem alterar o comportamento do gateway.
- Metadata do gateway migrada da annotation deprecada para `CommercePaymentGateway` attribute, preservando o plugin ID, modos, formulário e tipos de cartão.
- O teste Kernel usa processo isolado conforme Drupal 11.3. Após a migração, Kernel: 1 teste / 50 assertions PASS, 76 depreciações Core/contrib e 2 PHPUnit; a annotation própria não aparece mais.

## 0.1.0-dev.7 — popup de retorno rejeita controles no destino — 2026-10-19

- `CheckoutPopupReturnController` rejeita controles e espaços no destino, porque o navegador remove tabs e quebras de linha dentro de URLs (`/\t/host` vira `//host`). Casos novos no teste Unit.
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.6 — marcação do checkout e das credenciais por token — 2026-10-19

- Aviso do checkout (`checkout-help`) e fieldsets de credenciais (`mercado-pago-credentials-*`) usam `data-portal-ui`. O mapa das classes fica no tema.
- Pendência: o estilo das credenciais continua em `css/gateway-admin.css` deste submódulo, e deveria ir para o tema.
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.5 — retorno do popup e onReturn endurecidos — 2026-10-19

- Popup de retorno aceita só caminho do mesmo site ou URL de mesma origem, e grava o destino como literal JSON seguro (`JSON_HEX_*`). Teste Unit `CheckoutPopupReturnControllerTest`.
- `onReturn` falha fechado sem vínculo de pedido (usa `external_reference` do pagamento), registra o `transaction_amount` confirmado (não o saldo) e preserva `preference_id`.
- AJAX de país usa `getUserInput()` em vez de `\Drupal::request()`.
- Evidência: Unit 8 testes OK. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.4 — evento de elegibilidade de estorno; sem regra de negócio — 2026-10-18

- Novo evento `aculta_pagamentos_mercadopago.refund_eligibility` (`RefundEligibilityEvent`). O gateway pergunta a assinantes se o estorno é permitido; o gateway não conhece doação nem curso.
- `canRefundPayment()` nega a operação de estorno quando um assinante marca `deny()`. `refundPayment()` recusa com a mesma regra, então a interface e a API não divergem.
- Cancelamento de pagamento pendente não passa por este evento; é operação separada.
- Dependência nova: `CheckoutPro::create()` injeta `event_dispatcher`.
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild; plugin do gateway conferido). Testes: Kernel OK.

## 0.1.0-dev.3 — método de pagamento único `aculta_mercadopago_checkout_pro`; schema corrigido — 2026-10-18

- O ID do tipo de meio de pagamento e do plugin do gateway passa de `mercado_pago_checkout_pro` para `aculta_mercadopago_checkout_pro`. É o único método de pagamento do Portal. O ID do gateway (`mercado_pago`) não muda.
- Schema: `payment_types` e `payment_method` eram arrays sem `sequence`; o teste Kernel falhava com `SchemaIncompleteException`. O mesmo problema existia no contrib.
- Dados já existentes com o ID antigo precisam de migração antes de desinstalar o contrib (ver a entrega do aculta_portal).

## 0.1.0-dev.2 — duplicação de credenciais removida — 2026-10-18

- `RedirectCheckoutForm` repetia a escolha de token e de chave pública por modo que `CheckoutPro` já tinha. Agora usa `CheckoutPro::getAccessToken()` e `getPublicKey()`, que passam a ser públicos.
- Quando o plugin não é `CheckoutPro`, o formulário recusa com a mesma mensagem de "não configurado" (antes não havia checagem de tipo).
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.1 — porte do commerce_mercado_pago — 2026-10-18

- Porte do `drupal/commerce_mercado_pago` 3.0.0-rc3 para o submódulo: plugin `CheckoutPro` (`aculta_mercadopago_checkout_pro`), tipo de meio de pagamento, formulário de redirecionamento, controlador de retorno de popup, mapa de meios de pagamento, bibliotecas, schema e teste Kernel.
- Namespace `Drupal\aculta_pagamentos_mercadopago`; rotas, bibliotecas e canal de log renomeados. IDs de plugin, chave de dados do pedido e caminho de retorno mantidos.
- Documentação e licença do upstream preservadas (`README.original-commerce_mercado_pago.md`, `LICENSE.txt`).
- Ainda não habilitado nem referenciado pelo `aculta_portal`: a troca de dependência e a remoção do contrib vêm nas entregas seguintes.
- RUNTIME STATUS: DEFERRED (não instalado no Runtime de teste).
## 0.1.0-dev.9 — usar Webhooks assinados no checkout — 2026-10-26

- O formulário de checkout deixou de adicionar `source_news=ipn` à
  `notification_url`. Esse parâmetro força o canal IPN legado, não validável
  por assinatura; o guard central do Portal aceita somente Webhooks modernos
  assinados, valida `x-signature` e normaliza o payload antes do Commerce.
- PHPCS e testes Unit do guard do Portal verificam os contratos de assinatura,
  normalização e recusa fail-closed de IPN. Nenhuma credencial, chamada externa,
  pagamento ou Runtime foi utilizado.
- PHP lint PASS. PHPCS Drupal e DrupalPractice no `RedirectCheckoutForm`: zero
  erros; seis avisos antigos de linhas acima de 80 caracteres permanecem.

