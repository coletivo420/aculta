# ADR — Carrinhos e checkout multi-contexto no ACULTA Portal

**Status:** aprovado para planejamento; implementação e migração pendentes. **Data:** 2026-10-22.

## Decisão

O **aculta_portal** é o dono da coordenação transversal de carrinhos, seleção de checkout, resolução de contexto e navegação de compras. **Não** delegar essa função ao `aculta_commerce` nem ao `aculta_donation`. O Drupal Commerce permanece fonte de verdade de pedidos, itens, carrinhos, preços, flows, pagamentos e estoque.

- `aculta_commerce`: catálogo, operações da loja e Green Stash (Studio); fornece adaptador de contexto `store`.
- `aculta_lms`: compra de cursos, autorização e concessão de matrícula; fornece adaptador `lms`.
- `aculta_donation`: doação dedicada, campanha e recorrência; fornece adaptador `donation` e preserva `donate|cart|both`.
- `aculta_pagamentos_mercadopago`: gateway central usado por loja, LMS e doações; sem cópia de código ou separação artificial de gateway.
- `aculta_statistics`: métricas derivadas e segmentadas; evitar duplicação de pedidos/receita.
- `aculta_studio`: Green Stash é interface administrativa própria da loja e não o carrinho do comprador.

## Interface funcional: quatro abas internas

| Aba | Função | Ação de checkout |
|---|---|---|
| **Comum** | Visão consolidada de todos os carrinhos autorizados e elegíveis, separados por origem, loja, moeda e pedido | Encaminha cada grupo ao checkout adequado. **Não** cria pedido único nem soma impostos e frete incompatíveis |
| **Loja** | Carrinho de produtos, variações e frete | Checkout comercial configurado para o pedido |
| **LMS** | Carrinho de cursos e acesso educativo | Checkout LMS configurado para o pedido, matrícula após confirmação válida |
| **Doações** | Carrinho de doação avulsa e campanhas, inclusive entrada dedicada | `donate`, `cart` ou `both`; assinatura permanece fluxo segregado |

A aba Comum é uma **interface agregadora**, não um novo tipo de pedido por padrão. Um checkout efetivamente unificado só poderá ser desenvolvido com ADR posterior que detalhe compatibilidade de order item types, moedas, lojas, regras fiscais, shipping, campanhas, recorrência, estados, reembolsos e fulfillment. Pedidos incompatíveis nunca devem ser fundidos automaticamente.

A navegação será mobile-first: abas internas ou seletor acessível em telas estreitas, indicação de contagem, estado vazio por contexto, filtros de elegibilidade, atualização AJAX progressiva com fallback HTTP, foco/teclado/ARIA, clareza de subtotais por pedido e moeda e decisões explícitas antes do pagamento. O carrinho permanece na área `main`; cabeçalho multidomínio mantido globalmente. Tema `aculta420` implementa componentes apenas em SDC/UI Suite, light/dark.

## Contratos técnicos propostos (não existentes ainda)

- `aculta_portal.checkout_context_registry`: descoberta de providers por tag/interface; IDs estáveis `store`, `lms`, `donation`, extensíveis; usar dependency injection e evitar dependências circulares.
- `CheckoutContextProviderInterface`: `id()`, `label()`, `supports(OrderInterface)`, `access(OrderInterface, AccountInterface)`, `allowedCheckoutModes(OrderInterface)`, `resolveCheckoutRoute(OrderInterface)`; implementação adaptada às APIs instaladas após inventário.
- `CartContextResolver`: consulta `CartProviderInterface`, `CartSessionInterface` e Commerce; seleciona carrinhos por tipo/itens/loja/conta, não `reset(getCartIds())`. Verifica ownership e cache contexts/tags.
- `CheckoutSelectionManager`: decide modalidade `dedicated`, `common`, `both` por provider e entidade; conserva escolha por **contexto de carrinho/pedido**, não por sessão PHP global compartilhada. Sessão só guarda seleção temporária com namespace/contexto, jamais autoriza compras.
- `CartDashboardController`: fornece render arrays e APIs de interação com políticas explícitas; não cria storage próprio. Rotas públicas e checkout existentes preservados inicialmente.
- `CartCheckoutRouteSubscriber` hoje em donation: funcionalidade legítima de habilitar ou desabilitar entradas; port/migração progressiva para Portal, **sem remover comportamentos antes de caracterizar o sistema real**.

## Segurança e limites

1. Nunca selecionar pedido só pela primeira ID da sessão; validar store, dono, estado, tipo, elegibilidade, conteúdo e acesso.
2. Preservar requisitos nativos de acesso do Commerce, CSRF, acesso por entidade e proteção de cache. Rota compilada não pode depender do pedido atual; avaliação contextual ocorre por requisição.
3. Não expor pedidos de LMS/doação em Green Stash; `main` e `shop` são purposes de domínio, não tipos de negócio.
4. O webhook Mercado Pago permanece único; reconciliação idempotente e políticas de estorno continuam com módulos proprietários.
5. Não indexar carrinhos, endereços ou pagamentos no ACULTA Content Index. Apenas catálogo público com adaptadores autorizados.
6. Migrar `aculta_donation.settings` por leitura compatível antes de corte; preservar históricos, IDs e configuração exportada.

## Plano versionado

- **P-0.1-A:** inventariar rotas, serviços, `config/sync`, order/item types, checkout flows e consumidores dos três contextos. Reproduzir `donate|cart|both`.
- **P-0.1-B:** interfaces e registry neutros no Portal, sem mudança de rotas; testes unitários de seleção, conflitos, sem provider e cache.
- **P-0.1-C:** adaptadores Donation/Commerce/LMS, guardas de conflito e modo comum por contexto; não duplicar checkout.
- **P-0.1-D:** resolução de carrinhos simultâneos e seleção por entidade, contratos de acesso; testar convidado e autenticado.
- **P-0.2-A:** migrar propriedade do subscriber e da configuração global para Portal com deprecation transitória, teste de equivalência de rotas e rollback; telas de doação continuam no Donation.
- **P-0.2-B:** aba **Comum** mais Loja/LMS/Doações, render arrays e componentes SDC/UI Suite; URLs acessíveis, fallback sem JS e AJAX progressivo.
- **P-0.2-C:** habilitar seleção de checkout por contexto e persistência da preferência dentro dos limites da sessão, com validação no pedido; jamais na sessão inteira.
- **P-0.3:** pagamentos e fluxos ponta a ponta no homelab, observabilidade via Statistics, hardening, performance e testes UI.
- **P-1.0:** estabilização após aceite e documentação de manutenção.

## Gates no servidor de testes

Validar checkout da loja, compra LMS e doação avulsa/recorrente, carrinhos simultâneos e alternância das quatro abas; reentrada e logout; domínio multi-host; alteração de `donation_route`; checkout dedicado e comum; Mercado Pago sandbox, webhook assinado/repetido, estados e estorno autorizado; renderização mobile/light/dark e fallback sem JavaScript. Registrar PASS/FAIL sanitizados e procedimentos de rollback. **Sem runtime verificado, nenhuma migração de rotas está homologada.**
