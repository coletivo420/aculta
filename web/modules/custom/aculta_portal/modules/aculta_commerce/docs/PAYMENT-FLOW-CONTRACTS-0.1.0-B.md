> **Retificação arquitetural — 2026-10-22:** O código `CartCheckoutRouteSubscriber` tem finalidade **intencional** de selecionar checkout dedicado/comum/ambos. B-01 deixa de ser tratado como remoção de funcionalidade e passa a ser a **migração da orquestração para `aculta_portal`**, com preservação de `donate|cart|both`, compatibilidade e controle contextual por pedido. Ver [ADR-013](../../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md). Os achados abaixo permanecem como pontos de caracterização e testes, não prova de erro operacional.

# ACULTA Commerce 0.1.0-B — Contratos de pagamento e isolamento

Data: 2026-10-22. Base: inspeção estática da branch `docs/aculta-forum-0.1.0-dev.1`. Nenhuma afirmação de PASS em runtime.

## Fonte da verdade e ownership

| Contexto | Entidade e checkout | Meio de pagamento | Interface |
|---|---|---|---|
| Loja | Drupal Commerce + regras da loja (`aculta_commerce`) | `aculta_pagamentos_mercadopago`, gateway configurado `mercado_pago` | Catálogo SHOP + Green Stash via Studio |
| LMS | `aculta_lms` e integração com Commerce, se habilitada no runtime | Mesmo gateway `mercado_pago` | GanjaLab420 / experiência LMS |
| Doações e apoio | `aculta_donation`, campanhas, fluxo e contratos de assinaturas | Mesmo gateway `mercado_pago` | Experiência de doações |

**Regra:** compartilhamento do gateway não implica compartilhamento de carrinhos, rotas comerciais, direitos de acesso, relatórios nem autorização de estorno.

## Código verificado

- `modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php`: plugin offsite de pagamento `aculta_mercadopago_checkout_pro`, reembolsos via `SupportsRefundsInterface`. Reutilizar, não replicar em Commerce.
- `modules/aculta_pagamentos_mercadopago/README.md`: gateway configurado `mercado_pago`, retorno e chaves de dados históricos mantidos; as integrações de loja/LMS/doação dependem desse contrato.
- `src/Commerce/MercadoPago/WebhookEventSubscriber.php`: intercepta apenas `commerce_payment.notify` com gateway `mercado_pago`; prioridade 29; valida secret Key de provider `env`, `mercadopago_webhook_secret` ligado a `MERCADOPAGO_WEBHOOK_SECRET`.
- `src/Commerce/MercadoPago/WebhookGuard.php`: exige POST, x-signature e x-request-id, identificador consistente, valida SDK; separa tipos de assinatura e pagamento; não delega notificações não suportadas.
- `modules/aculta_donation/src/EventSubscriber/CartCheckoutRouteSubscriber.php`: **achado crítico para caracterizar**. Quando a configuração `aculta_donation.settings:donation_route` não equivale a CART/AMBAS, modifica requisitos de acesso de `commerce_cart.page`, `commerce_checkout.checkout` e `commerce_checkout.form`. São rotas globais do Commerce; a influência sobre a loja e o LMS deve ser demonstrada no servidor. Não alterar sem verificar configurações reais e testes.
- `modules/aculta_statistics/src/Hook/CommerceOrderDomainHooks.php`: marca purpose em `commerce_order` novo sob chave `aculta_statistics_purpose`. Propósito é origem do pedido, **não classificação confiável do negócio**: checkout pode mudar de domínio; pedidos antigos podem não ter chave.
- `modules/aculta_statistics/src/EventSubscriber/CommerceOrderPaidSubscriber.php`: agrega pagamentos da entidade pedido para métricas. Cuidado: receita total agregada não equivale à receita da loja quando inclui tipos LMS/doação.

## Matriz de cenários de caracterização — executar no servidor

| Cenário | Loja | LMS | Doação | Expectativa |
|---|---|---|---|---|
| Compra autorizada | Produto e variação reais | Oferta de curso disponível | Campanha vigente | Checkout próprio e pedido atribuível ao contexto |
| Checkout anônimo/autenticado | Conforme política de loja | Matrícula/conta conforme LMS | Conforme política de apoio | Sessões e acessos coerentes |
| Webhook assinado `payment` | Pedido da loja | Pedido do LMS | Pedido de apoio | Atualização única e sem tipo cruzado |
| Webhook repetido ou fora de ordem | Sim | Sim | Sim | Idempotência e estado consistente |
| Webhook inválido / segredo ausente | Sim | Sim | Sim | Negar de modo fail-closed |
| Reembolso | Conforme política comercial | Conforme política educacional | Doação não reembolsável conforme `aculta_donation` | Política específica por tipo, sem regra genérica |
| Pedido sem purpose | Legado/importado | Legado/importado | Legado/importado | Não presumir loja nem conceder ação |
| Relatório no Green Stash | Somente loja autorizada | Não exibir dados privativos | Não exibir dados privativos | Filtro por contrato comercial + ACL no servidor |
| Rota global cart/checkout | Testar todas as opções `donation_route` | Idem | Idem | Nenhum bloqueio acidental de outros fluxos |

## Requisitos de classificação comercial

1. Produzir contrato `OrderBusinessContextResolver` futuramente, **derivado de tipo do pedido, item comprado, store e contratos específicos do owner**; jamais deduzir ownership exclusivamente do purpose da URL ou do gateway.
2. Priorizar política fail-closed em itens híbridos/mistos: declarar regra explícita, bloquear operação ambígua até contrato validado.
3. Manter referências de transação e reconciliação no Commerce/gateway; deduplicar eventos por ID remoto + tipo + resultado, conforme API existente.
4. As permissões do Green Stash são por ação e entidade: ver pedido, processar fulfillment, ver pagamentos, reembolsar. O acesso ao painel não substitui autorização transacional.
5. Nenhum webhook, token, endereço ou documento pessoal vai ao Content Index; produtos públicos podem ser indexados só após existir API efetiva.

## Gate 0.1.0-B no homelab

Capturar nomes/IDs de order types, order item types, checkout flows, payment gateways, shipments e stores, omitindo PII. Testar rotas `commerce_cart.page`, `commerce_checkout.checkout` e `commerce_checkout.form` para cada valor de `donation_route` (em banco isolado); inspeção de requisitos de acesso pós-rebuild. Gerar fixtures de checkout separadas por fluxo e de webhooks assinatura inválida, tipo inesperado, repetição, fora de ordem e exceções do SDK. Executar testes unitários/kernel existentes do gateway e donation; registrar PASS/FAIL e revisões.

## Bloqueador conhecido

**B-01:** `CartCheckoutRouteSubscriber` potencialmente afeta globalmente checkout de loja e LMS. A fase 0.1.0-C não deve modificar o comportamento dessas rotas sem primeiro provar o alcance real e definir correção isolada e reversível.
