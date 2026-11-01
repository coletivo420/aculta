# Commerce 0.1.0-C — Auditoria de implementação vigente

Data: 2026-10-22. Branch `docs/aculta-forum-0.1.0-dev.1`, HEAD inicial `c46fff67a416f533d6b24d8253ca91155fc97555`. Auditoria estática: **não há validação de configuração ou pedidos reais no homelab**.

## Correção arquitetural baseada no código

O Portal **já implementa um carrinho único público com separadores**, e não apenas uma proposta:

- `src/Cart/CartSite.php`: mapeia tipos `default`→`loja`, `curso`→`cursos`, `doacao`→`apoio`, `assinatura`→`assinatura`; fallback para Loja.
- `src/Cart/CartUtility.php`: usa `CartProviderInterface::getCarts()`, conta itens, agrupa por separador, renderiza URL de carrinho global e `aculta_portal.cart_checkout`, com saída específica da assinatura em `commerce_checkout.form` e cache contexts `cart`, `user`, `session`.
- `src/Cart/CartConsolidator.php`: reúne pedidos de `curso`, `default`, `doacao` em **um pedido Commerce**, com prioridade curso > loja > doação; **assinatura excluída**. Move itens, exclui carrinhos de origem e aponta destino na sessão. Esse comportamento **é existente e intencional** e contradiz a sugestão anterior de jamais unificar pedidos. **Não alterá-lo sem decisão explícita e provas de equivalência**.
- `src/Checkout/CheckoutSlugMap.php`: traduz rotas Commerce `/checkout` para `/finalizar-compra` sem alterar a URL interna da operação.
- `modules/aculta_donation/src/EventSubscriber/CartCheckoutRouteSubscriber.php`: modos `donate|cart|both` modulam disponibilidade de rotas globais durante route rebuild; a finalidade é intencional. O ownership administrativo deverá migrar ao Portal com compatibilidade.
- `modules/aculta_commerce/`: nesta revisão o submódulo contém manifesto e documentação, **sem classes comerciais operacionais**. Não apresentar o Green Stash como já ativo.

## Contrato de não-regressão

1. **Não modificar** o carrinho público da barra multidomínio (posição, contagens, URLs, interação, visual, checkout).
2. **Não modificar** a consolidação atual e a prioridade entre `curso/default/doacao`, nem a exceção `assinatura`, enquanto não houver baseline e teste no homelab.
3. Abas **Comum | Loja | LMS | Doações** são **somente administrativas** no Portal, jamais novas abas do comprador.
4. `aculta_commerce` é submódulo da **Loja**; Portal coordena seleção de checkout; LMS/Donation preservam regras; Mercado Pago permanece compartilhado.
5. O Content Index não deve indexar pedidos, cart sessions ou dados privados.
6. Não mudar a regra de fallback de `CartSite` sem inventário de tipos existentes e tests de migração.

## Riscos reais para testes, não defeitos comprovados

- `CartConsolidator` transfere itens entre tipos de pedido e exclui carrinhos de origem: verificar suporte dos `commerce_order_item_type`, store, moeda, ajustes, shipment, cupons, estado e recuperabilidade em falhas. Não corrigir às cegas.
- `CartUtility` soma preços via `float` e registra a última moeda encontrada: caracterizar valores e múltiplas moedas, porém sem mudar o visual antes da aprovação.
- `CartSite` classifica desconhecidos como loja; separar classificação operacional administrativa do contrato visual histórico, sem mudar o rótulo apresentado.
- `CartCheckoutRouteSubscriber` altera requisitos globais de rotas quando `donation_route=donate`; caracterizar coexistência com checkout da loja/LMS em configurações reais.
- `ShopController` usa ID fixo de node (372), busca sem paginação e formatação monetária com float; tratar em fases específicas, preservando `/loja`.

## Próximas fases de Commerce

- **C-0.1-C1 (esta entrega):** auditoria e fixture/validador estático de fronteiras, inventário documentado.
- **C-0.1-C2:** caracterização de dados/config no homelab: produto, variation, order type, order item type, cart, checkout flow, shipping, promotion, imposto, domain purpose; testes para consolidação em casos de transações concorrentes e rollback.
- **C-0.1-C3:** adaptador administrativo da Loja ao registry do Portal, **após** contrato central P-CART B; não registrar seletores paralelos.
- **C-0.1-D:** provider e autorização do Green Stash no Studio, **após** auditar serviços Studio e grants; sem rotas públicas adicionais.
- **C-0.2:** evoluir catálogo público e a listagem, eliminando acoplamento a node fixo e float com testes, mas mantendo mesma URL e carrinho existente.
- **C-0.3+**: catálogo visual SDC/UI Suite, estoque/frete/pedidos e relatórios via Statistics, com gates por fase.

## Checks necessários no servidor

Executar `drush pm:list` e inspecionar configurações ativas, `drush cget aculta_donation.settings`, `drush cr`, testes PHP/Kernel/Functional e comparação HTTP/DOM do cabeçalho antes/depois. Testar adicionar produto, curso e doação; finalizar carrinho consolidado e assinatura separada; reproduzir `donate|cart|both`; Mercado Pago sandbox, webhooks e acesso por entidade. Registrar PASS/FAIL e backups. **Nenhum teste de runtime executado nesta auditoria.**
