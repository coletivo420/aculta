# Arquitetura do aculta_pagamentos_mercadopago

O módulo adapta Checkout Pro e operações do Mercado Pago ao contrato de
pagamentos do Drupal Commerce ACULTA. A ordem, matrícula, doação e elegibilidade
de estorno continuam sob os módulos proprietários do negócio.

## Decisões vigentes

- **2026-10-18 — Um único método Commerce do Portal: Checkout Pro ACULTA.**
  Situação: vigente. Motivo: manter um único tipo de pagamento próprio, sem
  duplicar o método de gateway legado. Origem: `0.1.0-dev.3`,
  [histórico arquivado](../history/changelog/aculta_pagamentos_mercadopago.md).
  `aculta_mercadopago_checkout_pro` é o tipo do plugin e do método; o ID do
  gateway configurado continua `mercado_pago`.
- **2026-10-26 — Webhooks modernos assinados; IPN legado é recusado.** Situação:
  vigente. Motivo: processar notificações autenticadas pelo guard comum e evitar
  um callback sem assinatura verificável. Origem: `0.1.0-dev.9`, [histórico
  arquivado](../history/changelog/aculta_pagamentos_mercadopago.md); contrato
  transversal em [ADR-024](../decisions/ADR-024-webhooks-mercado-pago-assinados.md).
  `WebhookGuard` exige e valida `x-signature` antes de normalizar o payload.

## Fronteiras

- O módulo não possui pedidos nem define regras de estorno do negócio.
- Segredos vêm do provider ambiental; logs e respostas não expõem credenciais
  nem corpos privados do provedor.
