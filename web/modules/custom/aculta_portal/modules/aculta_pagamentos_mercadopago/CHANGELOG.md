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

