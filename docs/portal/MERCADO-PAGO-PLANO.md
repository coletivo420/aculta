# Mercado Pago: inventário de APIs, recorrência e plano de integração

Status: **plano de trabalho** para a linha 0.4.0-dev.65 (`aculta_portal`). Painéis visuais (vendas, loja e produto) ficam para a fase seguinte, por decisão do responsável.

Base: SDK `mercadopago/dx-php` 3.16.0 instalado em `vendor/`; gateway `commerce_mercado_pago` 3.0.0-rc3 (Checkout Pro).

## 0. Decisões do responsável (2026-10-16)

- **Cancelar e estornar:** somente o gerente de loja (papel `gerente_loja`, permissão `gerenciar pagamentos de vendas`). A permissão já está concedida ao papel em `config/sync`. A tela de ações entra na fase de painéis.
- **Doação recorrente:** pela API de assinaturas do Mercado Pago (`preapproval`); a pessoa autoriza e acompanha a assinatura no Mercado Pago. Não usar `commerce_recurring`.
- **Assinatura de produto da loja:** fora desta fase.
- **Relatório de vendas:** exclui doações. Itens com `commerce_order_item.type = donation` ficam fora de todas as agregações; um pedido entra quando tem ao menos um item de produto.
- **Migração de tokens para Drupal Key:** **não autorizada.** Os tokens de teste ficam onde já estão (configuração do gateway `mercado_pago`, modo `test`) e são usados pela aplicação. A migração não entra nesta linha.

## 0.1 Atualização: gerente de loja administra doações (dev.66)

- Permissão `administrar doacoes` (papel `gerente_loja`) e rota `/painel-administrativo/loja/doacoes` com o relatório de doações por período e moeda.
- `SalesReportQuery` recebe o escopo (`produto` ou `doacao`). Vendas e doações ficam em relatórios separados, mesmo quando um pedido mistura os dois.
- Ações de pagamento de doação (estornar, cancelar) usam a permissão `administrar doacoes`, e não `gerenciar pagamentos de vendas`. A tela entra na fase de painéis.
- Cancelar a assinatura de uma doação recorrente exige guardar o id da assinatura no pedido. Isso é a F3 do plano.

## 0.2 Pendências resolvidas (dev.67)

| Pendência | Situação |
|---|---|
| Ligar relatório de vendas à consulta | Feito. Controller usa `SalesReportQuery`; dívida de carga em memória removida |
| Testes Kernel de vendas | Feito: 6 casos em `aculta_simpletest`, passam |
| Testes dos serviços do SDK | Feito, com cliente substituído por duplo (sem rede) |
| Permissões no Runtime | Feito por escopo do papel `gerente_loja`; import completo de config não feito |
| Tópicos de webhook | Confirmados: `subscription_preapproval`, `subscription_preapproval_plan`, `subscription_authorized_payment` |
| Webhook com verificação de assinatura | **Bloqueado**: exige segredo do aplicativo (credencial nova, Key). Sem autorização |
| URL de notificação | **Bloqueado**: precisa de entrada em `environment_bound` do `aculta_deployer` |
| Vínculo assinatura ↔ pedido de doação (F3) | **Bloqueado**: depende do webhook e do desenho do pedido de doação |
| Painéis e ações de pagamento (F1) | Adiado pelo responsável para a fase visual |

## 1. Inventário das APIs do SDK

O SDK tem 21 arquivos `*Client.php`: 20 clientes concretos e a classe base `MercadoPagoClient`. A fábrica `MercadoPagoClientFactory::CLIENTES` mapeia os 20 e um teste confirma que as classes existem no SDK instalado.

| Alias | Cliente do SDK | Operações disponíveis | Situação nesta fase |
|---|---|---|---|
| `pagamentos` | PaymentClient | create, get, update, cancel, capture, search, searchAll | **Implementado**: consultar (`atualizar`) e cancelar. Capture e search: planejado |
| `reembolsos` | PaymentRefundClient | refund, refundTotal, get, list | Estorno continua pelo Commerce (`refundPayment` do gateway). Listar estornos: planejado |
| `pedidos_comerciante` | MerchantOrderClient | create, get, update, search | Planejado: conciliação de pagamentos |
| `contestacoes` | ChargebackClient | get, search | Planejado: painel de contestações (hoje só registra em log) |
| `assinaturas` | PreApprovalClient | create, get, update, search, searchAll | **Implementado**: criar, consultar, pausar/reativar/cancelar |
| `planos_assinatura` | PreApprovalPlanClient | create, get, update, search | **Implementado**: criar e consultar. Alterar e buscar: planejado |
| `preferencias` | PreferenceClient | Checkout Pro | Usado pelo gateway contrib; não chamado direto |
| `pedidos` | OrderClient | Orders API | Avaliar: API de pedidos nova do Mercado Pago |
| `transacoes_pedido` | OrderTransactionClient | Transações de pedido | Avaliar junto de `pedidos` |
| `pagamento_avancado` | AdvancedPaymentClient | Pagamento avançado | Avaliar: só se houver split |
| `token_cartao` | CardTokenClient | Tokenização de cartão | **Não usar**: o site não manipula dados de cartão (AGENTS.md) |
| `clientes` | CustomerClient | Clientes | **Não usar**: não há cartões salvos na conta (AGENTS.md) |
| `cartoes_cliente` | CustomerCardClient | Cartões do cliente | **Não usar**, pelo mesmo motivo |
| `estornos_desembolso` | DisbursementRefundClient | Estorno de desembolso | Avaliar |
| `tipos_identificacao` | IdentificationTypeClient | Tipos de documento | Não usado; o checkout contrib cuida disso |
| `faturas` | InvoiceClient | Faturas | Avaliar: pode servir a cobranças de assinatura |
| `maquinas_point` | PointClient | Maquininhas presenciais | **Fora de escopo** |
| `metodos_pagamento` | PaymentMethodClient | Lista de meios de pagamento | Não usado direto |
| `oauth` | OAuthClient | OAuth de integrador | **Fora de escopo** por ora |
| `usuarios` | UserClient | Dados da conta do vendedor | Planejado: diagnóstico de credencial no painel |

Inconsistência registrada: o docblock de `PaymentClient::cancel` diz que só `pending` pode ser cancelado, mas a documentação oficial de criação de cancelamento lista `in_process`, `pending` e `authorized`. A implementação segue a documentação (`MercadoPagoPaymentPolicy::CANCELAVEIS`). Se o Mercado Pago recusar um estado, a resposta de erro 2018 é tratada como falha segura.

## 2. Recorrência

**Confirmado na documentação e no SDK:**
- planos: `POST /preapproval_plan` (SDK `PreApprovalPlanClient::create`), com `reason`, `auto_recurring` (`frequency`, `frequency_type`, `transaction_amount`, `currency_id`) e `back_url`;
- assinaturas: `POST /preapproval` (`PreApprovalClient::create`), com `preapproval_plan_id` ou `auto_recurring` próprio, `payer_email`, `external_reference`, `back_url` e `status: pending`;
- a assinatura nasce em `pending`, e o pagador a autoriza pelo `init_point`;
- alteração de status por `PUT /preapproval/{id}`, com `paused`, `authorized` ou `cancelled`.

**Não confirmado (lacuna da pesquisa):** o recurso `authorized_payments` (cada cobrança de uma assinatura) e os tópicos de webhook exatos para assinaturas. A pesquisa retornou `subscription_preapproval` como evento de assinatura, mas não confirmou o tópico de cobrança recorrente. Antes de implementar o webhook, consultar a referência oficial do Mercado Pago do país do site (Brasil) e fixar os tópicos.

**Implementado nesta fase:**
- `RecurrenceRequestBuilder`: valida valor (decimal, > 0, até duas casas), moeda (BRL), frequência (1 a 12 meses), `back_url` (https com host), e-mail do pagador e referência. Testado em unidade.
- `MercadoPagoRecurrenceService`: `criarPlano`, `obterPlano`, `criarAssinatura`, `obterAssinatura`, `alterarStatusAssinatura`.

**Decisão:** a recorrência usa a API de assinaturas do Mercado Pago, não o módulo `commerce_recurring`. Motivo: `commerce_recurring` não está habilitado no Runtime, e o cobrador de recorrência do Commerce não tem cobrança via Mercado Pago. Isto precisa de confirmação do responsável (pergunta 2 da seção 6).

## 3. Pagamentos: consultar, cancelar e estornar

| Ação | Como | Regra |
|---|---|---|
| Atualizar status | `PaymentClient::get` e `decidirSincronizacao` | Espelha `remote_state`, o valor estornado e os estados `completed`, `partially_refunded`, `refunded` e `authorization_voided` |
| Cancelar | `PaymentClient::cancel` (PUT com `status: cancelled`) | Só com status remoto em `pending`, `in_process` ou `authorized` e estado local `authorization`. Transição local `void` |
| Estornar | `refundPayment` do gateway, pela operação `refund-payment` do Commerce | Total ou parcial, só em `completed` e `partially_refunded` |
| Contestação | Só registro em log | Exige tratamento manual |

Pagamentos do Checkout Pro entram como `authorization` e viram `completed` na aprovação (IPN ou retorno). Por isso o cancelamento de um pendente usa a transição `void` nativa do Commerce.

Os painéis com botões de cancelar, estornar e atualizar ficam para a fase visual. Cancelar e estornar são concedidos ao gerente de loja (permissão `gerenciar pagamentos de vendas`, papel `gerente_loja`).

## 4. Relatório de vendas

- `SalesReportQuery` lê `commerce_order` e `commerce_order_item` direto, sem tabela paralela. Assim, estorno e cancelamento aparecem sem regerar dados.
- `SalesReportFilters` trata datas (`de`, `ate`), loja (`loja`) e granularidade (`granularidade`), com limite de 366 dias.
- A sintaxe de data é MariaDB, o banco de produção. Portabilidade é do DBTNG-2.
- Ainda não ligado ao controller: `SalesReportController` continua com a agregação antiga. A ligação faz parte da fase visual.

Origem das ideias: o `commerce_reports` (GPL-2.0-or-later) inspirou a granularidade por período e a estrutura de tipos de relatório. Nenhum arquivo foi copiado literalmente; a versão do projeto usa consultas próprias sobre as tabelas do Commerce, por decisão de não criar armazenamento paralelo (AGENTS.md).

## 5. Plano de integração

### 5.1 Com a loja (produtos e pedidos)

- Compras avulsas continuam no Checkout Pro, pelo `commerce_mercado_pago`. Nada muda no fluxo de pagamento.
- Assinatura de produto da loja (ex.: clube de assinatura) **não está no escopo** até decisão do responsável (pergunta 3).
- Pedidos da loja entram no relatório de vendas pela consulta da seção 4.

### 5.2 Com doações (`commerce_donation_flow`)

O módulo já tem a opção `recurring` (campo `field_gift_type`), o valor mensal (`field_monthly`) e a data de início (`field_recurring_begins`). Hoje a opção só funciona com `commerce_recurring`, que não está ativo.

Fluxo proposto:
1. A doação recorrente cria um item de pedido com valor e frequência mensal.
2. O Portal chama `MercadoPagoRecurrenceService::criarAssinatura` com o e-mail do pagador (do checkout), a referência igual ao id do pedido e a `back_url` do ambiente.
3. A pessoa é redirecionada ao `init_point` e autoriza no Mercado Pago.
4. Webhook de assinatura atualiza o pedido e cria um pagamento local por cobrança aprovada.
5. Em "Meu apoio", a pessoa pode pausar ou cancelar a assinatura; a alteração chama `alterarStatusAssinatura`.

Pontos que dependem de confirmação: tópicos de webhook (seção 2), e se o pedido de doação recorrente deve ser um pedido por cobrança ou um pedido único que agrupa as cobranças.

### 5.3 Ambiente e endereços

- `back_url`, `notification_url` e o domínio de retorno são endereços dependentes de ambiente. Devem ir para `environment_bound` do `aculta_deployer` (`deploy.json`), nunca para o código. O builder só aceita https com host.
- Gate: `php scripts/validate-environment-addresses.php` deve continuar PASS.

### 5.4 Credenciais

- Os tokens do gateway estão em configuração do módulo contrib (`access_token_test`, `access_token_stage`, `access_token_prod`). O AGENTS.md exige Drupal Key com provider `env`.
- **Decisão:** a migração para Drupal Key não foi autorizada. Nesta linha, a aplicação usa os tokens de teste já configurados. Antes de produção, a migração precisa de nova autorização do responsável.
- Esta fase lê o token, não o grava.

## 6. Perguntas

Respondidas em 2026-10-16; ver a seção 0.

## 7. Fases

| Fase | Conteúdo | Situação |
|---|---|---|
| F0 | Inventário, camada de API, recorrência, ações de pagamento, relatório (consultas) | Nesta linha 0.4.0-dev.65 |
| F1 | Painéis: vendas, loja e produto (visual, ações com confirmação) | Próxima, a pedido do responsável |
| F2 | Webhooks de assinatura e cobrança recorrente; tópicos confirmados | Depende da pergunta 2 e da pesquisa |
| F3 | Integração com doações (fluxo da seção 5.2) | Depende de F2 |
| F4 | Migração de credenciais para Drupal Key (não autorizada ainda); testes Kernel no banco `aculta_simpletest` | Antes de produção |

## 8. Testes e evidências

- Unit `aculta_portal`: 139 testes, 369 asserções, OK (dev.67) (inclui `RecurrenceRequestBuilderTest`, `MercadoPagoPaymentPolicyTest`, `SalesReportFiltersTest`, `MercadoPagoClientFactoryTest`).
- Gates: `validate-aculta-portal-drupal11`, `validate-module-theme-boundary`, `validate-module-theme-map` e `validate-environment-addresses` PASS.
- **Kernel (dev.67): feito.** `SalesReportQueryKernelTest`, 6 casos, no banco `aculta_simpletest`.
- **Runtime (verificado em 2026-10-16):** o gateway `mercado_pago` (plugin `mercado_pago_checkout_pro`, modo `test`) existe, com token de teste. Uma chamada somente leitura `UserClient::get` autenticou com o token de teste (site `MLB`). O Runtime carrega este checkout (`web/modules/custom/aculta_portal` aponta para o mesmo repositório). As consultas do relatório executam no MariaDB sem erro; o banco de teste não tem pedidos concluídos, então os valores não foram conferidos.
- **Runtime: DEFERRED** para: ações de cancelar e estornar (sem tela ainda), criação de planos e assinaturas (escrevem na conta de teste; aguardam confirmação), e conferência de valores do relatório (sem pedidos de teste).
