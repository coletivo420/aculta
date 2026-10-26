# Extração da integração Mercado Pago do aculta_portal — Fase B0

Base: `origin/chore/aculta-m0-integridade` @ `6253ef8609f76bbc7a21e64cc1543d2ecf226f87`.
Data do inventário: 2026-10-26. Documento somente leitura: nenhum código foi alterado.

## Decisão e objetivo

Decisão do responsável: **opção B** — a integração Mercado Pago sai do `aculta_portal` e passa a viver em
`aculta_pagamentos_mercadopago`, eliminando a aresta `aculta_portal.info.yml:34`.

Hoje o ciclo é duplo:

- **Portal → MP:** `aculta_portal.info.yml:34` declara `aculta_portal:aculta_pagamentos_mercadopago`.
- **MP → Portal:** `aculta_pagamentos_mercadopago.info.yml` declara `aculta_portal:aculta_portal` e o
  `CheckoutPopupReturnController` usa `Drupal\aculta_portal\Integration\PopupReturnPage`.

O alvo da B2 é manter apenas **MP → Portal**. O submódulo continua fisicamente em
`web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/`; caminho de submódulo não
implica dependência.

Além do ciclo, há uma aresta de classe: `MercadoPagoClientFactory` importa
`Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway\CheckoutPro` (Portal → MP). A extração
também remove essa aresta.

## 1. Inventário classe por classe

Namespace de destino proposto: manter os sufixos atuais sob `Drupal\aculta_pagamentos_mercadopago\`.
Exemplo: `Drupal\aculta_portal\MercadoPago\X` → `Drupal\aculta_pagamentos_mercadopago\MercadoPago\X`.
Achatar o segmento `MercadoPago` (que fica redundante no módulo) é uma decisão separada (ver “Decisões”).

| # | Classe (origem) | Destino | Dependências injetadas | Consumidores atuais |
|---|---|---|---|---|
| 1 | `Commerce/MercadoPago/BillingAddressAliasItemList.php` | **Fica no Portal** | nenhuma (estende `FieldItemList`; usa `field_address`) | `ProfileAddressAliasHooks`; alias `address` usado por `aculta_donation/ThankYouPane`, `aculta_commerce_melhorenvio/MelhorEnvio` e `aculta_pagamentos_mercadopago/RedirectCheckoutForm` |
| 2 | `Commerce/MercadoPago/MercadoPagoCredentials.php` | MP: `Commerce\MercadoPago\` | nenhuma (estático); usa `Drupal\aculta_portal\Environment\DeployerEnvironment` | `MercadoPagoEnvironmentOverride`, `EntitySaveHooks`, scripts de validação, testes |
| 3 | `Commerce/MercadoPago/MercadoPagoEnvironmentOverride.php` | MP: `Commerce\MercadoPago\` | `%app.root%` | tag `config.factory.override`; script `validate-portal-commerce-security.php:455-482` |
| 4 | `Commerce/MercadoPago/WebhookEventSubscriber.php` | MP: `Commerce\MercadoPago\` | `entity_type.manager`, `key.repository`, `WebhookGuard` | tag `event_subscriber`; webhook do Commerce |
| 5 | `Commerce/MercadoPago/WebhookGuard.php` | MP: `Commerce\MercadoPago\` | `?subscription_notifications`, `?logger` | `WebhookEventSubscriber`; registry de helpers do Portal |
| 6 | `MercadoPago/MercadoPagoApiException.php` | MP: `MercadoPago\` | nenhuma | `MercadoPagoPaymentService`, `RecurringAuthorizationPane` (Doação), `SubscriptionActionForm`, `SalesPaymentCancelForm`, `SalesPaymentRefreshForm`, `RefundEligibilitySubscriber` |
| 7 | `MercadoPago/MercadoPagoClientFactory.php` | MP: `MercadoPago\` | nenhuma no construtor; importa `CheckoutPro` (MP) | `PaymentService`, `RecurrenceService`, `SubscriptionNotificationService`; testes |
| 8 | `MercadoPago/MercadoPagoPaymentService.php` | MP: `MercadoPago\` | `client_factory`, `payment_policy`, `logger` | `SalesPaymentCancelForm`, `SalesPaymentRefreshForm` |
| 9 | `MercadoPago/MercadoPagoRecurrenceService.php` | MP: `MercadoPago\` | `client_factory`, `recurrence_request_builder`, `logger` | `SubscriptionNotificationService`, `SubscriptionRecorder`, `RecurringAuthorizationPane` (Doação), `SubscriptionActionForm`, `SupportController` |
| 10 | `MercadoPago/MercadoPagoSubscriptionNotificationService.php` | MP: `MercadoPago\` | `entity_type.manager`, `client_factory`, `recurrence_service`, `http_client`, `logger`, `subscription_recorder` | `WebhookGuard` |
| 11 | `MercadoPago/MercadoPagoSubscriptionRecorder.php` | MP: `MercadoPago\` | `entity_type.manager`, `recurrence_service`, `logger`, `time` | `RecurringDonationsReport` (Portal/Doação), `SupportController`, `SubscriptionActionForm`, `RecurringAuthorizationPane`, `WebhookGuard` |
| 12 | `MercadoPago/RecurrenceRequestBuilder.php` | MP: `MercadoPago\` | nenhuma | `MercadoPagoRecurrenceService` |
| 13 | `MercadoPago/RefundEligibilitySubscriber.php` | MP: `MercadoPago\` | `config.factory`, `payment_policy`, `?CheckoutSettings` (Portal) | evento `RefundEligibilityEvent` do MP; `aculta_donation.settings` |
| 14 | `Sales/MercadoPagoPaymentPolicy.php` | MP: `Sales\` | nenhuma (constantes e métodos puros) | `PaymentService`, `RefundEligibilitySubscriber`, `SalesOrdersTableBuilder` |

Constantes que carregam identificadores persistidos ou contratuais e **não podem mudar de valor**:

- `MercadoPagoCredentials::ENVIRONMENTS` → variáveis `MERCADOPAGO_TEST_PUBLIC_KEY`,
  `MERCADOPAGO_TEST_ACCESS_TOKEN`, `MERCADOPAGO_PRODUCTION_PUBLIC_KEY`,
  `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN`; campos `public_key_test`, `access_token_test`,
  `public_key_prod`, `access_token_prod`.
- `MercadoPagoClientFactory::CHAVES_TOKEN` → `access_token_test`, `access_token_stage`,
  `access_token_prod`.
- `MercadoPagoSubscriptionRecorder` → `mp_preapproval_id`, `mp_status`, `mp_cobranca_situacao`,
  `assinatura`, estados `pending`/`authorized`/`paused`/`cancelled` e rótulos de cobrança.
- `MercadoPagoPaymentPolicy` → `pending`, `in_process`, `authorized`, `completed`,
  `partially_refunded`, `curso`, `assinatura`.
- `WebhookEventSubscriber` → rota `commerce_payment.notify`, gateway `mercado_pago`, Key
  `mercadopago_webhook_secret`, variável `MERCADOPAGO_WEBHOOK_SECRET`.

## 2. Serviços

Hoje há 12 definições MP no `aculta_portal.services.yml` (11 serviços + logger):

| ID atual | Linha | Classe | Argumentos atuais |
|---|---:|---|---|
| `aculta_portal.mercado_pago_environment_override` | 269 | EnvironmentOverride | `%app.root%` |
| `aculta_portal.mercado_pago.subscription_notifications` | 276 | SubscriptionNotificationService | `entity_type.manager`, `.client_factory`, `.recurrence_service`, `http_client`, `.logger`, `.subscription_recorder` |
| `aculta_portal.mercado_pago.subscription_recorder` | 282 | SubscriptionRecorder | `entity_type.manager`, `.recurrence_service`, `.logger`, `datetime.time` |
| `aculta_portal.mercado_pago_webhook_guard` | 285 | WebhookGuard | `.subscription_notifications`, `.logger` |
| `aculta_portal.mercado_pago_webhook_subscriber` | 290 | WebhookEventSubscriber | `entity_type.manager`, `key.repository`, `.webhook_guard` |
| `aculta_portal.mercado_pago.logger` | 372 | LoggerChannelInterface | channel `aculta_portal` |
| `aculta_portal.mercado_pago.client_factory` | 376 | ClientFactory | — |
| `aculta_portal.mercado_pago.recurrence_request_builder` | 378 | RecurrenceRequestBuilder | — |
| `aculta_portal.mercado_pago.payment_policy` | 380 | PaymentPolicy | — |
| `aculta_portal.mercado_pago.refund_eligibility_subscriber` | 382 | RefundEligibilitySubscriber | `config.factory`, `.payment_policy`, `aculta_portal.checkout_settings` |
| `aculta_portal.mercado_pago.payment_service` | 387 | PaymentService | `.client_factory`, `.payment_policy`, `.logger` |
| `aculta_portal.mercado_pago.recurrence_service` | 390 | RecurrenceService | `.client_factory`, `.recurrence_request_builder`, `.logger` |

Serviços do Portal que **não** são MP, mas dependem deles:

- `aculta_portal.recurring_donations_report` (linha 279) injeta
  `aculta_portal.mercado_pago.subscription_recorder`.
- `aculta_portal.mercado_pago.refund_eligibility_subscriber` injeta
  `aculta_portal.checkout_settings` (Portal). Como MP continua dependendo do Portal, isso é permitido.

### Recomendação de IDs

**Renomear para `aculta_pagamentos_mercadopago.*` e atualizar todos os consumidores no mesmo commit, sem
aliases.** A política do projeto rejeita aliases temporários de compatibilidade.

Prós: o ID passa a refletir o dono; remove a ambiguidade atual entre `mercado_pago_*` e `mercado_pago.*`;
impede que código do Portal volte a depender do prefixo do Portal para o domínio MP.
Contras: exige atualizar de uma vez status registries, forms, controller, painel de doação, scripts e
testes; qualquer extensão externa que busque o ID antigo quebra (não há consumidores externos no
repositório além dos listados).

Alternativa: **manter os IDs**. Prós: menor churn e nenhum risco de ID órfão. Contras: perpetua o prefixo
errado e a mistura de padrões; o serviço continua parecendo do Portal. Se mantiver, não criar alias
secundário — apenas não renomear.

Recomendação adicional: o logger deve usar o channel `aculta_pagamentos_mercadopago` (hoje
`aculta_portal`). Logs não são persistidos em banco, mas mudam a categoria; verificar se alguma
configuração de logging referencia o channel.

## 3. Hooks, event subscribers, rotas, permissões, config, State e Key IDs

### Hooks do Portal com trecho MP

| Hook | Arquivo | O que faz | Decisão proposta |
|---|---|---|---|
| `entity_presave` | `src/Hook/EntitySaveHooks.php:20-35` | Impede habilitar o gateway sem credenciais do ambiente (fail-closed) | **Mover** para o MP como hook próprio |
| `entity_access` | `src/Hook/EntityHooks.php:99-107` | Proíbe editar o gateway pelo formulário padrão | **Mover** para o MP |
| `form_alter` | `src/Hook/FormHooks.php:160-180` | Transforma tokens em campos de senha no formulário do gateway | **Mover** para o MP |
| `entity_bundle_field_info` | `src/Hook/ProfileAddressAliasHooks.php` | Expõe `address` computado no perfil | **Fica no Portal** — usado por Doação, Melhor Envio e MP |

### Event subscribers e override

| Item | Arquivo | Registro | Observação |
|---|---|---|---|
| Webhook | `WebhookEventSubscriber` | `KernelEvents::REQUEST`, prioridade 29 | Escuta a rota `commerce_payment.notify` do Commerce; não é rota própria |
| Estorno | `RefundEligibilitySubscriber` | `RefundEligibilityEvent::NAME` (MP) | Lê `aculta_donation.settings` |
| Ambiente | `MercadoPagoEnvironmentOverride` | tag `config.factory.override`, prioridade 100 | Sobrescreve `commerce_payment.commerce_payment_gateway.mercado_pago` |

### Rotas

- **Webhook:** `commerce_payment.notify` (Commerce core). Caminho, método, assinatura e prioridade 29
  **não podem mudar**. O `WebhookGuard` responde 503/405/401/400 fail-closed.
- **Retorno do checkout:** `aculta_pagamentos_mercadopago.checkout_popup_return`
  (`/integracoes/mercadopago/checkout-retorno`) já está no MP. O `PaymentDomainPathProcessor` do Portal
  (`src/Routing/PaymentDomainPathProcessor.php:25`) mantém a regex interna dessa rota; se o módulo for
  desabilitado, a rota deixa de existir e a regex fica inerte.

### Permissões

Ficam no Portal, porque as rotas e formulários permanecem no Portal:

- `gerenciar pagamentos de vendas` — cancelar/estornar vendas.
- `administrar doacoes` — relatório e ações de doação.

Se os formulários de venda forem movidos para o MP (não recomendado nesta fase), as permissões precisam
acompanhar a rota.

### Config, State e Key IDs

| Tipo | ID | Dono após B2 | Pode mudar? |
|---|---|---|---|
| Config entity (Commerce) | `commerce_payment.commerce_payment_gateway.mercado_pago` | Commerce / MP | **Não** — está no Config Sync e referencia o plugin `aculta_mercadopago_checkout_pro` |
| Plugin id | `aculta_mercadopago_checkout_pro` | MP | **Não** — renomear órfã a config entity existente |
| Config do Portal | `aculta_portal.settings:mercado_pago_account_url` | Portal | Pode ficar; é só um link de UI, sem classe MP |
| Config de schema | `commerce_payment.commerce_payment_gateway.plugin.aculta_mercadopago_checkout_pro` | MP | **Não** |
| Key | `mercadopago_webhook_secret` (provider `env`, `MERCADOPAGO_WEBHOOK_SECRET`) | MP | **Não** |
| State | nenhum `StateInterface` encontrado no código MP | — | — |
| Dados em `commerce_order.data` | `mp_preapproval_id`, `mp_status`, `mp_cobranca_situacao` | MP | **Não** — valores persistidos em pedidos existentes |
| Cache suffix do override | `aculta_portal_mercado_pago_environment` | MP | Pode mudar (só cache), mas não é necessário |

## 4. Usos do Portal que não podem ir para o submódulo

O Portal não pode referenciar classes/serviços do MP depois da B2. Estes consumidores precisam de
inversão. A recomendação é **interface definida no Portal, implementada pelo MP** (MP pode depender do
Portal) ou `moduleExists` quando o uso é opcional.

| Consumidor | O que usa hoje | Como fica |
|---|---|---|
| `aculta_portal/src/Donation/RecurringDonationsReport.php` | `MercadoPagoSubscriptionRecorder` (status, rótulos, `TIPO_PEDIDO`, `STATUS_ORDEM`) | Interface `SubscriptionStatusInterface` no Portal (status do pedido, rótulos, tipo de pedido, ordem de status), implementada pelo recorder no MP; o serviço do relatório recebe a interface opcional |
| `src/Support/Controller/SupportController.php` | `MercadoPagoSubscriptionRecorder` + service id | Mesma interface; `moduleExists('aculta_pagamentos_mercadopago')` para exibir o bloco de assinatura |
| `src/Support/Form/SubscriptionActionForm.php` | Recorder + `MercadoPagoApiException` + service id | Interface de ações (`pausar`, `reativar`, `cancelar`) e exceção neutra definida no Portal |
| `src/Form/SalesPaymentCancelForm.php` / `SalesPaymentRefreshForm.php` | `MercadoPagoPaymentService`, `MercadoPagoApiException`, service id | Interface `PaymentOperationsInterface` (cancelar, atualizar, estornar) no Portal; implementação no MP; formulário só aparece se `moduleExists` |
| `src/Sales/SalesOrdersTableBuilder.php:146` | `MercadoPagoPaymentPolicy::ESTORNAVEIS` | Interface `RefundPolicyInterface::isRefundable(string $state)` no Portal; implementação no MP |
| `src/Controller/PortalRequirementsController.php:122-125` | Nome do módulo e pacote `mercadopago/dx-php` | `moduleExists` + lista de extensões; passa a reportar o MP como módulo separado/opcional |
| `src/Status/PortalHelperRegistry.php:212` / `PortalImplementationRegistry.php:22-23` | IDs de serviço e rótulos | Atualizar IDs se renomeados; remover entradas MP do Portal ou movê-las para um registry do MP |
| `src/Controller/PortalController.php:190-197` | Config `mercado_pago_account_url` | Permanece no Portal; é string de configuração, sem classe MP |

O `ProfileAddressAliasHooks` e o `BillingAddressAliasItemList` **não** entram nessa lista: são capacidade
compartilhada de perfil/endereço usada por Doação e Melhor Envio, não domínio MP.

## 5. Consumidores externos

### aculta_donation

`modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/RecurringAuthorizationPane.php` usa:
`MercadoPagoApiException`, `MercadoPagoRecurrenceService`, `MercadoPagoSubscriptionRecorder` e os service
ids `aculta_portal.mercado_pago.recurrence_service` e `aculta_portal.mercado_pago.logger`.

Recomendação: **não** fazer `aculta_donation` depender do MP. O painel é específico de MP, mas a Doação
deve continuar agnóstica a gateway. Duas saídas:

1. **Mover o painel para o MP** (recomendado se ele não tiver uso fora do MP): o MP já depende do Portal e
   pode depender de `aculta_donation`; a descoberta de CheckoutPane é por atributo, não por dependência.
2. **Inverter por interface no Portal** (`RecurrenceInterface` + exceção neutra): a Doação fica igual e o
   MP implementa.

Se o responsável preferir o caminho mais curto, `aculta_donation` pode declarar
`aculta_portal:aculta_pagamentos_mercadopago`; isso não cria ciclo (Doação → MP → Portal), mas acopla a
Doação ao gateway. Não é a recomendação.

### Scripts

- `scripts/validate-aculta-portal-drupal11.php`: imports/asserções em `355-357` e `537-538`; lista de
  arquivos em `826` inclui `src/Commerce/MercadoPago/WebhookEventSubscriber.php`. Atualizar namespace e
  caminho.
- `scripts/validate-portal-commerce-security.php`: `450-728` usa
  `MercadoPagoCredentials::currentEnvironment`, o service id
  `aculta_portal.mercado_pago_environment_override`, a Key e o plugin id do gateway. Atualizar namespace
  e ID; **não** alterar as asserções de comportamento (fail-closed, tokens não persistidos, gateway
  habilitado só com credencial do ambiente).

### Testes

Há **13** testes do Portal que citam MP (o briefing mencionava 12):

1. `Unit/Controller/DonationReportControllerTest.php`
2. `Unit/MercadoPagoCredentialsTest.php`
3. `Unit/MercadoPago/MercadoPagoClientFactoryTest.php`
4. `Unit/MercadoPago/MercadoPagoPaymentServiceTest.php`
5. `Unit/MercadoPago/MercadoPagoRecurrenceServiceTest.php`
6. `Unit/MercadoPago/MercadoPagoSubscriptionActionsTest.php`
7. `Unit/MercadoPago/MercadoPagoSubscriptionRecorderTest.php`
8. `Unit/MercadoPago/RecurrenceRequestBuilderTest.php`
9. `Unit/MercadoPago/RefundEligibilitySubscriberTest.php`
10. `Unit/MercadoPagoSubscriptionNotificationServiceTest.php`
11. `Unit/MercadoPagoSubscriptionWebhookTest.php`
12. `Unit/MercadoPagoWebhookFailClosedTest.php`
13. `Unit/Sales/MercadoPagoPaymentPolicyTest.php`

Destino: os testes 2-13 acompanham as classes para `modules/aculta_pagamentos_mercadopago/tests/`; o teste
1 fica no Portal usando um stub da interface de status. Os dois testes do MP
(`tests/src/Kernel/CheckoutProTest.php`, `tests/src/Unit/CheckoutPopupReturnControllerTest.php`) não mudam
de dono.

Testes de outros submódulos que apenas listam `aculta_pagamentos_mercadopago` como módulo exigido
(`SitemapWiringKernelTest`, `SecretsRoutesKernelTest`, `ShippingEngineKernelTest`,
`MelhorEnvioWiringKernelTest`) continuam válidos; conferir a lista após a extração.

## 6. Ordem de extração (commits atômicos na B2)

| Passo | Conteúdo | Teste que prova |
|---:|---|---|
| 1 | Mover `MercadoPagoApiException`, `RecurrenceRequestBuilder`, `MercadoPagoClientFactory`, `MercadoPagoPaymentPolicy` e seus testes; atualizar `use` | Unit `RecurrenceRequestBuilderTest`, `MercadoPagoClientFactoryTest`, `MercadoPagoPaymentPolicyTest` |
| 2 | Mover os serviços de pagamento/assinatura (`PaymentService`, `RecurrenceService`, `SubscriptionRecorder`, `SubscriptionNotificationService`) + `services.yml` com IDs novos | Unit `MercadoPagoPaymentServiceTest`, `MercadoPagoRecurrenceServiceTest`, `MercadoPagoSubscriptionRecorderTest`, `MercadoPagoSubscriptionNotificationServiceTest` |
| 3 | Mover credenciais, override de ambiente, webhook (`Credentials`, `EnvironmentOverride`, `WebhookGuard`, `WebhookEventSubscriber`) e testes | `MercadoPagoCredentialsTest`, `MercadoPagoWebhookFailClosedTest`, `MercadoPagoSubscriptionWebhookTest`; script de segurança |
| 4 | Mover `RefundEligibilitySubscriber` e os ramos MP dos hooks (`EntitySaveHooks`, `EntityHooks`, `FormHooks`); manter `ProfileAddressAliasHooks` no Portal | Unit `RefundEligibilitySubscriberTest`; teste de acesso/form do gateway (criar se não houver) |
| 5 | Inverter consumidores do Portal com interfaces (`SubscriptionStatusInterface`, `PaymentOperationsInterface`, `RefundPolicyInterface`, exceção neutra); ajustar Doação (mover o painel ou interface) | `DonationReportControllerTest` + testes de Doação; Unit dos formulários de venda |
| 6 | Remover `aculta_portal:aculta_pagamentos_mercadopago` de `aculta_portal.info.yml` | `drush pm:list`/instalação sem ciclo; Kernel de wiring dos submódulos |
| 7 | Atualizar scripts, status YAML, CHANGELOGs e versões; regenerar a tabela do README pelo dono | `validate-aculta-portal-drupal11.php`, `validate-portal-commerce-security.php`, `validate-project-status-index.php --write` (dono do README) |

Cada passo deve deixar a árvore verde: PHP lint, PHPCS Drupal/DrupalPractice e os testes citados. Os
passos 1-4 são movimentação mecânica; o passo 5 é o único com desenho novo.

## 7. Riscos

| Risco | Impacto | Mitigação |
|---|---|---|
| Webhook em produção | Alta | Rota `commerce_payment.notify`, prioridade 29, headers `x-signature`/`x-request-id`, Key `mercadopago_webhook_secret` e env `MERCADOPAGO_WEBHOOK_SECRET` **não mudam**. Teste de fail-closed e smoke autenticado no servidor de testes, com `live_mode` conferido |
| Config entity do gateway | Alta | Não mudar `mercado_pago` nem o plugin id `aculta_mercadopago_checkout_pro`; o Config Sync referencia ambos |
| Dados persistidos em pedidos | Alta | Manter valores de `mp_preapproval_id`, `mp_status`, `mp_cobranca_situacao`; não renomear constantes de valor |
| Renome de service IDs | Média | Atualizar todos os consumidores no mesmo commit; sem alias; `drush cr` no deploy. IDs não são persistidos em banco |
| Override de ambiente desabilitado | Média | O override só existe com MP habilitado. Garantir que o deploy mantenha o módulo habilitado; o gateway fica fail-closed sem credenciais |
| Hooks MP fora do Portal | Média | Mover o ramo exato (presave, access, form_alter) sem alterar a mensagem nem a condição; teste de acesso ao formulário do gateway |
| `ProfileAddressAliasHooks` | Média | **Não mover**: Doação (ThankYouPane) e Melhor Envio dependem de `profile.address` mesmo sem MP |
| Status/registries do Portal | Baixa | Atualizar IDs e rótulos; não deixar entrada apontando para serviço inexistente |
| Update de banco | Baixa | Nenhum schema/State/config persistido muda. Se alguma config for movida, exigir `hook_post_update` com migração e rollback |
| Rollback | Baixa | Reverter os commits e rodar `drush cr`; sem migração de dados. O gateway e a Key permanecem intactos |

## 8. Critérios de aceite da B2

1. `aculta_portal.info.yml` não declara `aculta_pagamentos_mercadopago`; `aculta_pagamentos_mercadopago`
   continua declarando `aculta_portal`.
2. Nenhum arquivo de `aculta_portal` (fora do submódulo MP) referencia classe ou service id
   `*mercado_pago*`/`MercadoPago`, exceto strings de configuração (`mercado_pago_account_url`) e
   `moduleExists`.
3. Nenhum arquivo do MP referencia `Drupal\aculta_portal\...` para classes movidas; o que restar é
   dependência legítima do Portal (`PopupReturnPage`, `DeployerEnvironment`, `CheckoutSettings`).
4. Rota do webhook, prioridade, headers, Key ID, env var, config entity do gateway e plugin id
   inalterados; teste fail-closed passa.
5. Os 13 testes de MP estão no dono correto e passam; Doação e Melhor Envio continuam usando
   `profile.address` com MP desabilitado.
6. `aculta_portal.recurring_donations_report`, formulários de venda e painel de suporte funcionam com a
   interface do Portal e a implementação do MP, e degradam com `moduleExists` quando o MP está ausente.
7. Scripts `validate-aculta-portal-drupal11.php` e `validate-portal-commerce-security.php` passam com os
   novos namespaces/IDs.
8. PHP lint, PHPCS Drupal/DrupalPractice e os gates de status/pacotes verdes; tabela do README
   regenerada pelo dono.
9. Nenhum alias de serviço ou rota temporário.
10. Deploy no servidor de testes com smoke de webhook e checkout; produção só na janela de RC.

## Decisões que dependem do responsável

1. **IDs de serviço:** renomear para `aculta_pagamentos_mercadopago.*` (recomendado) ou manter os atuais.
2. **Painel `RecurringAuthorizationPane`:** mover para o MP (recomendado) ou inverter por interface no
   Portal. A Doação não deve declarar dependência do MP.
3. **Hooks MP:** mover os ramos de `EntitySaveHooks`/`EntityHooks`/`FormHooks` para o MP (recomendado) ou
   manter no Portal com `moduleExists`.
4. **Namespaces:** manter os sufixos atuais sob o MP ou achatar o segmento `MercadoPago` (mais churn).
5. **Log channel:** renomear para `aculta_pagamentos_mercadopago` ou manter `aculta_portal`.
6. **Versões:** `aculta_pagamentos_mercadopago` sobe de `0.1.0-dev.9`; `aculta_portal` sobe só se o
   contrato público mudar (interfaces novas).
