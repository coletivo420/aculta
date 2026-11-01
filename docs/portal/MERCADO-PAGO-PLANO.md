# Mercado Pago: inventário de APIs, recorrência e plano de integração

**Ciclo vigente:** este plano segue [Dev → RC → deploy/validação Alpha → produção](../operations/RELEASE-LIFECYCLE.md). Concluir integração, testes/gates e revisão visual aplicável antes de RC; validar o candidato em Alpha antes da migração a produção.

Status canônico do submódulo: [aculta_pagamentos_mercadopago.yml](../operations/status/aculta_pagamentos_mercadopago.yml). Este documento contém decisões e evidências históricas abaixo; pendências atuais também constam no status do componente.

Base: SDK `mercadopago/dx-php` instalado em `vendor/`; gateway Commerce `mercado_pago`, plugin `aculta_mercadopago_checkout_pro` (Checkout Pro).

## 0. Decisões do responsável (2026-10-16 e 2026-10-17)

- **Recorrência (2026-10-17):** só a API de assinaturas do Mercado Pago (preapproval), com reaproveitamento de código do `commerce_recurring` 8.x-1.0-rc3 (workflow `order_recurring`, copiado para `aculta_donation`). Resolve a pergunta 2 da seção 6. Os detalhes estão em `aculta_donation/docs/PORT-COMMERCE-DONATION-FLOW.md`.

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

## 0.0 Correção de 2026-10-16 (leia antes do resto)

- O prefixo do token não identifica o ambiente: credenciais `APP_USR-` podem ser de teste. Decidir pelo modo configurado e pelo `live_mode`. A recusa da API (401 em `POST /v1/payments`, "Unauthorized use of live credentials") segue como evidência aberta no DT-MP-09. As afirmações anteriores de "ambiente de teste autenticado" valem só para a conta vinculada a essa aplicação. Ver DT-MP-09.
- Não criar pagamentos nem assinaturas de teste até o gateway ter credenciais `TEST-`. O plano `763715780e834403864cf3c154e3167b` foi criado com essas credenciais; cancelar depende de autorização.

## 0.2 Pendências resolvidas (dev.67)

| Pendência | Situação |
|---|---|
| Ligar relatório de vendas à consulta | Feito. Controller usa `SalesReportQuery`; dívida de carga em memória removida |
| Testes Kernel de vendas | Feito: 6 casos em `aculta_simpletest`, passam |
| Testes dos serviços do SDK | Feito, com cliente substituído por duplo (sem rede) |
| Permissões no Runtime | Feito por escopo do papel `gerente_loja`; import completo de config não feito |
| Tópicos de webhook | Confirmados: `subscription_preapproval`, `subscription_preapproval_plan`, `subscription_authorized_payment` |
| Webhook com verificação de assinatura | Implementado: segredo via Key/env e verificação oficial HMAC; Kernel rejeita segredo ausente/inválido e gateway desabilitado. Recebimento de todos os tópicos ainda depende da configuração da aplicação e homologação sandbox |
| URL de notificação | Checkout Pro usa a rota nativa `commerce_payment.notify` por `notification_url`, sem IPN legado. Webhooks de assinatura são encaminhados ao mesmo endpoint; habilitação de tópicos no painel ainda requer comprovação |
| Vínculo assinatura ↔ pedido de doação (F3) | Implementado em `aculta_donation`: associação sob lock, referência do pedido, reconciliação por versão e registro deduplicado de cobranças aprovadas. Testes Unit/Kernel passam; homologação ponta a ponta aguarda comprador sandbox autenticado |
| Painéis e ações de pagamento (F1) | Código de vendas/doações, escopo de permissões e ações implementados. Revisão visual autenticada com pedidos de teste e operação sandbox permanecem pendentes |

## 0.3 Webhooks de assinatura (registro histórico; implementação evoluiu)

- Segredo: Key `mercadopago_webhook_secret` (env `MERCADOPAGO_WEBHOOK_SECRET`), já no contrato de segredos. Autorizado pelo responsável.
- Verificação: `WebhookSignatureValidator` do SDK, sem cópia própria.
- Endpoint: o mesmo da notificação do gateway, `/integracoes/pagamentos/mercado_pago/notificacao` (a rota é reescrita pelo Portal). Nenhuma URL nova no código.
- O código atual consulta assinatura, plano e cobrança (`GET /authorized_payments/{id}`), valida IDs e integra a gravação com o Commerce. Ver detalhes atuais em `docs/integrations/MERCADOPAGO-CONTRATO.md`.
- Verificado no Runtime: sem assinatura e com assinatura inválida, 401; assinatura válida com id inexistente, 500 com registro em log.
- Caminho de sucesso verificado com plano real de teste (`subscription_preapproval_plan`): 200, plano consultado (status `active`). Plano criado na conta de teste; não pode ser apagado pela API.
- Assinatura de teste (`subscription_preapproval`): **verificada em 2026-10-16** com comprador de teste criado por `POST /users/test_user` (e-mail `test_user_…@testuser.com`; o painel não mostra e-mail). Assinatura `4e7c1e310e84410985a281431511bdd3`, status `pending`; notificação assinada consultada com 200. A cobrança (`subscription_authorized_payment`) depende de autorização do comprador pelo `init_point`.
- Bloqueio anterior (histórico): assinatura de teste (`subscription_preapproval`) bloqueada até obter o comprador. O Mercado Pago exige `payer_email` de um comprador diferente da conta vendedora, e o e-mail do usuário de teste não é retornado pela API. Falta o e-mail do comprador de teste, que está no painel (Suas integrações > aplicação > Contas de teste).
- Diagnóstico registrado: criar assinatura com `preapproval_plan_id` exige `card_token_id` (não usado pelo projeto); sem plano, a API recusou o pagador (`User bad request`).

## 0.4 Painéis (registro histórico da implementação; evidência visual atual pendente)

- Painéis de vendas e de doações com tabela de pedidos e ações por pagamento.
- Permissão por escopo do pedido (produto: `gerenciar pagamentos de vendas`; doação: `administrar doacoes`; misto: as duas).
- Apresentação no ACULTA420 aplicada em dev.70 (módulo) e 0.6.0-dev.67 (tema). Pendências: captura visual com administrador e pedidos de teste; Kernel do painel.

## 1. Inventário das APIs do SDK

O SDK tem 21 arquivos `*Client.php`: 20 clientes concretos e a classe base `MercadoPagoClient`. A fábrica `MercadoPagoClientFactory::CLIENTES` mapeia os 20 e um teste confirma que as classes existem no SDK instalado.

| Alias | Cliente do SDK | Operações disponíveis | Situação nesta fase |
|---|---|---|---|
| `pagamentos` | PaymentClient | create, get, update, cancel, capture, search, searchAll | **Implementado**: consultar (`atualizar`) e cancelar. Capture e search: planejado |
| `reembolsos` | PaymentRefundClient | refund, refundTotal, get, list | Estorno continua pelo Commerce (`refundPayment` do gateway). Listar estornos: planejado |
| `pedidos_comerciante` | MerchantOrderClient | create, get, update, search | Planejado: conciliação de pagamentos |
| `contestacoes` | ChargebackClient | get, search | Webhook assinado busca o caso e concilia o pagamento Commerce vinculado; painel de análise e envio de evidências seguem planejados |
| `assinaturas` | PreApprovalClient | create, get, update, search, searchAll | **Implementado**: criar, consultar, pausar/reativar/cancelar |
| `planos_assinatura` | PreApprovalPlanClient | create, get, update, search | Implementados no serviço: criar (exige ID de intenção único e estável para retries), consultar, alterar e buscar (páginas limitadas e IDs validados). A busca ainda não está conectada a uma interface operacional ACULTA. Cancelamento é manual no painel e irreversível |
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
- alteração de status por `PUT /preapproval/{id}`: o guia oficial documenta `paused` e `canceled`; a reativação enviada pelo código usa `authorized` e ainda precisa de homologação sandbox.

**Confirmado na documentação oficial brasileira:** tópicos `subscription_preapproval`, `subscription_preapproval_plan` e `subscription_authorized_payment` e consulta correspondente por API. Assinaturas também exigem ativar o tópico `payments` para conciliar os pagamentos associados. A ativação no painel e a entrega real ainda precisam ser comprovadas em sandbox. Ver `docs/integrations/MERCADOPAGO-CONTRATO.md`.

**Implementado nesta fase:**
- `RecurrenceRequestBuilder`: valida valor (decimal, > 0, até duas casas), moeda (BRL), frequência (1 a 12 meses), `back_url` (https com host), e-mail do pagador e referência. Testado em unidade.
- `MercadoPagoRecurrenceService`: `criarPlano`, `obterPlano`, `alterarPlano`, `listarPlanos`, `criarAssinatura`, `obterAssinatura`, `alterarStatusAssinatura`; a busca limita paginação e valida o formato e IDs da resposta. Criação/alteração de planos e criação de assinatura enviam chaves de idempotência determinísticas e validam o ID remoto.

**Decisão:** a recorrência usa a API de assinaturas do Mercado Pago, não o módulo `commerce_recurring`. Motivo: `commerce_recurring` não está habilitado no Runtime, e o cobrador de recorrência do Commerce não tem cobrança via Mercado Pago. Isto precisa de confirmação do responsável (pergunta 2 da seção 6).

## 3. Pagamentos: consultar, cancelar e estornar

| Ação | Como | Regra |
|---|---|---|
| Atualizar status | `PaymentClient::get` e `decidirSincronizacao` | Espelha `remote_state`, o valor estornado e os estados `completed`, `partially_refunded`, `refunded` e `authorization_voided` |
| Cancelar | `PaymentClient::cancel` (PUT com `status: cancelled`) | Só com status remoto em `pending`, `in_process` ou `authorized` e estado local `authorization`. Transição local `void` |
| Estornar | `refundPayment` do gateway, pela operação `refund-payment` do Commerce | Total ou parcial, só em `completed` e `partially_refunded` |
| Contestação | Webhook assinado consulta o caso pela API, confere o pagamento e associa-o ao pedido sob lock; painel especializado e envio de evidências ainda não implementados | Revisão humana do caso continua necessária |

Pagamentos do Checkout Pro usam o workflow próprio `aculta_mercadopago_payment`: `pending`, `in_process` e `authorized` permanecem pendentes; o Webhook assinado ou o retorno reconsulta a API e só muda para `completed` quando o estado remoto é `approved`. Notificações IPN legadas são recusadas. O cancelamento de um pagamento pendente usa a transição `void` do workflow.

Os painéis de vendas e doações têm ações de atualização, cancelamento e estorno conforme o escopo e as permissões de cada tipo de pedido. Doações recorrentes não permitem estorno; ações de assinatura são expostas no fluxo de recorrência.

## 4. Relatório de vendas

- `SalesReportQuery` lê `commerce_order` e `commerce_order_item` direto, sem tabela paralela. Assim, estorno e cancelamento aparecem sem regerar dados.
- `SalesReportFilters` trata datas (`de`, `ate`), loja (`loja`) e granularidade (`granularidade`), com limite de 366 dias.
- A sintaxe de data é MariaDB, o banco de produção. Portabilidade é do DBTNG-2.
- O controller usa `SalesReportQuery`; vendas e doações mantêm escopos separados e não duplicam persistência financeira.

Origem das ideias: o `commerce_reports` (GPL-2.0-or-later) inspirou a granularidade por período e a estrutura de tipos de relatório. Nenhum arquivo foi copiado literalmente; a versão do projeto usa consultas próprias sobre as tabelas do Commerce, por decisão de não criar armazenamento paralelo (AGENTS.md).

## 5. Plano de integração

### 5.1 Com a loja (produtos e pedidos)

- Compras avulsas continuam no Checkout Pro, pelo `aculta_pagamentos_mercadopago`. Nada muda no fluxo de pagamento.
- Assinatura de produto da loja (ex.: clube de assinatura) **não está no escopo** até decisão do responsável (pergunta 3).
- Pedidos da loja entram no relatório de vendas pela consulta da seção 4.

### 5.2 Com doações (`commerce_donation_flow`)

O módulo usa os campos de frequência e valor do fluxo de doação para criar um pedido do tipo `assinatura` e delega ao serviço Mercado Pago; não depende de `commerce_recurring`.

Fluxo implementado: o checkout cria o pedido de assinatura e inicia uma assinatura pendente no Mercado Pago usando referência do pedido e chave idempotente; o comprador autoriza pelo `init_point`. Webhooks assinados atualizam o estado por versão e cobranças aprovadas criam pagamentos Commerce deduplicados no pedido associado. A interface de recorrência oferece pausa, reativação e cancelamento nas transições permitidas. O fluxo está coberto por Unit/Kernel; confirmação de autorização e cobrança no sandbox continua pendente.

### 5.3 Ambiente e endereços

- `back_url` e URLs de retorno são dependentes de ambiente e devem respeitar o mapeamento de domínios ACULTA. `notification_url` do Checkout Pro é gerada pela rota nativa Commerce. O builder de recorrência só aceita `back_url` HTTPS com host.
- Gate: `php scripts/validate-environment-addresses.php` deve continuar PASS.

### 5.4 Credenciais

- O gateway seleciona o token do ambiente ativo em memória; `MercadoPagoCredentials` e o override obtêm valores de ambiente, e o hook pré-save remove valores de credenciais antes de persistir a entidade.
- O segredo HMAC de webhook é carregado por Drupal Key com provider `env`. Nenhuma credencial deve ser gravada em config exportado, logs, fixtures ou respostas.

## 6. Perguntas

Respondidas em 2026-10-16; ver a seção 0.

## 7. Fases

| Fase | Conteúdo | Situação |
|---|---|---|
| F0 | Inventário, camada de API, recorrência, ações de pagamento, relatório | Implementado em código; testes Unit/Kernel focais passam, operação sandbox ainda pendente |
| F1 | Painéis: vendas e doações, ações com confirmação e escopo de permissões | Implementado em código; inspeção visual autenticada com pedidos de teste pendente |
| F2 | Webhooks de pagamentos, assinatura, plano, cobrança recorrente e contestação | Implementado com assinatura HMAC, consulta remota e associação validada; Unit/Kernel passam. Configuração dos tópicos e entrega ponta a ponta sandbox pendentes |
| F3 | Integração com doações (fluxo da seção 5.2) | Implementada em código e coberta; aprovação/autorização/cobrança recorrente sandbox pendentes |
| F4 | Credenciais por ambiente, Drupal Key/env e Kernel no banco dedicado | Implementado e testado; o pré-save mantém o gateway ativo, falha fechado sem credenciais do ambiente e impede persistência dos valores. Preparação de produção depende de configuração operacional e homologação |

## 7.1 Assinaturas: status e ações (2026-10-17)

- Status da assinatura (`preapproval`) usados no código: `pending` (aguardando autorização), `authorized` (ativa), `paused` (pausada), `canceled` (cancelada). Para compatibilidade de leitura, o módulo também reconhece `cancelled`, gravado por versões anteriores, como cancelado.
- Status da cobrança (`authorized_payments`), na documentação oficial consultada: `processed` (cobrado com sucesso, ou parcela que esgotou as tentativas), `waiting for gateway` (em processamento), `recycling` (recusada, em nova tentativa). O exemplo da referência usa `scheduled`. Só `payment.status = approved` vira pagamento no pedido.
- Ações do doador em "Meu apoio": pausar (de `authorized`), reativar (de `paused`) e cancelar (de `pending`, `authorized` ou `paused`). Cada ação serializa por ID remoto, consulta o estado atual, valida associação ao pedido e só persiste após resposta confirmada. Retry de estado já aplicado apenas reconcilia; doações não são estornadas.
- Ações e webhooks de assinatura compartilham lock por ID. `mp_subscription_version`, gravado no `data` do pedido Commerce existente, ignora eventos atrasados e rejeita estado divergente na mesma versão; ações também recusam consulta remota anterior ao marcador. Não exige migração de esquema; rollback pode ignorar a chave e removê-la opcionalmente durante manutenção.
- Painel "Doações recorrentes", em Relatório de doações: resumo por situação e as 100 assinaturas mais recentes.
- Desde MP dev.16, o registro da cobrança usa lock por gateway/ID de pagamento; exige `currency_id` e `transaction_amount` iguais ao total do pedido e recusa reutilização do ID remoto em outro pedido.
- Situação da última cobrança, guardada no pedido (`mp_cobranca_situacao`): `paga` (pagamento aprovado), `nao_cobrada` (`processed` sem aprovação: tentativas esgotadas), `recusada` (`recycling`), `processando` (`waiting for gateway`), `agendada` (`scheduled`), `cancelada` (`cancelled`, não documentado: rótulo conservador), `desconhecida` (demais). Só `paga` grava pagamento.

**Verificado no sandbox (2026-10-17, conta de teste):**
- `PUT /preapproval/{id}` com `paused` em assinatura `pending` → HTTP 400 `Invalid transition from pending to paused`. Confirma que pausar só vale para `authorized` (regra de `MercadoPagoSubscriptionRecorder::ACOES`).
- `PUT /preapproval/{id}` usa `canceled` para cancelar e `paused` para pausar, conforme o [guia oficial de gerenciamento](https://www.mercadopago.com.br/developers/pt/docs/subscriptions/subscription-management). A reativação de pausada permanece enviada como `authorized` e deve ser confirmada no sandbox.
- Criação de assinatura `pending` com `back_url` do domínio `aculta.toca.net.br` e do Mercado Pago: aceita em algumas tentativas. `back_url` `https://aculta.org/` foi recusado pela conta de teste.
- **Não verificado:** assinatura `authorized` criada com cartão de teste (HTTP 400 `Unsupported_credit_card_for_recurring_payment` no Mastercard e `User bad request` nos Visa), e, por consequência, pausar e reativar a partir de `authorized`, e as cobranças reais (`authorized_payments`). A partir de certo momento da sessão, até a criação `pending` passou a ser recusada com `User bad request`; a causa não foi identificada.
- Criação de compradores de teste (`POST /users/test_user`) teve limite de taxa local (HTTP 429).

## 7.2 Pendências de sandbox e interface

- Ponta a ponta no sandbox pelo checkout ACULTA: autenticar comprador de teste, aprovar/recusar/aguardar pagamento, validar retorno e webhook, autorizar assinatura, pausar, reativar, cancelar e receber cobrança.
- Habilitar no painel da aplicação os tópicos necessários (`subscription_preapproval` ou `subscription_preapproval_plan`, `subscription_authorized_payment` e `payments`) e registrar a evidência de entrega assinada.
- Inspecionar visualmente checkout, feedback de pagamento, painel financeiro e ações de assinatura com sessão de teste autenticada.
- Confirmar no sandbox os valores de status de cobrança que não estejam descritos pela referência oficial.

## 8. Testes e evidências

- Unit do submódulo Mercado Pago: 258 testes / 1.156 assertions PASS em 2026-10-28 (1 depreciação PHPUnit); Unit Studio 62/502 PASS.
- Kernel Mercado Pago: `CheckoutProTest` 3 testes / 135 assertions PASS em execução registrada anteriormente (78 depreciações Core/contrib). Na verificação atual, `aculta_simpletest` não existe; o Kernel não foi repetido e nenhum banco foi criado.
- Gates verificados nesta microfase: Portal Drupal 11+ PASS (1.155 checks), índice de status PASS (43 componentes) e fronteira módulo/tema PASS (3.132 verificações). PHPCS Drupal/DrupalPractice e PHP lint focais PASS.
- **Runtime (verificado em 2026-10-16):** o gateway `mercado_pago` (plugin `aculta_mercadopago_checkout_pro`, modo `test`) existe, com token de teste. Uma chamada somente leitura `UserClient::get` autenticou com o token de teste (site `MLB`). O Runtime carrega este checkout (`web/modules/custom/aculta_portal` aponta para o mesmo repositório). As consultas do relatório executam no MariaDB sem erro; o banco de teste não tem pedidos concluídos, então os valores não foram conferidos.
- Sandbox: preferência sintética aberta no Firefox ESR visível até as etapas de escolha de pagamento e identificação do comprador (HTTP 200, sem CAPTCHA); o login do comprador não foi concluído. Não há evidência de pedido Commerce pago, estorno, nem autorização/cobrança de assinatura.
- Não houve escrita em bancos do Runtime, alteração persistente de configuração ou publicação nesta microfase; houve apenas atualização de um plano sandbox já existente.
### Gestão administrativa dos planos — MP dev.79 / Studio dev.24 (2026-10-28)

Registro histórico: na época, a equipe autorizada administrava modelos pela tela `/studio/conta/mercado-pago/planos`; o POST já usava o endpoint canônico `/integracoes/mercadopago/planos` no domínio MAIN. Pela ADR-038, a tela atual é `/conta/mercado-pago/planos` no purpose `account`; o path antigo `/studio/...` foi removido sem alias. O owner, permissões e endpoint MAIN não mudaram.

A tela histórica consultava o catálogo remoto com paginação limitada, criava planos e atualizava por ID. As operações eram restritas a gateway ativo em modo sandbox e às permissões `access aculta portal` e `administrar doacoes`; não havia exclusão de planos pela API nem persistência local paralela. Firefox ESR/X11 no Runtime confirmou o shell ACULTA420, formulário, listagem, labels, envio ao domínio MAIN e reflow em 390/1280 px. Homologação sandbox criou plano mensal de R$ 1,00, atualizou esse mesmo plano para R$ 1,25 e confirmou o estado por API GET; nenhuma assinatura ou cobrança foi iniciada. O plano permanece ativo na conta de teste porque a API não oferece exclusão. Unit MP 258/1.156, Unit Studio 62/502, PHPCS, PHP lint, boundary e gate Drupal 11 passaram nessa execução; o Kernel 3/135 tem evidência anterior, mas não pôde ser repetido pois `aculta_simpletest` não existe. A tentativa de autenticar o comprador em 2026-10-28 retornou `manual_security_challenge`, mantendo checkout de pagamentos pendente.
