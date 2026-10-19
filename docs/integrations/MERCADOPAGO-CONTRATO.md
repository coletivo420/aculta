# Mercado Pago: contrato da integração Checkout Pro

Documento de auditoria da integração. Registra o que a documentação oficial confirma, o que o código faz e o que ainda não foi confirmado. Não contém credenciais.

## Escopo

Checkout Pro é a única forma de pagamento do Portal (`aculta_mercadopago_checkout_pro`). Todos os fluxos passam por ele: loja, doações avulsas, doações recorrentes, cursos, cancelamento, estorno e retorno de notificações. Os clientes do SDK `mercadopago/dx-php` (Point, OAuth, Orders, Customer, Invoice, Chargeback, PreApprovalPlan) são usados como apoio dessa integração, não como formas de pagamento separadas.

## Fontes

Confirmadas por leitura da página oficial:
- Webhooks, Brasil: https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
- Estornos (Checkout Pro), Argentina: https://www.mercadopago.com.ar/developers/en/docs/checkout-pro-preferences/additional-settings/refunds-and-cancellations
- Assinatura com plano associado, Brasil: https://www.mercadopago.com.br/developers/pt/docs/subscriptions/integration-configuration/subscription-associated-plan
- Assinatura sem plano com pagamento pendente, Brasil: https://www.mercadopago.com.br/developers/en/docs/subscriptions/integration-configuration/subscription-no-associated-plan/pending-payments

Encontradas em busca e não lidas por completo (acesso negado ou sem conteúdo):
- Referência de preferências: https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro-preferences/create-preference/post
- Exclusão de meios de pagamento: https://www.mercadopago.com.uy/developers/en/docs/checkout-pro-preferences/additional-settings/payment-methods
- Notificações Checkout Pro: https://www.mercadopago.cl/developers/en/docs/checkout-pro-preferences/additional-content/notifications/webhooks (403 na leitura)

## Confirmado na documentação

**Webhooks**
- Cabeçalho `x-signature` no formato `ts=<timestamp>,v1=<hash>`. O cabeçalho `x-request-id` acompanha a notificação.
- Validação pelo SDK oficial com o segredo do aplicativo. O SDK monta o manifesto `id:<data.id>;request-id:<x-request-id>;ts:<ts>;` e compara o HMAC-SHA256. O código do Portal usa `MercadoPago\Webhook\WebhookSignatureValidator` do SDK, então segue o algoritmo oficial.
- O `data.id` vem da query string nos exemplos oficiais.
- Tópicos listados na documentação: `orders`, `payment`, `subscription_authorized_payment`, `subscription_preapproval`, `subscription_preapproval_plan`, `topic_merchant_order_wh`, `topic_chargebacks_wh`, entre outros.
- Resposta esperada: 200 ou 201 em até 22 segundos. Sem confirmação, o Mercado Pago reenvia a cada 15 minutos.
- Recomendação oficial: validar, responder, e só então buscar o recurso pela API (`GET /v1/payments/{id}`) antes de atualizar o sistema.

**Estorno**
- `POST /payments/{id}/refunds`. Estorno total com corpo vazio; parcial com o valor no corpo.
- Prazo de 180 dias após a aprovação; a conta precisa de saldo disponível.
- Compras com dois meios geram um `payment_id` por meio. Estorno total do pedido exige estornar cada pagamento vinculado ao `merchant_order`.
- O SDK envia `X-Idempotency-Key` automaticamente em `POST`.

**Assinatura com plano associado**
- Plano: `POST /preapproval_plan`, com `reason`, `auto_recurring`, `payment_methods_allowed` e `back_url`. O `id` da resposta é o `preapproval_plan_id`.
- Assinatura: `POST /preapproval` com `preapproval_plan_id`, `payer_email` e `back_url`.

**Assinatura sem plano com pagamento pendente**
- `POST /preapproval` com `status: "pending"`, sem `card_token_id`. O comprador conclui o pagamento por um link de pagamento compartilhado.
- Isso confere com o código: `RecurrenceRequestBuilder::assinatura()` envia `status => 'pending'` e não envia cartão.

## Verificado no código

| Área | Como está | Situação |
| --- | --- | --- |
| Webhook | `WebhookGuard` valida a assinatura com o SDK e rejeita tópico legado sem assinatura | Alinhado |
| Estorno | `CheckoutPro::refundPayment()` usa `refundTotal` quando o valor cobre o pagamento, senão `refund` parcial | Alinhado |
| Cancelamento | `MercadoPagoPaymentService::cancelar()` confere o status remoto e chama `PaymentClient::cancel()` do SDK | Alinhado; o endpoint exato não foi confirmado na documentação lida |
| Assinatura pendente | `status => 'pending'`, sem cartão | Alinhado |
| Status de assinatura alteráveis | `RecurrenceRequestBuilder::STATUS_ALTERAVEIS = paused, authorized, cancelled` | Confirmar com a referência de `PUT /preapproval/{id}` |

## Não confirmado

- **Cancelamento de pagamento aprovado ou pendente:** a página lida trata só de estorno. O endpoint de cancelamento usado (`PaymentClient::cancel`) precisa ser confirmado na referência oficial de pagamentos.
- **Tópicos de assinatura:** os nomes `subscription_preapproval` e `subscription_authorized_payment` estão na tabela de tópicos, mas o payload de cada um não foi lido.
- **Efeito do cancelamento sobre a preferência e o pedido:** não documentado na página lida.
- **Exclusão de meios de pagamento:** a regra "Cash in account não pode ser excluído" vem de uma página de outro país; confirmar para o Brasil.
- **`notification_url` por preferência:** o código envia `notification_url` com `?source_news=ipn`. A página de referência não foi lida; confirmar se a notificação é a configurada no aplicativo ou a da preferência.

## Próximos passos

1. Ler a referência oficial de pagamentos (cancelamento) e de preapproval (`PUT`), e fechar os itens "não confirmado".
2. Testar no sandbox: estorno parcial e total, cancelamento de pagamento pendente, assinatura pendente com link de pagamento, e recebimento dos tópicos de assinatura.
3. Só então tratar o Runtime (ver `CHANGELOG` do `aculta_portal`).

## Verificações em 2026-10-19 (registro)

- **Credencial configurada como teste** (`access_token_test`): pertence ao vendedor de teste `3735929876`, aplicativo `8616430635467445`. A API recusa criar pagamento e estorno com ela (401, `Unauthorized use of live credentials`), mesmo com comprador de teste e cartão de teste. Não é possível testar pagamento de sandbox com ela.
- **Chave de produção salva** (`mercadopago_production_access_token`): pertence ao aplicativo `CheckoutProacultaorg` (`1573840918145296`), dono `3638510484`, conta real da ACULTA. Não enxerga os pagamentos do vendedor de teste (404).
- **Pagamento real de R$ 1,00** pela preferência de produção `3638510484-a268b814-2f08-4151-8985-0e152c01b163`: pagamento `184952248830`, aprovado (`live_mode=true`), estornado totalmente pelo estorno `3395210317` (status `refunded`).
- **Pagamentos de teste de R$ 30,00** (pedidos 6, 7 e 13; IDs `183617491071`, `183758594246`, `183741511220`): continuam aprovados no Mercado Pago, sem estorno. Não é possível excluí-los pela API. O registro local do pagamento do pedido 13 foi excluído do Commerce; os pedidos foram mantidos.
- **Orientação documentada** (MCP, documentação MLB): produto que não exige credenciais de teste deve usar credenciais de produção de uma conta de teste. Essa credencial ainda não está disponível no Runtime.
