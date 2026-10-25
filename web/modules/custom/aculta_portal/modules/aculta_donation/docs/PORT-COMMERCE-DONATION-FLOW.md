# Port do commerce_donation_flow para o aculta_donation

Status (2026-10-17): **port aplicado** (código, blocos, configuração, Composer), **recorrência aplicada no código** (ver "Recorrência"), com ações do doador em "Meu apoio" e painel de doações recorrentes. Pendente: ponta a ponta no sandbox do Mercado Pago (plano em `docs/portal/MERCADO-PAGO-PLANO.md`, seção 7.2).

## Decisões do responsável (2026-10-17)

- Assinaturas viram um único tipo de pedido, agrupando as cobranças.
- Doações e assinaturas não podem ser estornadas.
- Recorrência: portar do `commerce_recurring` só o necessário, sem adicionar dependência Composer.
- O `commerce_donation_flow` (contrib) também sai: copiar o que for preciso para o `aculta_donation` e não manter o contrib.

## Fonte analisada

- `web/modules/contrib/commerce_donation_flow` (versão instalada, `core_version_requirement: ^10.3 || ^11`, GPL-2.0-or-later).
- Código PHP: 3.406 linhas. Configuração, Twig e componentes: cerca de 2.000 linhas.
- `commerce_recurring` 1.0.0-rc3 (Packagist: `^9.2 || ^10 || ^11` do Core, `^2.36 || ^3` do Commerce). O branch `master` do repositório é Drupal 7 (2011), não serve.

## Mapa do que portar

| Origem (contrib) | Destino (`aculta_donation`) | Uso no site |
|---|---|---|
| `NewOrder` | `Drupal\aculta_donation\NewOrder` (mesmo nome de classe, sem subpasta) | Cria pedido e item de doação; doação mensal cria pedido `assinatura` |
| `Controller\DonationController` | `Controller\DonationController` | `/donate`, passos do checkout, doação rápida |
| `EventSubscriber\CartCheckoutRouteSubscriber` | `EventSubscriber\...` | Redireciona o carrinho para o checkout de doação. **Movido em 2026-10-22 (P-CART D)** para `aculta_portal` como `CommerceCheckoutRouteSubscriber`, com o aviso de origem GPL preservado; classe removida do Donation |
| `Plugin\Commerce\CheckoutFlow\DonationCheckoutFlow` (+ `MultistepWithDonation`, `PaneSummaryData`) | `Plugin\Commerce\CheckoutFlow\...` | Fluxo `doacao` |
| `Plugin\Commerce\CheckoutPane\*` (DonationItemPaneBase, DonationCheckoutPane, AddDonationPane, MemorialCheckoutPane, ThankYouPane) | mesmos nomes | Painéis de doação, homenagem e agradecimento |
| `Plugin\Field\FieldWidget\DonationLevelWidget` | mesmo nome | Campo de valor |
| `Plugin\Field\FieldFormatter\DonationSummaryFormatter` | mesmo nome | Resumo |
| `Form\DonationSettingsForm` | mesmo nome | Configuração (ver "Valores padrão") |
| `Routing\DonationSecuredRedirectResponse`, `Exception\MissingDonationItemException` | mesmos nomes | Suporte |
| `templates/*.twig`, `components/` | **ACULTA420** (tema), não o módulo | Apresentação |

Verificar antes de portar: os blocos `DonateBlock`, `QuickDonationBlock` e `BackToSiteBlock` (não encontrei colocação no `config/sync`; confirmar no Runtime) e os testes do contrib (`tests/`), que devem virar testes Kernel em `aculta_simpletest`.

## Riscos do corte

1. **Dados existentes:** o Runtime tem 14 itens de pedido do tipo `donation`. O corte não pode apagá-los.
2. **Desinstalação apaga configuração dependente:** o `commerce_donation_flow` é dependência do tipo de item `donation`, dos campos `field_*` e do fluxo `doacao`. Desinstalar o contrib sem reapontar essas dependências apaga a configuração e pode apagar os itens. Antes: reapontar as dependências para `aculta_donation` em config e exportar, e só depois desinstalar.
3. **Colisão de IDs:** plugins (`fluxo_doacao`, `commerce_donation_pane`, `commerce_memorial_pane`), serviços (`commerce_donation_flow.*`) e rotas (`/donate`) não podem existir ativos em duplicidade. Desenvolver e testar com o contrib desativado e a cópia ativa só no corte.
4. **Dependências no Portal:** `DonationSummaryController`, `SupportForm`, `DonationFriendlyRouteSubscriber`, `DomainRouteSubscriber`, `DomainRoutePolicy`, `PortalRequirementsController` e os scripts `validate-payment-domain-policy.php`, `validate-portal-commerce-security.php` citam o contrib. Precisam apontar para o novo namespace no mesmo corte.
5. **Composer:** remover `drupal/commerce_donation_flow` de `composer.json` é uma mudança de dependência. Exige confirmação antes do `composer remove`.
6. **Licença:** o código copiado é GPL-2.0-or-later. Manter o aviso de origem nos arquivos portados.

## Ordem proposta

1. Portar as classes com namespace novo, sem registrar nada ativo (commit próprio).
2. Portar configuração, campos e fluxo, com dependências em `aculta_donation` (commit próprio).
3. Testes Kernel em `aculta_simpletest` (sem Runtime): fluxo de doação, item de doação, resumo, homenagem, agradecimento.
4. Corte no Runtime, com backup do banco antes e confirmação do responsável. Reapontar config, depois desativar e remover o contrib.
5. Atualizar Portal, gates e `composer.json`.

## Recorrência

Decisão do responsável (2026-10-17): recorrência pela **API de assinaturas do Mercado Pago** (preapproval), reaproveitando código do `commerce_recurring` 8.x-1.0-rc3 (GPL-2.0-or-later), sem adicionar dependência Composer. Fonte lida em `git.drupalcode.org/project/commerce_recurring`, tag `8.x-1.0-rc3`.

O que foi reaproveitado e o que ficou de fora:

- **Reaproveitado:** o workflow `order_recurring` (estados `draft`, `needs_payment`, `failed`, `completed`, `canceled` e as transições), copiado para `aculta_donation.workflows.yml`. Adaptação: `cancel` também parte de `completed`, porque a assinatura continua ativa depois da primeira cobrança.
- **Não reaproveitado:** a entidade `commerce_subscription`, a cobrança pelo próprio Commerce (`RecurringOrderManager`, `Cron`, cobradores) e o meio de pagamento guardado. O Mercado Pago cobra o cartão da autorização, e o portal não guarda meio de pagamento.

Modelo:

1. Doação mensal (`field_gift_type = recurring`) cria pedido do tipo `assinatura` (`NewOrder::get`). O tipo usa o fluxo `assinatura_flow`, que tem a mesma sequência de panes do fluxo de doação, mais a pane `donation_recurring_authorization`.
2. A pane (passo de pagamento, peso 3, antes do `payment_process`) cria a assinatura (`criarAssinatura`: valor do pedido, frequência de 1 mês, BRL, `external_reference` = id do pedido, `back_url` na etapa de conclusão do domínio principal). Guarda o id em `data` (`mp_preapproval_id`), manda o pedido para `needs_payment` e leva o doador ao `init_point` por `NeedsRedirectException`.
3. Webhook `subscription_preapproval` (`MercadoPagoSubscriptionRecorder::assinatura`): guarda o status em `data` (`mp_status`); `cancelled` leva o pedido a `canceled`.
4. Webhook `subscription_authorized_payment` (`MercadoPagoSubscriptionRecorder::cobranca`): consulta `GET /authorized_payments/{id}` e, **só com `payment.status = approved`**, cria um `commerce_payment` (estado `completed`, `remote_id` = id do pagamento no Mercado Pago). Grava uma vez por id. A primeira cobrança leva o pedido a `completed`.

Regras e limites:

- `processed` não é aprovação. A documentação oficial diz que `processed` também marca parcela que esgotou as tentativas (ver [authorized-payments](https://www.mercadopago.com.br/developers/pt/docs/subscriptions/integration-configuration/subscription-no-associated-plan/authorized-payments)).
- O `external_reference` da cobrança não é usado para achar o pedido. O pedido vem do `preapproval_id`, consultando a assinatura.
- Doações não são estornadas (decisão do responsável). Não há tratamento de estorno.
- Pedido `assinatura` fica fora do carrinho unificado (`CartConsolidator::TIPO_ASSINATURA`).
- Ações do doador: pausar, reativar e cancelar em "Meu apoio" (`SubscriptionActionForm`). Painel: seção "Doações recorrentes" no relatório de doações.
- Não verificado: o corpo real das notificações de assinatura no sandbox (a referência consultada não lista todos os valores de `status`). Testes unitários cobrem as regras, mas o fluxo ponta a ponta ficou DEFERRED, porque criar assinatura no sandbox depende de confirmação do responsável.

Cobertura: `tests/src/Unit/MercadoPago/MercadoPagoSubscriptionRecorderTest.php` (5 testes) e `tests/src/Unit/MercadoPagoSubscriptionNotificationServiceTest.php` (3 testes).
