# ACULTA Doações (`aculta_donation`)

Submódulo do `aculta_portal` (versão 0.1.0-dev.18). Reúne a doação do site: campanhas com prazo, fluxo de pedido e checkout
(port próprio do `commerce_donation_flow`, que saiu do Composer), blocos, painéis e configuração.

## O que entrega

- **Campanha de doação** (`aculta_donation_campaign`, configuração): nome, período de vigência (inclusivo, no fuso do
  site), valores sugeridos em reais, valor personalizado e status ativo. Lista em
  `/painel-administrativo/aculta/doacoes/campanhas`.
- **Seleção da campanha vigente** (`DonationCampaignSelector`): a campanha ativa cuja janela contém a data de hoje. Sem
  campanha, a página de apoio usa os valores padrão (`aculta_portal.support`).
- **Fluxo de doação** (`fluxo_doacao`, `donation_multistep_flow`): pedido, painéis de doação, dedicatória
  (homenagem), resumo, agradecimento e o widget de valor. Entrada: `/apoiar` e `/apoiar/{valor}/agora`.
- **Blocos** (`aculta_donation_donate`, `aculta_donation_quick`, `aculta_donation_back`): dados neutros; a apresentação
  é do tema ACULTA420 (SDC `donation-quick`, templates do tema).
- **Configuração do fluxo** (`/painel-administrativo/aculta/doacoes/fluxo`): rota (`/apoiar`, carrinho
  padrão ou ambas), tipo de item, tipo de pedido e fluxo de checkout. Grava em
  `aculta_donation.settings`; a atualização de 0.1.0-dev.18 migra os valores do nome usado pelo contrib removido.

O nome da configuração segue o provider que atualmente fornece o comportamento (`aculta_donation`). IDs históricos de
plugins e campos continuam estáveis quando usados por configurações exportadas; o setting migra para o namespace próprio
porque a dependência de configuração Drupal é validada pelo módulo provider durante importações.
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

- Unitários: `tests/src/Unit/DonationCampaignSelectorTest.php`, `tests/src/Unit/CartCheckoutRoutesCharacterizationTest.php` (equivalência de `donate|cart|both` nas rotas) e `tests/src/Unit/DonationCheckoutProviderTest.php`.
  Execução: `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_donation/tests/src/Unit`.
- Runtime de testes: habilitado e verificado por HTTP e por API (ver CHANGELOG). Testes de Kernel do fluxo: pendentes.
- Navegador: pendente (conferência visual do fluxo e dos blocos).

## Roadmap de integração: Portal multi-carrinho / checkout (ADR-013)

A seleção `donate | cart | both` é **funcionalidade intencional**, preservada na migração. A propriedade das políticas transversais de carrinho/checkout, era parcialmente do `CartCheckoutRouteSubscriber` (removido em 2026-10-22; a decisão de rotas agora é do `CommerceCheckoutRouteSubscriber` do Portal, alimentado por `DonationCheckoutProvider`) e segue em `DonationSettingsForm` (inalterado), e migra para `aculta_portal`, que fornecerá quatro abas administrativas **Comum | Loja | LMS | Doações**. O módulo Donation continua responsável por campanhas, pedidos e checkout dedicado, recorrência e proibição de estorno. Não mover gateway Mercado Pago. A aba Comum não agrega pedidos incompatíveis em pedido único.

- **D-CART.1:** inventariar `donation_route`, settings exportados e dependências dos consumidores; teste de caracterização das modalidades.
- **D-CART.2:** implementar provider `donation` segundo interfaces do Portal, com acesso por pedido e seleção de carrinho compatível.
- **D-CART.3:** mudar gradualmente ownership do subscriber/configuração transversal, preservando compatibilidade de configurações existentes e rotas de doação; remover registro legado somente após equivalência comprovada.
- **D-CART.4:** integrar aba Doações e Comum (avulsa e dedicada; assinaturas continuam segregadas); verificar domínio, sessão, ACL e acesso anônimo.
- **D-CART.T:** testes Kernel/Functional/Browser, cenário loja+LMS+doação simultâneos, Mercado Pago sandbox, rollback e logs sanitizados no homelab.

Ver [ADR-013](../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md). Planejamento somente: os controllers/subscribers não foram modificados.

## RETIFICAÇÃO ADR-013 — Migração de ownership sem mudar o carrinho

A seleção `donate | cart | both` permanece funcional e inalterada publicamente. O Portal passará a controlar a **configuração administrativa transversal**; Donation continuará dono de campanhas, doação, recorrência e checkout dedicado. As abas **Comum | Loja | LMS | Doações** serão apenas administrativas. **Não modificar o carrinho na barra multidomínio nem a escolha atual de checkout**; não criar tabs do comprador. Ver [roadmap revisado](docs/ROADMAP-MULTI-CART.md) e [ADR-013](../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md).
