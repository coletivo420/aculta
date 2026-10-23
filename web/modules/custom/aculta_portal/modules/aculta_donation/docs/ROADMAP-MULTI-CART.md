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
