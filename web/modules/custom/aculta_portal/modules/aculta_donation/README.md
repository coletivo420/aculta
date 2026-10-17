# ACULTA Doações (`aculta_donation`)

Submódulo do `aculta_portal` (versão 0.1.0-dev.3). Reúne a doação do site: campanhas com prazo, fluxo de pedido e checkout
(port próprio do `commerce_donation_flow`, que saiu do Composer), blocos, painéis e configuração.

## O que entrega

- **Campanha de doação** (`aculta_donation_campaign`, configuração): nome, período de vigência (inclusivo, no fuso do
  site), valores sugeridos em reais, valor personalizado e status ativo. Lista em
  `/painel-administrativo/configuracoes/aculta/doacoes/campanhas`.
- **Seleção da campanha vigente** (`DonationCampaignSelector`): a campanha ativa cuja janela contém a data de hoje. Sem
  campanha, a página de apoio usa os valores padrão (`aculta_portal.support`).
- **Fluxo de doação** (`donation_checkout_flow`, `donation_multistep_flow`): pedido, painéis de doação, dedicatória
  (homenagem), resumo, agradecimento e o widget de valor. Entrada: `/apoiar` e `/apoiar/{valor}/agora`.
- **Blocos** (`aculta_donation_donate`, `aculta_donation_quick`, `aculta_donation_back`): dados neutros; a apresentação
  é do tema ACULTA420 (SDC `donation-quick`, templates do tema).
- **Configuração do fluxo** (`/painel-administrativo/configuracoes/aculta/doacoes/fluxo`): rota (`/apoiar`, carrinho
  padrão ou ambas), tipo de item, tipo de pedido e fluxo de checkout. Grava em `commerce_donation_flow.settings`
  (nome mantido para não migrar dados).
- **Abas** da seção Doações: Campanhas, Valores padrão (`aculta_portal.support_settings`) e Fluxo de doação.

## Regras de negócio

- Doações e assinaturas não podem ser estornadas.
- Assinatura recorrente: um tipo único de pedido, agrupando as cobranças (decisão do responsável, 2026-10-17). Ainda não
  implementada; ver `docs/PORT-COMMERCE-DONATION-FLOW.md`, seção "Recorrência".

## Fronteiras

- Pedido e checkout: Drupal Commerce (`commerce_cart`, `commerce_checkout`, `commerce_order`, `commerce_payment`,
  `commerce_price`, `commerce_store`), sem dependência do contrib de doações.
- Apresentação: o módulo entrega dados e tokens `data-portal-ui` (`donation-*`); o tema ACULTA420 traduz em classes
  (`PortalUiHooks`) e desenha os componentes SDC (`donation-quick`, `donation-pane`, `donation-dedication`,
  `donation-notification`, `donation-summary-container`).
- Permissões: `administer aculta donation campaigns` (campanhas); `make donation` e `view any donation complete`
  (herdadas, mesmo nome, concedidas por papel).

## Origem e licença

O fluxo, os painéis e os blocos são um port de `commerce_donation_flow` 1.2.0 (GPL-2.0-or-later, Shawn Duncan e
colaboradores). Avisos de licença em `LICENSE.txt` e em `docs/ORIGEM-COMMERCE-DONATION-FLOW-README.txt`.

## Testes

- Unitários: `tests/src/Unit/DonationCampaignSelectorTest.php` e `tests/src/Unit/CartCheckoutSubscriberTest.php`.
  Execução: `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_donation/tests/src/Unit`.
- Runtime de testes: habilitado e verificado por HTTP e por API (ver CHANGELOG). Testes de Kernel do fluxo: pendentes.
- Navegador: pendente (conferência visual do fluxo e dos blocos).
