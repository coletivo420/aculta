# Port do commerce_donation_flow para o aculta_donation

Status: **plano** (2026-10-17). Nenhuma mudança de código ou de Runtime feita ainda.

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
| `NewOrder` | `Drupal\aculta_donation\Order\DonationOrderFactory` | Cria pedido e item de doação |
| `Controller\DonationController` | `Controller\DonationController` | `/donate`, passos do checkout, doação rápida |
| `EventSubscriber\CartCheckoutRouteSubscriber` | `EventSubscriber\...` | Redireciona o carrinho para o checkout de doação |
| `Plugin\Commerce\CheckoutFlow\DonationCheckoutFlow` (+ `MultistepWithDonation`, `PaneSummaryData`) | `Plugin\Commerce\CheckoutFlow\...` | Fluxo `donation_flow` |
| `Plugin\Commerce\CheckoutPane\*` (DonationItemPaneBase, DonationCheckoutPane, AddDonationPane, MemorialCheckoutPane, ThankYouPane) | mesmos nomes | Painéis de doação, homenagem e agradecimento |
| `Plugin\Field\FieldWidget\DonationLevelWidget` | mesmo nome | Campo de valor |
| `Plugin\Field\FieldFormatter\DonationSummaryFormatter` | mesmo nome | Resumo |
| `Form\DonationSettingsForm` | mesmo nome | Configuração (ver "Valores padrão") |
| `Routing\DonationSecuredRedirectResponse`, `Exception\MissingDonationItemException` | mesmos nomes | Suporte |
| `templates/*.twig`, `components/` | **ACULTA420** (tema), não o módulo | Apresentação |

Verificar antes de portar: os blocos `DonateBlock`, `QuickDonationBlock` e `BackToSiteBlock` (não encontrei colocação no `config/sync`; confirmar no Runtime) e os testes do contrib (`tests/`), que devem virar testes Kernel em `aculta_simpletest`.

## Riscos do corte

1. **Dados existentes:** o Runtime tem 14 itens de pedido do tipo `donation`. O corte não pode apagá-los.
2. **Desinstalação apaga configuração dependente:** o `commerce_donation_flow` é dependência do tipo de item `donation`, dos campos `field_*` e do fluxo `donation_flow`. Desinstalar o contrib sem reapontar essas dependências apaga a configuração e pode apagar os itens. Antes: reapontar as dependências para `aculta_donation` em config e exportar, e só depois desinstalar.
3. **Colisão de IDs:** plugins (`donation_checkout_flow`, `commerce_donation_pane`, `commerce_memorial_pane`), serviços (`commerce_donation_flow.*`) e rotas (`/donate`) não podem existir ativos em duplicidade. Desenvolver e testar com o contrib desativado e a cópia ativa só no corte.
4. **Dependências no Portal:** `DonationSummaryController`, `SupportForm`, `DonationFriendlyRouteSubscriber`, `DomainRouteSubscriber`, `DomainRoutePolicy`, `PortalRequirementsController` e os scripts `validate-payment-domain-policy.php`, `validate-portal-commerce-security.php` citam o contrib. Precisam apontar para o novo namespace no mesmo corte.
5. **Composer:** remover `drupal/commerce_donation_flow` de `composer.json` é uma mudança de dependência. Exige confirmação antes do `composer remove`.
6. **Licença:** o código copiado é GPL-2.0-or-later. Manter o aviso de origem nos arquivos portados.

## Ordem proposta

1. Portar as classes com namespace novo, sem registrar nada ativo (commit próprio).
2. Portar configuração, campos e fluxo, com dependências em `aculta_donation` (commit próprio).
3. Testes Kernel em `aculta_simpletest` (sem Runtime): fluxo de doação, item de doação, resumo, homenagem, agradecimento.
4. Corte no Runtime, com backup do banco antes e confirmação do responsável. Reapontar config, depois desativar e remover o contrib.
5. Atualizar Portal, gates e `composer.json`.

## Recorrência (`commerce_recurring`)

Pendente: o código não está no projeto e a análise do `commerce_recurring` ainda não começou. Próximo passo: ler o modelo de assinatura e de cobrança e decidir o que portar (ver "Decisões do responsável").
