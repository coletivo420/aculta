# ADR-024 — Webhooks modernos assinados do Mercado Pago

**Status:** vigente desde 2026-10-26.  
**Escopo:** notificações do Mercado Pago processadas pelo Portal e seu adapter Commerce.  
**Origem:** `0.1.0-dev.9`, [histórico arquivado de aculta_pagamentos_mercadopago](../history/changelog/aculta_pagamentos_mercadopago.md).

## Contexto

O fluxo legado IPN não oferece a assinatura exigida pelo guard compartilhado.
Aceitá-lo permitiria que uma notificação não autenticada alcançasse a
conciliação de pedidos e pagamentos.

## Decisão

1. O checkout fornece a URL de notificação sem `source_news=ipn` e usa Webhooks
   modernos assinados.
2. O guard exige `x-signature` e `x-request-id`, valida a assinatura e só então
   normaliza os dados para os consumidores Commerce.
3. IPN legado e notificações sem assinatura válida falham fechados e não alteram
   pedidos nem pagamentos.

## Evidência vigente

`WebhookGuard` valida os cabeçalhos e a assinatura antes da normalização; a
configuração do Checkout Pro não força o canal IPN. O procedimento geral de
integrações permanece em
[integracoes-externas.md](../architecture/integracoes-externas.md).
