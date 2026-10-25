# ACULTA Donation — Roadmap de migração para o multi-carrinho do Portal

**Decisão:** [ADR-013](../../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md). **Status:** planejado; sem alteração funcional.

## Preservação obrigatória

`DonationSettingsForm` oferece `donate`, `cart`, `both`: são **modos intencionais**. O `CartCheckoutRouteSubscriber` associado decide disponibilidade global no modelo original; não removê-lo sem substituição e teste de equivalência no novo coordenador `aculta_portal`. O controller de doação, assinatura recorrente, campanhas, panes, integração Mercado Pago compartilhada, proibição de estorno e IDs históricos continuam pertencendo ao Donation. Não migrar entidades, históricos nem gateway.

## Fases pequenas

| Fase | Entrega | Prova obrigatória |
|---|---|---|
| D-CART 0.1-A | Inventariar configuração `donation_route`, seus consumidores, tipos `doacao` e `assinatura`, ordem dos carrinhos e requisitos das rotas | Fixtures/config e teste legado nas três modalidades |
| D-CART 0.1-B | Formalizar provider de doações para registry neutro do Portal, sem alterar serviços antigos | Unitários e Kernel; nenhuma dependência circular |
| D-CART 0.1-C | Resolver carrinho elegível e modo dedicado/comum por pedido, sem `reset(getCartIds())` | Simultaneidade loja + LMS + doação, usuário anônimo/autenticado |
| D-CART 0.2-A | Migrar seleção e política transversal ao Portal, dual-read temporário de configurações antigas, preservando rotas | Matriz de rotas pré/pós, rollback, cache e ACL |
| D-CART 0.2-B | Alimentar aba Doações e resumo da aba Comum, sem mesclar assinatura com carrinho avulso | Browser mobile, teclado/ARIA, fallback sem JS |
| D-CART T | Validar fluxos pagamento, webhook e acesso em ambiente isolado | Evidência PASS/FAIL com Mercado Pago sandbox |

## Gates

A escolha de checkout é por carrinho/pedido e não por sessão PHP global; no máximo, guardar preferência temporária com chave por contexto, revalidada a cada requisição. A aba Comum é agregadora, não um pedido adicional. Nunca alterar o checkout da loja ou LMS em função da configuração de doações. Rotas de Commerce mantêm os requisitos nativos de acesso. Transferência de propriedade somente após contratos e testes de compatibilidade da configuração exportada.

## Trabalho reservado ao servidor

`drush cget aculta_donation.settings`, listar `commerce_order_type`, `commerce_order_item_type`, `commerce_checkout_flow`, `commerce_payment_gateway` e módulos habilitados. Sanitizar logs, nunca expor chaves de pagamento. Testar `donate`, `cart`, `both`, doação avulsa e recorrente, carrinhos simultâneos, redirecionamento multidomínio, política de estorno e acesso por entidade. Não executar alterações de configuração em produção.

## RETIFICAÇÃO VIGENTE — Centralização **administrativa** (2026-10-22)

O carrinho atual da barra multidomínio permanece inalterado e **não haverá quatro abas públicas**. Comum | Loja | LMS | Doações são abas de configuração interna no Portal. Manter `donate|cart|both`, redirecionamentos, order types, checkout dedicado, recorrência e policy de estorno como comportamentos públicos existentes; a migração é de ownership do seletor transversal, sem mudança de UX ou sessão de usuário.

**Fases substitutivas:** D-A caracterizar `DonationSettingsForm` e `CartCheckoutRouteSubscriber` em `donate|cart|both`; D-B implementar provider administrativo mantendo código anterior ativo; D-C integrar formulário da aba administrativa Doações do Portal; D-D transferir a lógica compartilhada após testes de equivalência, dual-read configurável e rollback, desregistrando o antigo subscriber somente no corte; D-T testar checkout e cabeçalho multidomínio intactos. As ideias anteriores de construir novos resolvedores de carrinho público ou tabs do comprador **não integram este escopo**.

## Linha de base P-CART A / D-A — caracterização (2026-10-22)

Testes Unit que fixam o comportamento atual antes de qualquer migração (nenhum código de produção foi alterado):

- `aculta_donation/tests/src/Unit/CartCheckoutRoutesCharacterizationTest.php`: `CartCheckoutRouteSubscriber` e `CartFriendlyRouteSubscriber` (Portal) alteram `commerce_cart.page`; o resultado não depende da ordem. `donate` nega `commerce_cart.page`, `commerce_checkout.checkout` e `commerce_checkout.form` (`_access` FALSE, sem `_permission`); `cart` e `both` produzem exatamente a mesma coleção e deixam o Commerce intacto; configuração ausente ou inválida se comporta como `donate`; o caminho do carrinho é sempre `/carrinho`.
- `aculta_portal/tests/src/Unit/Cart/CartSiteTest.php`: tipo de pedido → separador do carrinho único (`default`→Loja, `curso`→Cursos, `doacao`→Apoio, `assinatura`→Assinatura; desconhecido→Loja), ordem e rótulos.
- `aculta_portal/tests/src/Unit/Cart/CartFriendlyRouteSubscriberTest.php`: `/cart` → `/carrinho` sem trocar o nome da rota nem tocar o checkout.

Consumidores de `donation_route` além do subscriber, que o futuro dual-read também precisa cobrir: `DonateBlock`, `QuickDonationBlock`, `DonationController::checkAccess()` e `DonationSettingsForm`. O valor instalado por padrão é `both`; o padrão do formulário é `donate`.

Fora do alcance de Unit (ficam para Kernel/Browser/homelab): `CartUtility`/`CartConsolidator` com carrinhos reais, DOM do cabeçalho, contador, redirecionamento de `CartCheckoutController` e fluxo de pagamento. Execução: `vendor/bin/phpunit -c web/core <caminho>` (27 testes, 88 asserções, OK).

## Corte do seletor — P-CART D / D-D (2026-10-22)

`CartCheckoutRouteSubscriber` foi removido do Donation e substituído por `aculta_portal/src/EventSubscriber/CommerceCheckoutRouteSubscriber`, que consulta `CheckoutPolicyManager::commonCheckoutRoutesAvailable()`. O `DonationCheckoutProvider` declara `gatesCommonCheckout` (efeito que já existia: o modo do Donation liga e desliga as rotas globais do Commerce, inclusive para Loja e LMS; ver B-01 em `aculta_commerce/docs/PAYMENT-FLOW-CONTRACTS-0.1.0-B.md`). Só há um subscriber ativo por vez.

- **Equivalência:** as asserções da linha de base acima passam inalteradas contra a nova cadeia; `donation_route` ausente ou inválido continua equivalendo a `donate`; sem Donation habilitado, as rotas ficam como o Commerce as define.
- **Configuração:** `aculta_donation.settings` e `DonationSettingsForm` não foram alterados; nada a migrar.
- **Rollback:** reverter o commit do corte (`git revert`) restaura o subscriber e o serviço antigos; não há configuração a desfazer.
- **Pendente:** comparação das rotas compiladas e do cabeçalho no servidor de testes (Kernel/Browser/homelab, P-CART T).

## Homologação parcial P-CART T — runtime de testes `toca` (2026-10-22)

Teste reversível por configuração e HTTP local (`curl --resolve` para 127.0.0.1), com backup prévio de `aculta_donation.settings` e restauração ao final (`donation_route: both`, sem `aculta_portal.checkout_settings`). HEAD `18547fee`. Sem pedidos reais, sem credenciais e sem Mercado Pago.

| Cenário | Rotas do Commerce | `/carrinho` (principal) | `/apoiar` (apoio) |
|---|---|---|---|
| `donate` | `_access: FALSE` nas três | 403 | 302 (dedicado ativo) |
| `cart` | abertas | 200 | 403 (dedicado fechado) |
| `both` | abertas | 200 | 302 |
| `both` + `common_checkout_enabled: false` | `_access: FALSE` nas três | 403 | 302 (dedicado não depende da chave) |

`loja.../carrinho` redireciona (302) ao domínio principal nos quatro cenários. **Limite:** a comparação é com o comportamento caracterizado pelos testes (asserções originais), não uma execução antes/depois no mesmo runtime. Continuam pendentes: DOM e contador do carrinho do cabeçalho, checkouts de Loja, LMS e Doações em navegador, anônimo e autenticado, webhooks, assinaturas, mobile e claro/escuro, sem JavaScript e Olivero.
