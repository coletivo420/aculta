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

## 0.1.0-dev.50 — confirmar o ID do Merchant Order retornado (2026-10-27)

- `onNotify()` e `onReturn()` agora verificam que `merchant_order.id` devolvido pela API corresponde ao Merchant Order ligado ao pagamento e ao ID consultado. Uma resposta divergente não grava pagamento; webhook pede retry e retorno falha fechado.
- Regressões cobrem Merchant Order divergente com a mesma `external_reference` em webhook e retorno. Unit MP: 207 testes / 908 assertions PASS; Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream); PHPCS/PHP lint PASS.

## 0.1.0-dev.49 — confirmar o ID retornado pela API (2026-10-27)

- `onNotify()` e `onReturn()` agora exigem que o ID no objeto devolvido pelo Mercado Pago corresponda ao ID solicitado. Uma resposta divergente não consulta Merchant Order nem grava pagamento; notificação pede retry e retorno falha fechado.
- Regressões cobrem respostas de API com ID diferente em ambos os fluxos. Unit MP: 205 testes / 886 assertions PASS; Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream).

## 0.1.0-dev.48 — serializar também o retorno do navegador (2026-10-27)

- `onReturn()` agora adquire o lock compartilhado pelo ID remoto antes de qualquer consulta ao Mercado Pago e o mantém até persistir no Commerce. Lock ocupado encerra com exceção segura sem chamar o provedor; falhas liberam o lock em `finally`.
- Regressão cobre disputa do lock antes da consulta. Unit MP: 203 testes / 861 assertions PASS; Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream); PHPCS Drupal/DrupalPractice e PHP lint PASS.

## 0.1.0-dev.47 — serializar consulta e gravação do webhook de pagamento (2026-10-27)

- `onNotify()` adquire o lock pelo ID remoto antes de consultar o Mercado Pago e mantém a seção crítica até concluir a conciliação Commerce. Uma notificação concorrente recebe 503 antes da consulta e pode ser reenviada; isso impede que uma leitura remota antiga, obtida antes do lock, seja aplicada depois de um estado mais recente.
- Regressão cobre lock ocupado sem chamada ao provedor; os fluxos de aprovação, pendência, terminais, timeout e referências continuam cobertos. Unit MP: 202 testes / 839 assertions PASS; PHPCS Drupal/DrupalPractice PASS; Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream).

## 0.1.0-dev.46 — atualizar status remoto em retorno pendente (2026-10-27)

- `onReturn()` agora atualiza `remote_state` quando o pagamento Commerce já está pendente e a API confirma outro estado ainda pendente (`pending`, `in_process` ou `authorized`). Mantém a máquina Commerce pendente e não regride pagamentos concluídos.
- Regressão: retorno autenticado do provedor `in_process` → `authorized`, pagamento local continua `pending`, `remote_state` é salvo uma vez. Unit MP: 201 testes / 820 assertions PASS; PHPCS Drupal/DrupalPractice PASS; Kernel `CheckoutProTest`: 2 testes / 91 assertions PASS (77 depreciações upstream).

## 0.1.0-dev.45 — conciliar notificações assinadas de contestação (2026-10-27)

- Webhook `topic_chargebacks_wh` agora consulta o caso no Mercado Pago pelo ID assinado, associa o pagamento pelo `payment_id` retornado pela API e aciona a conciliação Commerce normal, com validação de pedido/valor/moeda e lock compartilhado. Associação ausente ou ambígua falha e solicita retry; payload remoto e dados do comprador não são registrados.
- Webhook e associação de contestação: testes cobrem assinatura inválida, reentrega, seller header `X-Caller-Id`, caso divergente e vínculo ausente/duplicado; conciliação injeta o contrato de operações Commerce existente.
- Unit MP: 200 testes / 809 assertions PASS; PHPCS Drupal/DrupalPractice PASS; Kernel: 2 testes / 91 assertions PASS (77 depreciações upstream); sandbox pendente.

## 0.1.0-dev.44 — corrigir payload e diagnóstico da preferência (2026-10-27)

- O payload de Checkout Pro não referencia mais a variável de itens removida pelo normalizador. O diagnóstico de debug agora ocorre somente após calcular itens ajustados e frete, e continua removendo dados do pagador.
- Unit MP: 192 testes / 783 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.43 — provar ajustes de linha no Commerce Kernel (2026-10-27)

- Kernel usa um `OrderItem` real com desconto aplicado e verifica o valor ajustado que o Checkout Pro enviará. A regressão usa exclusivamente `aculta_simpletest` e importa BRL no schema descartável.
- CheckoutPro Kernel: 2 testes / 89 assertions PASS (77 depreciações upstream); Unit MP: 192 testes / 783 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint PASS. Smoke Firefox ESR repetiu produto, carrinho e checkout até endereço/frete sem CAPTCHA; não criou preferência nem pagamento, e o carrinho temporário foi removido.

## 0.1.0-dev.42 — refletir ajustes do pedido na preferência (2026-10-27)

- Preferências Checkout Pro agora usam totais de linha ajustados do Commerce, incluem ajustes gerais e alocam descontos gerais sem preços negativos; o frete permanece separado. O gateway bloqueia a chamada remota se o total do pagamento não corresponder ao pedido ou se o merchandise não puder ser representado com segurança.
- Unit MP: 192 testes / 783 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS. Descontos e ajustes ainda precisam de confirmação visual no sandbox.

## 0.1.0-dev.41 — validar vínculo em conciliação de pagamento (2026-10-27)

- `MercadoPagoPaymentService` agora exige que `external_reference` da consulta remota corresponda ao ID do pedido Commerce antes de reconciliar ou cancelar. IDs remotos válidos atribuídos a outro pedido falham fechados.
- Regressão reproduziu a atualização indevida antes da correção e confirma que nenhum estado é salvo para uma referência divergente. Unit MP: 188 testes / 770 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.40 — tornar idempotente a criação de preferências (2026-10-27)

- `RedirectCheckoutForm` cria preferências pelo `MercadoPagoClientFactory` e envia uma chave `X-Idempotency-Key` determinística por pedido e payload. Retry idêntico mantém a chave; payload ou pedido diferente recebe outra chave.
- Unit MP: 187 testes / 766 PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS. A aceitação de retries pelo endpoint de Preferências ainda requer homologação sandbox.

## 0.1.0-dev.39 — reconciliar rejeição e cancelamento via Webhook (2026-10-27)

- `CheckoutPro::onNotify()` agora registra os status remotos `cancelled` e `rejected` como `authorization_voided` no pagamento Commerce existente, sob o lock por ID remoto. Evento terminal sem pagamento local é reconhecido sem criar pagamento.
- Regressões Unit cobrem ambos os estados e a ausência de duplicação. Unit MP: 186 testes / 748 assertions PASS; Kernel CheckoutPro: 1/56 PASS (77 depreciações upstream); PHPCS e PHP lint focais PASS.

## 0.1.0-dev.38 — normalizar falhas de transporte na Merchant Order (2026-10-27)

- O retorno converte falhas ao consultar a Merchant Order em exceção segura do gateway; detalhes do provedor/transporte não escapam à resposta.
- Unit MP: 184 testes / 718 assertions PASS; Kernel CheckoutPro: 1/56 PASS; PHPCS e PHP lint focais PASS.

## 0.1.0-dev.37 — distinguir implementação de recorrência e homologação (2026-10-27)

- Documentação registra o vínculo recorrente com o pedido como implementado e coberto por Unit/Kernel; mantém a homologação do comprador sandbox pendente (DT-MP-02/DT-MP-03).
- Unit MP: 183 testes / 712 assertions PASS. Revisão documental e `git diff --check`.

## 0.1.0-dev.36 — homologar preferência sandbox e tela de comprador (2026-10-27)

- O `MercadoPagoClientFactory` criou uma preferência sintética com token no modo `test`; o `init_point` abriu a tela “Escolha como pagar” no Firefox ESR visível (HTTP 200, sem CAPTCHA), e avançar carregou a identificação do comprador (HTTP 200).
- O smoke visual reutilizável salva a captura somente em `$SUITE_OUT`; o helper de login aceita o init_point via arquivo temporário. A tentativa autorizada de autenticação terminou `login_incomplete`; não houve pagamento.
- Evidência parcial da API/provedor. Não comprova a chamada de preferência feita por `RedirectCheckoutForm`, pedido Commerce ou pagamento confirmado.

## 0.1.0-dev.35 — serializar criação de assinaturas por pedido (2026-10-27)

- Criação de assinatura de doação agora usa lock por pedido, recarrega o Commerce Order sob lock e grava o vínculo antes do redirecionamento. Um retry reutiliza a assinatura pendente só depois de conferir ID e `external_reference`; assinatura autorizada retorna à conclusão e referências conflitantes falham fechadas.
- O `data` do pedido guarda um marcador antes do POST remoto. Em timeout, conflito, rate limit ou erro 5xx, o marcador bloqueia uma segunda criação até conciliação; não há filtro oficial de busca por `external_reference`. Sem nova tabela ou migração. Após confirmar no painel que não existe assinatura, a equipe pode limpar o marcador do pedido; se existir, registrar o ID remoto no pedido após validar sua referência.
- Regressões cobrem criação/vínculo, retry sem segunda criação remota, referência conflitante, timeout ambíguo, bloqueio de novo POST e limpeza do marcador em validação local rejeitada. Unit MP completo: 183 testes / 712 assertions PASS; Donation Unit 24/84 e Kernel 5/195 PASS; PHPCS focal PASS. Sandbox de assinatura segue pendente de autenticação do comprador.

## 0.1.0-dev.34 — fixar os padrões efetivos da preferência (2026-10-27)

- A normalização da preferência agora força `binary_mode: false` e `auto_return: approved` antes do diagnóstico e da chamada remota. Isso mantém os logs coerentes com o payload efetivo e protege contra valores conflitantes montados antes.
- Regressão exercita a normalização recebendo valores conflitantes; Unit completo 177 testes / 651 assertions PASS; Kernel CheckoutPro 1 teste / 56 assertions PASS (77 depreciações upstream); PHPCS e PHP lint focais PASS.
- Firefox ESR não autenticou o comprador sandbox: o Mercado Pago pediu desafio de segurança manual. Nenhuma sessão, preferência ou transação foi criada.

## 0.1.0-dev.33 — corrigir dependências documentadas (2026-10-27)

- README agora corresponde ao `info.yml`: o submódulo depende do `aculta_portal`; o módulo base não depende deste gateway. Loja, LMS e doações seguem consumidores do contrato compartilhado.
- Revisão documental; índice do projeto e diff verificados.

## 0.1.0-dev.32 — alinhar o contrato de pagamentos pendentes (2026-10-27)

- Roadmap corrigido para descrever o workflow próprio `aculta_mercadopago_payment`, a confirmação por API via retorno/Webhook assinado e a recusa de IPN legado. O texto antigo afirmava `authorization` e IPN como fluxo vigente.
- Revisão documental; sem alteração de runtime. Gates de índice e `git diff --check` PASS.

## 0.1.0-dev.31 — normalizar falhas de transporte na recorrência (2026-10-27)

- Timeouts e outras exceções de transporte do SDK em operações de plano/assinatura agora viram `MercadoPagoApiException` com mensagem segura e registro categórico, para que a pane de autorização trate a falha e ofereça nova tentativa sem expor detalhes privados.
- Regressão simula falha contendo token/URL sentinela e confirma log e mensagem seguros. Unit focal `MercadoPagoRecurrenceServiceTest`: 5 testes / 13 assertions PASS.

## 0.1.0-dev.30 — ordenar notificações de assinatura (2026-10-27)

- Webhooks e ações do doador agora usam o mesmo lock por assinatura. O webhook recarrega o pedido sob lock e só aplica estados de uma versão do Mercado Pago maior que `mp_subscription_version`; versão antiga é ignorada e status conflitante na mesma versão falha para retry.
- Ações remotas recusam leituras antigas e só salvam atualização confirmada com versão crescente. Retry de ação já aplicada reconcilia estado e versão sem repetir o `PUT`.
- `mp_subscription_version` é metadado no `data` já existente do pedido Commerce, sem mudança de esquema. Reversão: o código antigo ignora a chave; sua remoção, se desejada, é `unsetData()` no pedido sob procedimento de manutenção, sem migração obrigatória.
- Regressões cobrem evento atrasado, conflito na mesma versão, avanço, lock ocupado e leitura remota antiga. Unit MP completo: 176 testes / 642 assertions PASS.

## 0.1.0-dev.29 — serializar ações de assinatura (2026-10-27)

- Pausar, reativar e cancelar agora compartilham lock por assinatura, recarregam o pedido sob lock e consultam a situação remota antes de alterar.
- As respostas de consulta e atualização precisam corresponder ao ID da assinatura, à referência do pedido e ao status solicitado antes de persistir.
- Retry após timeout reconcilia o estado já aplicado sem repetir o `PUT`; estados remotos conhecidos que invalidam a ação atualizam o pedido e pedem nova tentativa pela interface. Unit MP: 171 testes / 611 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.28 — alinhar cancelamento de assinaturas à API (2026-10-27)

- A API de assinaturas documenta `canceled`; o código enviava `cancelled`, portanto o Mercado Pago podia rejeitar cancelamentos. A atualização agora envia o estado oficial e reconcilia notificações com ele.
- Pedidos já gravados com `cancelled` continuam legíveis como cancelados, sem migração ou gravação em massa.
- Regressões cobrem a grafia oficial, rejeitam o valor obsoleto em chamadas novas e preservam a leitura do valor legado. Unit focal: 47 testes / 127 assertions PASS; suíte completa: 165 testes / 550 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS.

## 0.1.0-dev.27 — representar pagamentos pendentes no workflow Commerce (2026-10-27)

- Gateway usa o tipo de pagamento `aculta_mercadopago`, com workflow próprio e estado `pending`; retorno e Webhook persistem status pendente sem confirmar pedido e promovem para `completed` somente após consulta remota aprovada.
- Regressões cobrem notificação pendente, aprovação de pagamentos pendentes/autorizados e retorno duplicado idempotente. Unit: 163 testes / 548 assertions PASS; Kernel `CheckoutProTest`: 1 teste / 56 assertions PASS (77 depreciações de Core/contrib).
- Pendentes: validar instalação da nova configuração de workflow no Runtime pelo fluxo operacional permitido, teste ponta a ponta/sandbox do estado pendente e revisão completa das transições de assinatura/estorno no workflow próprio.

## 0.1.0-dev.26 — preservar pagamentos pendentes do provedor (2026-10-27)

- A preferência Checkout Pro envia `binary_mode: false`. A documentação oficial confirma que o modo binário rejeita pagamentos que ficam pendentes ou em análise; o modo normal preserva esses estados para confirmação por Webhook/consulta.
- Teste Unit protege a política binária desativada; Unit completa 159 testes / 512 assertions PASS, PHP lint e PHPCS Drupal/DrupalPractice focais PASS.

## 0.1.0-dev.25 — regressão de checkout sem credencial (2026-10-27)

- `RedirectCheckoutForm` é exercitado com gateway ativo e token ausente: exibe aviso neutro e encerra antes de ler o pedido ou criar preferência no Mercado Pago.
- Evidência da regressão focal: Unit 3 testes / 15 assertions PASS; suíte completa 158 testes / 511 assertions PASS.

## 0.1.0-dev.24 — gateway ativo sem persistir credenciais (2026-10-27)

- O Mercado Pago permanece ativo em todos os ambientes. O hook pré-save limpa os campos de credenciais, sem bloquear a gravação/importação da configuração ativa quando variáveis do ambiente estão ausentes.
- A criação da preferência continua falhando fechada antes de qualquer chamada externa quando não há token efetivo.
- Regressão Unit garante que o gateway permaneça ativo e que os campos de credencial sejam removidos. Unit: 157 testes / 505 assertions PASS; PHP lint e PHPCS Drupal/DrupalPractice dos PHP alterados PASS sem avisos. Kernel `CheckoutProTest`: 1/53 PASS, 77 depreciações upstream (reexecutado nesta microfase).

## 0.1.0-dev.23 — linguagem de webhook moderno (2026-10-27)

- Checkout Pro não descreve os callbacks assinados como IPN. Logs, descrição de configuração e comentários passam a identificá-los como Webhooks; IPN legado continua citado somente ao explicar sua recusa.
- PHP lint, PHPCS Drupal/DrupalPractice e suíte Unit MP 156/502 PASS.

## 0.1.0-dev.22 — gateway ativo no teste Kernel (2026-10-27)

- `CheckoutProTest` salva o gateway ativo em modo de teste, usando chaves sintéticas somente no processo de teste. Confirma que o hook limpa ambas antes de persistir e que o plugin continua selecionando o modo `test`; nenhuma chamada de API é feita.
- Kernel: 1 teste / 53 assertions PASS em `aculta_simpletest`, com as mesmas 77 depreciações upstream. PHPCS Drupal/DrupalPractice e PHP lint PASS.

## 0.1.0-dev.21 — sanitização dos logs de checkout e recorrência (2026-10-27)

- O diagnóstico opcional da preferência omite todo o bloco `payer`; falhas da API registram apenas HTTP status e falhas de transporte usam mensagem genérica. O texto e o corpo de exceções nunca entram nos logs.
- Falhas de ação de assinatura registram pedido e status HTTP. `PaymentOperationException` mantém a mensagem segura sem encadear a exceção remota.
- Regressões injetam sentinelas de e-mail, endereço e token e exigem que fiquem fora de logs. Unit MP completo: 156 testes / 502 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint nos arquivos alterados PASS. Nenhuma chamada externa ou pagamento foi realizado.

## 0.1.0-dev.20 — gateway de teste ativo e credenciais fora do config (2026-10-27)

- O gateway `mercado_pago` fica ativo no `config/sync`; o modo efetivo continua sendo escolhido pelo Deployer (`test` no Runtime de teste). A configuração ativa do Runtime agora coincide com o YAML do gateway, com campos de credenciais vazios.
- `EntitySaveHooks` limpa chaves públicas, tokens e campos de cliente no pré-save, inclusive quando um override de ambiente as injeta em memória. Regressão Unit: valores de teste e produção são apagados antes da persistência.
- Unit MP: 153 testes / 482 assertions PASS. Kernel: 1 teste / 50 assertions PASS, com 77 depreciações upstream. PHPCS Drupal/DrupalPractice (PHP), PHP lint e `node --check` PASS.
- Firefox ESR visível tentou seguir o login do comprador sandbox, mas não confirmou sessão; os domínios Mercado Pago responderam HTTP 403. Nenhum pagamento ou estorno foi criado.

## 0.1.0-dev.19 — falhas de transporte no webhook e retorno (2026-10-27)

- `CheckoutPro::onNotify()` responde 503 para falhas de rede/timeout na consulta do pagamento e comerciante, permitindo reentrega; `onReturn()` falha fechado com mensagem segura sem persistência.
- Logs dos callbacks registram só o ID técnico do pagamento, sem corpo ou mensagem privada da exceção. Regressões simulam timeout com texto sensível e confirmam resposta segura e ausência de exposição.
- Unit MP: 152 testes / 480 assertions PASS; PHP lint e PHPCS Drupal/DrupalPractice focais PASS. Kernel, sandbox e fluxo ponta a ponta pendentes.

## 0.1.0-dev.18 — cancelamento e reconciliação sob lock (2026-10-27)

- `MercadoPagoPaymentService` compartilha o lock financeiro do webhook, recarrega o pagamento Commerce, exige ID remoto igual, total e moeda correspondentes na consulta e serializa cancelamento/atualização.
- Cancelamento aceita os estados oficiais `pending`, `in_process` e `authorized`; valida resposta `cancelled` e mesmo ID. Repetição após timeout reconcilia o estado local sem nova operação externa. Falhas de transporte e respostas do provedor não expõem dados privados em logs ou mensagens.
- A referência oficial confirma `PUT /v1/payments/{payment_id}` e os estados permitidos. Unit MP: 150 testes / 464 assertions PASS; PHP lint e PHPCS Drupal/DrupalPractice focais PASS. Kernel, sandbox e fluxo ponta a ponta pendentes.

## 0.1.0-dev.17 — estorno idempotente e isolado por gateway (2026-10-27)

- `refundPayment()` usa `MercadoPagoClientFactory` para selecionar o token do gateway ativo; serializa pelo pagamento remoto, recarrega o pagamento Commerce sob lock e confere saldo/moeda atualizados.
- Envia `x-idempotency-key` determinística pelo ID remoto, moeda e valor acumulado, permitindo retry seguro após timeout; só persiste o estado local quando a resposta corresponde ao pagamento e está `approved`.
- Exceções e corpos do provedor não são gravados nos logs. Testes cobrem estorno parcial e total, cliente do gateway correto, repetição com chave idempotente igual, contenção concorrente, resposta remota pendente sem persistência e erro remoto sem vazamento em log/mensagem.
- Unit MP: 143 testes / 435 assertions PASS; lint e PHPCS Drupal/DrupalPractice dos PHP alterados: PASS. Kernel com Commerce e sandbox financeiro seguem pendentes.

## 0.1.0-dev.14 — contratos neutros para operações do provedor

- Serviços MP implementam as interfaces neutras do Portal e exportam `aculta_portal.payment_provider.subscription_status`, `.subscription_operations`, `.payment_operations` e `.refund_policy`; os IDs proprietários continuam internos.
- Falhas de API são embrulhadas em `PaymentOperationException` com mensagem segura para a interface. A mensagem crua da API é enviada somente ao log.
- Unit MP: 118 testes / 260 assertions PASS, incluindo estorno, ações de assinatura e proteção de mensagens.

## 0.1.0-dev.13 — hooks de administração e elegibilidade no módulo próprio

- `RefundEligibilitySubscriber` e os ramos de presave, acesso e formulário do gateway passam ao módulo MP; os serviços não dependem mais de `CheckoutSettings` do Portal.
- A regra `bloqueiaEstorno` e seus casos permanecem inalterados.
- Unit do MP: 111 testes / 231 assertions PASS (inclui os 22 casos contratuais informados e os novos testes de acesso/form); PHP lint: 43 arquivos PASS; PHPCS Drupal/DrupalPractice: zero erros e zero avisos nos arquivos alterados; services.yml parseados; `git diff --check` PASS.
- `validate-portal-commerce-security.php` segue adiado até site descartável; não foi executado no Runtime.

## 0.1.0-dev.12 — credenciais e webhook no módulo próprio

- Credenciais/override de ambiente e guarda/subscriber do webhook passam ao módulo próprio; IDs de serviço renomeados sem aliases.
- Mantidos `commerce_payment.notify`, prioridade 29, headers `x-signature`/`x-request-id`, Key `mercadopago_webhook_secret`, env `MERCADOPAGO_WEBHOOK_SECRET`, gateway `mercado_pago`, plugin e comportamento fail-closed.
- Unit: 18 testes / 36 assertions PASS. PHP lint: 8 arquivos PASS; PHPCS Drupal/DrupalPractice: 0 erros / 0 avisos; os dois services.yml parseiam. O validator de segurança não foi executado: só tem entrada Drupal/runtime, sem modo estático isolado; será exercitado em instalação descartável, nunca no Runtime.

## 0.1.0-dev.11 — serviços de pagamento e recorrência no módulo próprio

- Serviços de pagamento, recorrência, gravação de assinaturas e notificações passam a ser declarados pelo módulo; IDs renomeados para `aculta_pagamentos_mercadopago.*`, sem alias.
- Canal de log passa a `aculta_pagamentos_mercadopago`. Os valores persistidos e regras da integração permanecem iguais.
- Suítes dos arquivos alterados: 32 testes / 106 assertions PASS; PHP lint em 18 arquivos PASS; PHPCS sem erros e com 77 avisos de linha longa em comentários. Os quatro testes de serviço foram movidos junto às classes; os dois services.yml parseiam.

## 0.1.0-dev.10 — extração das regras puras e fábrica do SDK

- Move `MercadoPagoApiException`, `RecurrenceRequestBuilder`, `MercadoPagoClientFactory` e `MercadoPagoPaymentPolicy` para o módulo Mercado Pago; seus três testes também passam a pertencer ao módulo.
- Mantidos IDs de serviço atuais até o passo 2; comportamento e valores persistidos não mudam.
- Suítes dos arquivos alterados: 82 testes / 177 assertions PASS; PHP lint em 22 arquivos PASS; PHPCS sem erros e com 102 avisos de linha longa em comentários.

# Changelog — aculta_pagamentos_mercadopago

## 0.1.0-dev.16 — cobrança recorrente serializada e validada (2026-10-27)

- Recorrência confirma que as respostas de fatura e assinatura são os recursos consultados; a assinatura deve coincidir com a registrada no pedido. Cobrança aprovada exige pagamento remoto numérico, valor positivo, moeda e total iguais ao pedido antes da gravação.
- Gravação usa lock por gateway/pagamento remoto e procura duplicatas globalmente nesse gateway dentro da seção serializada; recusa associação do mesmo pagamento a outro pedido. Contenção do lock retorna falha retryable.
- Novas regressões cobrem lock ocupado, cobrança repetida, IDs de resposta/assinatura divergentes, valor/moeda incorretos, IDs-array e vínculo prévio a outro pedido. `CheckoutProNotificationTest` também cobre aprovação remota de autorização existente, retorno aprovado duplicado e falha transitória da API (webhook 503, retorno fail-closed). Unit MP: 138 testes / 369 assertions PASS; lint e PHPCS Drupal/DrupalPractice dos PHP alterados: PASS sem avisos.
- `drush cr` e bootstrap do serviço DI PASS. Kernel não executado: `SIMPLETEST_DB` não está configurada; nenhum arquivo de segredos foi lido. Sem chamada externa, atualização de schema, importação de config ou escrita de entidade.

## 0.1.0-dev.15 — confirmação e idempotência de pagamentos (2026-10-27)

- Webhook só conclui pagamento após consultar a API e confirmar aprovação, vínculo ao pedido, moeda e valor integral; falhas transitórias da API retornam 503 para reentrega.
- Retorno exige associação comprovada ao pedido e ao merchant order, compara o valor integral e grava o status remoto consultado, sem confiar no status da URL.
- Webhook e retorno usam lock por ID remoto e repetem a busca do pagamento Commerce dentro do lock para evitar duplicidade concorrente.
- Atualizada a documentação do contrato: a preferência não envia `source_news=ipn`.
- Regressão `CheckoutProNotificationTest`: IDs/topic inválidos (inclui ID-array sem warning), status remoto pendente não adquire lock nem abre storage financeiro, referências divergentes não associam pedido e retorno que mente `approved` é recusado quando a API diz `pending`. Unit MP: 126 testes / 297 assertions PASS; sem depreciações PHPUnit.
- PHP lint, PHPCS Drupal/DrupalPractice dos dois arquivos alterados e `git diff --check`: PASS. No checkout servido, `drush cr` PASS; MAIN e SHOP HTTP 200; webhook POST sem assinatura HTTP 503 (falha fechada; credencial/gateway não inspecionados).
- Firefox ESR visível em X11/geckodriver carregou MAIN, SHOP e Apoio (3/3). Cobertura visual é smoke de página pública; não exercitou autenticação, checkout transacional ou sandbox. Nenhum pagamento ou credencial foi acionado.

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
- Situação histórica: essa folha e os campos de credenciais foram removidos em `0.1.0-dev.61`; as chaves são exclusivamente fornecidas pelo ambiente ativo.
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
