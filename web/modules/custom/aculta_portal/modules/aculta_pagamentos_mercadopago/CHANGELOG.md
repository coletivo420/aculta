## 0.1.0-dev.80 — sincronizar os tipos de pedido do gateway (2026-10-28)

- `config/sync` agora declara os bundles `curso`, `default` e `doacao` aceitos pelo gateway ativo em modo `test`; comparação focal do Runtime não aponta diferença. Nenhuma importação ou escrita de banco foi feita.

## 0.1.0-dev.79 — gestão de planos no Studio (2026-10-28)

- A tela do ACULTA Studio em `/studio/conta/mercado-pago/planos` lista, cria e atualiza planos; a submissão usa `/integracoes/mercadopago/planos` e retorna à tela no propósito `account`. A interface rejeita gateways fora de `test` e exige usuário autenticado, `access aculta portal` e `administrar doacoes`.
- Sem armazenamento local adicional. Criação usa token estável e hash dos dados no estado do formulário; após timeout, somente o reenvio do mesmo conteúdo é aceito até a conciliação. Unit Studio 62/502 e Unit MP 258/1.156 PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint focais, YAML route parse, status index e module/theme boundary PASS. O gate Portal Drupal 11 foi alinhado à política de configuração aprovada, preservando regressões para gateway sempre ativo, limpeza de segredos, edição negada e cliente financeiro fail-closed; PASS (1.155 verificações). Kernel `CheckoutProTest` 3/135 PASS em execução anterior; não repetido nesta sessão porque `aculta_simpletest` não existe e não foi criado. Firefox ESR/X11 no Runtime: entrada Studio, ACULTA420, listagem, labels, POST no domínio MAIN e reflow PASS. Sandbox CRUD criou plano mensal de R$ 1,00, atualizou o mesmo para R$ 1,25 e confirmou os dados via API GET. Um plano test ativo permanece na conta sandbox; nenhuma assinatura ou cobrança foi criada. Novo login do comprador sandbox retornou `manual_security_challenge`; nenhum pagamento foi enviado.

## 0.1.0-dev.78 — impedir regressão de pagamento em webhook pendente (2026-10-28)

- `CheckoutPro::onNotify()` não sobrescreve `remote_state` de pagamentos concluídos ou encerrados com resposta pendente atrasada. Também persiste a transição local para `pending` quando o `remote_state` já tem o mesmo valor remoto.
- Regressão falhava antes nos três casos (transição sem `save()`, regressão após aprovação e atualização de pagamento encerrado). Unit MP: 256 testes / 1.150 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.77 — listar planos com paginação limitada (2026-10-27)

- `MercadoPagoRecurrenceService::listarPlanos()` consulta planos pelo cliente oficial, limita a página a 100 itens e valida o formato da resposta e cada ID remoto antes de devolver os resultados.
- Regressões cobrem paginação, IDs malformados e paginação inválida sem chamada ao provedor. Unit MP: 254 testes / 1.127 assertions PASS (1 PHPUnit deprecation); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. A interface ACULTA de gerenciamento ainda não consome a busca.

## 0.1.0-dev.76 — isolar idempotência na criação de planos (2026-10-27)

- `criarPlano()` agora exige um identificador único da intenção: retries reutilizam esse identificador e duas criações legítimas com payload idêntico usam chaves distintas. O valor inválido é rejeitado antes do SDK. O chamador deve preservar o identificador durante retries e gerar outro para um novo plano.
- A regressão falhava antes porque payloads idênticos compartilhavam a chave. Unit MP: 249 testes / 1.119 assertions PASS (1 PHPUnit deprecation); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. A API de planos ainda não tem consumidor operacional ACULTA.

## 0.1.0-dev.75 — validar referência do pagamento no retorno (2026-10-27)

- `CheckoutPro::onReturn()` agora rejeita `external_reference` não vazio que não corresponda ao pedido local, mesmo quando o pagamento também informa uma Merchant Order aparentemente válida. Isso alinha o retorno ao webhook e impede consulta/gravação Commerce após referências remotas conflitantes.
- Regressão falhava antes por alcançar o storage de pagamentos; agora rejeita antes do storage. Unit MP: 247 testes / 1.117 assertions PASS (1 PHPUnit deprecation); Kernel `CheckoutProTest`: 3 testes / 135 assertions PASS (78 depreciações Core/contrib); PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.74 — não bloquear gravação segura do gateway (2026-10-27)

- `EntitySaveHooks` não exige credenciais de runtime para salvar/importar a configuração: força o gateway ativo e limpa os campos secretos sempre. A ausência de token continua falhando fechada na criação de operações financeiras, sem bloquear sincronização segura de configuração.
- O hook não recebe dependências que não usa e pode ser instanciado diretamente pelo resolvedor Drupal. Unit MP: 246 testes / 1.106 assertions PASS (1 PHPUnit deprecation); Kernel `CheckoutProTest::testGatewayPluginConfiguration`: 1 teste / 68 assertions PASS (77 depreciações Core/contrib); PHPCS Drupal/DrupalPractice e PHP lint PASS.

## 0.1.0-dev.73 — validar destinos externos do checkout (2026-10-27)

- `MercadoPagoRedirectUrl` centraliza validação HTTPS dos domínios oficiais `mercadopago.com`, `mercadopago.com.br` e `mercadopago.com.ar`. Checkout Pro e recorrência validam ID/URL antes de persistir preferência ou expor `init_point` ao formulário, popup ou redirecionamento.
- Regressão do formulário confirma host oficial `.com`, rejeição de domínio lookalike e ID não escalar. Unit MP: 246 testes / 1.104 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.72 — validar resposta e destino da autorização recorrente (2026-10-27)

- A criação de assinatura não converte ID, status ou `init_point` malformados por coerção. Para assinatura pendente, o link deve ser HTTPS e pertencer aos domínios oficiais do Mercado Pago (`mercadopago.com` ou `mercadopago.com.br`); a mesma regra vale ao recuperar uma autorização existente. Resposta inválida preserva o marcador de conciliação e evita um novo POST remoto ambíguo.
- Regressões cobrem ID array (antes persistia como `Array`), origem externa na criação e no retry. Unit MP: 245 testes / 1.101 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint PASS.

## 0.1.0-dev.71 — validar ID de fatura recorrente (2026-10-27)

- `WebhookGuard` rejeita `subscription_authorized_payment` cujo `data.id` não seja numérico positivo, conforme contrato oficial do endpoint de faturas do Mercado Pago. Isso evita chamada remota e ciclo de retries para IDs textuais incompatíveis.
- Regressão falhava antes (500 por despacho do handler) e passa agora (400 sem despacho). Unit MP: 241 testes / 1.075 assertions PASS (1 depreciação PHPUnit); teste de webhook: 8/22 PASS; PHPCS Drupal/DrupalPractice e PHP lint PASS.

## 0.1.0-dev.70 — serializar gravações de recorrência pelo pedido (2026-10-27)

- Criação de assinatura, ações do doador, webhooks de estado e cobranças agora compartilham lock por pedido e recarregam a entidade com `loadUnchanged()` antes de gravar. Cobranças mantêm também o lock por ID remoto, impedindo duplicidade enquanto diferentes faturas da mesma assinatura não sobrescrevem dados do pedido com snapshots concorrentes.
- Regressão comprovou a falha antes da correção: duas cobranças usavam locks remotos diferentes e não recarregavam o pedido. Unit MP: 240 testes / 1.073 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint PASS. Usa o `data` do Commerce existente; sem nova tabela ou migração.

## 0.1.0-dev.69 — persistir estorno para resposta array do SDK (2026-10-27)

- `refundPayment()` agora persiste o `remote_state` a partir do status já validado, aceitando tanto os formatos objeto como array do retorno do cliente. Antes, a resposta array era aprovada, mas o acesso posterior a `->status` interrompia a gravação Commerce após o estorno remoto.
- Regressão reproduz erro antes da correção e passa depois. Unit MP: 239 testes / 1.053 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.68 — repetir webhook se handler de assinatura não existir (2026-10-27)

- `WebhookGuard` responde `503` quando recebe tópico suportado de assinatura, validamente assinado, mas o handler não está disponível. O provedor poderá reenviar o evento em vez de receber um `200` que o descartaria sem conciliação.
- Regressão `MercadoPagoSubscriptionWebhookTest`: 7 testes / 20 assertions PASS; suíte Unit MP: 239 testes / 1.053 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice PASS.

## 0.1.0-dev.67 — respeitar precisão ISO da moeda (2026-10-27)

- `MercadoPagoAmount` consulta os dígitos fracionários da moeda ISO e aplica a mesma escala ao refresh, webhook, retorno, estorno e cobrança recorrente; o limiar entre endpoint de estorno parcial e total usa BCMath, e somente a chamada do SDK converte o valor para `float`.
- Unit cobre JPY (0 casas), BRL (2), BHD (3), rejeição de precisão excedente e estorno BHD. Unit MP: 239 testes / 1.053 assertions PASS (1 depreciação PHPUnit); Kernel `CheckoutProTest`: 3/135 PASS (78 depreciações Core/contrib); PHPCS e PHP lint focais PASS.

## 0.1.0-dev.66 — rejeitar Merchant Order malformada no webhook (2026-10-27)

- Quando a resposta autenticada do Mercado Pago inclui `order`, `onNotify()` agora exige um ID numérico positivo e interrompe com retry se o identificador for malformado; não usa `external_reference` como fallback nesse caso.
- Regressão comprova que o evento não chega a carregar nem persistir pedido Commerce. Unit MP: 232 testes / 1.026 assertions PASS (1 depreciação PHPUnit); PHPCS e PHP lint focais PASS.

## 0.1.0-dev.65 — normalizar valores remotos em todos os fluxos (2026-10-27)

- Centraliza a expansão de notação científica e validação de centavos em `MercadoPagoAmount`, aplicada ao refresh manual, webhook de pagamento e retorno do comprador. O webhook compara valores de reembolso com BCMath e não persiste quando os valores remotos são inválidos; regressões aprovadas exercitam notação científica no webhook e retorno.
- Suíte Unit MP: 231 testes / 1.013 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. Regressão de webhook comprova que valor subcentavo não chega à persistência. Nenhum pagamento externo executado.
## 0.1.0-dev.64 — tolerar instanciação direta do hook (2026-10-27)

- `EntitySaveHooks` aceita instanciação sem o argumento de container e resolve a raiz Drupal somente quando precisa tratar o gateway Mercado Pago. Isso evita `ArgumentCountError` no resolvedor de hooks e deixa entidades de usuário/pedido fora do gateway sem consultar credenciais.
- Unit `EntitySaveHooksTest`: 4 testes / 10 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS. O erro separado `releaseLock(): null` ainda precisa de reprodução no Runtime para confirmar se foi eliminado.

## 0.1.0-dev.63 — normalizar valores remotos em notação científica (2026-10-27)

- `MercadoPagoPaymentService` expande notação científica antes de comparar valores via BCMath e rejeita valores remotos com precisão não nula além dos centavos da moeda. O tamanho de expansão é limitado para que uma resposta malformada não cause alocação excessiva.
- Regressões cobrem `1.2e2`/`0e0` e rejeição de `120.001`. Unit MP: 222 testes / 986 assertions PASS (1 depreciação PHPUnit); PHPCS Drupal e PHP lint focais PASS. Sem chamadas ao provedor.

## 0.1.0-dev.62 — completar contrato visual dos tokens Portal (2026-10-27)

- Completei o mapa de tokens do ACULTA420 e o catálogo dos atributos data-* já usados por conta, Studio, Loja, LMS, Wiki e Messenger. Os avisos agrupados do Messenger agora usam as classes do tema após a tradução dos tokens, em vez de seletores para atributos removidos pelo preprocess.
- Unit ACULTA420: 10 testes / 59 assertions PASS; Unit Messenger: 9 / 42 PASS; contrato UI PASS (362 tokens mapeados, 282 usados, 145 atributos), mapa de módulos PASS (200) e fronteira módulo/tema PASS (3123). PHPCS focais PASS. Homologação sandbox Mercado Pago continua pendente.

## 0.1.0-dev.61 — remover configuração manual de credenciais (2026-10-27)

- A tela do gateway não exibe nem grava campos de credenciais; as chaves vêm exclusivamente do ambiente ativo. Removidos a validação de campos ausentes que já não existem, o hook de mascaramento obsoleto, a folha administrativa sem consumidor no tema ativo e seus tokens órfãos. O Kernel verifica que o formulário informa a origem ambiental e não contém campos secretos.
- Unit MP: 220 testes / 977 assertions PASS (1 depreciação PHPUnit); Kernel `CheckoutProTest`: 3 testes / 135 assertions PASS (78 depreciações Core/contrib); PHPCS Drupal/PHP lint e gates de mapa (200 checks), fronteira (3123 checks) e índice de status PASS. `validate-portal-ui-docs.php` continua FAIL por 89 divergências em outros contratos/tokens, sem ocorrência de Mercado Pago no relatório.

## 0.1.0-dev.60 — atualizar planos com idempotência e validar IDs (2026-10-27)

- `MercadoPagoRecurrenceService` agora implementa atualização de plano pelo endpoint oficial `PUT /preapproval_plan/{id}`, com payload validado, `X-Idempotency-Key` estável e conferência do ID retornado. Criação de planos também usa chave estável e falha fechada se a API não devolver ID válido. Não foi criado cancelamento por API: a documentação do Mercado Pago descreve essa ação como irreversível no painel e informa que assinantes existentes continuam ativos.
- Unit `MercadoPagoRecurrenceServiceTest`: 10 testes / 23 assertions PASS; suíte Unit MP: 221 testes / 984 assertions PASS (1 depreciação PHPUnit). PHPCS Drupal/DrupalPractice e PHP lint focais PASS. Homologação da operação de plano sandbox pendente.

## 0.1.0-dev.59 — manter gateway ativo e validar credenciais no pré-save (2026-10-27)

- `EntitySaveHooks` agora falha fechado se o par de credenciais do ambiente definido pelo Deployer estiver ausente, força `mercado_pago` ativo e remove valores de credenciais antes da persistência. Regressões Unit/Kernel cobrem ativação e ausência de credenciais; Kernel comprova que tentativa de desativação não persiste.
- Sincronizei o plano Mercado Pago e o mapa de módulos com o estado atual. Unit MP: 217 testes / 976 assertions PASS; Kernel: 3 testes / 129 assertions PASS (78 depreciações Core/contrib); PHPCS/PHP lint focais e gates Portal/mapa PASS.

## 0.1.0-dev.58 — idempotência estável ao criar assinatura (2026-10-27)

- `MercadoPagoRecurrenceService::criarAssinatura()` agora envia `X-Idempotency-Key` derivada do pedido e do payload exato. A chave evita que uma repetição após timeout crie outra assinatura; pedidos diferentes e alterações de payload recebem chaves distintas.
- Regressão verifica estabilidade da chave no retry e isolamento por pedido. Unit `MercadoPagoRecurrenceServiceTest`: 6 testes / 16 assertions PASS; PHP lint PASS. Sandbox permanece pendente.

## 0.1.0-dev.57 — reconciliar retorno sem status do navegador (2026-10-27)

- O retorno agora exige os IDs do pagamento e do Merchant Order, mas não exige o `status` enviado pelo navegador. A decisão continua baseada na consulta autenticada à API do Mercado Pago e na verificação dos vínculos. Também removi um bloco comentado que propunha copiar o e-mail do comprador da resposta remota para o pedido.
- Regressão aprova a repetição idempotente quando a API confirma `approved` e o navegador omite `status`; teste de aprovação forjada contra API pendente continua cobrindo rejeição. Unit MP: 215 testes / 969 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.56 — esclarecer o escopo do log de debug (2026-10-27)

- A descrição administrativa agora informa que só o payload de preferência sanitizado é registrado; dados de pagador são removidos, mas detalhes de pedido e itens permanecem. Removido um método de debug sem uso.
- Unit MP: 215 testes / 969 assertions PASS; Kernel `CheckoutProTest`: 3 testes / 128 assertions PASS (78 depreciações Core/contrib); PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.55 — remover controle de taxa sem efeito (2026-10-27)

- O formulário do gateway não expõe mais `autofee`: o campo prometia adicionar taxas do adquirente, mas não existia cálculo correspondente. A chave e o schema permanecem por compatibilidade; salvar o gateway força o legado a `false`.
- Kernel confirma que o controle não aparece. `CheckoutProTest`: 3 testes / 126 assertions PASS (78 depreciações Core/contrib); PHPCS Drupal/DrupalPractice e PHP lint PASS.

## 0.1.0-dev.54 — validar integração do webhook no Kernel (2026-10-27)

- Novo teste Kernel atravessa o subscriber real da notificação Commerce, resolve a signing Key pelo provedor `env`, aceita assinatura válida, retorna 401 para assinatura forjada e 503 quando o gateway está desabilitado. Nenhum segredo real nem chamada externa.
- `CheckoutProTest`: 3 testes / 124 assertions PASS (78 depreciações Core/contrib); PHPCS Drupal/DrupalPractice e PHP lint PASS. Unit MP: 215 testes / 969 assertions PASS. Homologação sandbox permanece pendente.

## 0.1.0-dev.53 — impedir associação duplicada entre pedidos (2026-10-27)

- `onNotify()` e `onReturn()` agora verificam se o ID remoto já está associado a outro pedido Commerce antes de criar pagamento local. Mais de um registro local com o mesmo gateway/ID também falha fechado; webhook pede retry e retorno não altera pedido/pagamento.
- Regressões cobrem ID já associado a outro pedido nos dois fluxos e mais de um pagamento Commerce para o mesmo ID. Unit MP: 215 testes / 969 assertions PASS; Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. Homologação sandbox pendente.

## 0.1.0-dev.52 — reentregar webhook durante vínculo da assinatura (2026-10-27)

- `subscription_preapproval` agora retorna erro retryable quando o ID remoto chega enquanto o pedido ainda tem `mp_subscription_creation_pending` e ainda não salvou o vínculo. O evento assinado não é descartado com HTTP 200 antes da conclusão da gravação local.
- Regressão reproduz a janela entre criação remota e persistência do ID: nenhuma alteração local ocorre e o lock é liberado após a exceção 503. Unit MP: 212 testes / 951 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS. Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream). Homologação sandbox pendente.

## 0.1.0-dev.51 — validar vínculo da resposta de estorno (2026-10-27)

- `refundPayment()` só atualiza o pagamento Commerce quando a API retorna um estorno aprovado com ID positivo e `payment_id` igual ao pagamento solicitado. Respostas incompletas ou vinculadas a outro pagamento falham fechadas.
- Regressões cobrem ausência de ID de estorno ou `payment_id`, ID inválido e pagamento divergente. Unit MP: 211 testes / 944 assertions PASS; Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. A homologação de estorno sandbox continua pendente.

