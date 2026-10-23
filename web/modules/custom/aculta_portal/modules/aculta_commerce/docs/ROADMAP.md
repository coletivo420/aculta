# ACULTA Commerce — Roadmap e auditoria 0.1.0-A

Data de início: 2026-10-22. Branch: `docs/aculta-forum-0.1.0-dev.1`. Execução por commits isolados na PR já aberta.

## Decisões normativas

1. Loja, LMS e doações preservam regras e checkouts próprios; **aculta_portal coordena a configuração administrativa (Comum/Loja/LMS/Doações) e preserva o checkout e a consolidação atual do carrinho público**. Ver [ADR-013](../../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md).
2. Mercado Pago é um gateway compartilhado centralizado em `aculta_pagamentos_mercadopago` para loja, LMS e doações. Reutilizar a integração e os contratos atuais; alteração de webhook exige regressão nas três áreas.
3. `aculta_commerce` concentra capacidades próprias da loja e fornece provider de contexto para o orquestrador **no aculta_portal**; Drupal Commerce permanece fonte de verdade. Nenhum armazenamento paralelo de pedidos, carrinhos, pagamentos ou estoque. **Já existe `CartConsolidator` no Portal que consolida pedidos não-recorrentes; preservar essa regra, não projetar outra.**
4. Green Stash será uma **experiência independente do ACULTA Studio**, jamais uma aba do GanjaLab. Usar `StudioExperienceProviderInterface`, com acesso real a serviços e entidades proprietárias. Não pressupor que `account` permita administrar a loja: resolver purpose e grants explicitamente.
5. ACULTA420 detém o visual, baseado exclusivamente em SDC/UI Suite; mobile-first, sem substituir o cabeçalho multidomínio.
6. Estatísticas comerciais via `aculta_statistics`. Ler agregados para indicadores; consultas operacionais de pedidos através de Commerce.
7. Índice transversal apenas para descoberta de produtos e relações públicas autorizadas, jamais para estoque, dados de clientes, carrinhos, transações, segredos ou controle de acesso.

## Inventário confirmado por inspeção estática

| Local | Evidência | Estado / ação |
|---|---|---|
| `aculta_portal/src/Controller/ShopController.php` | Página da loja ligada ao node 372; bundle `produto_loja`; consulta de produtos publicados sem paginação; preço `float` em BRL | Caracterizar e migrar futuramente para acesso paginado e Money do Commerce, sem regressão |
| `aculta_portal/src/Plugin/Commerce/CheckoutPane/` | `AcultaShippingInformation`, `BillingAddressPane`, `PaymentInformationWithoutAddress` | Preservar checkout; auditar endereços compartilhados e fluxos |
| `aculta_portal/src/Commerce/MercadoPago/` | Adaptadores de webhook, credenciais e assinatura | Auditar ownership; não mover sem provas para loja + LMS + doações |
| `aculta_pagamentos_mercadopago` | Plugin `CheckoutPro`, SDK oficial, config via Key | Manter gateway único compartilhado |
| `aculta_donation` | Checkout de doação, campanhas, assinaturas | Não absorver |
| `aculta_lms` | Experiência LMS proprietária | Integrar somente por contrato explícito de compra/entitlement |
| `aculta_statistics/src/EventSubscriber/CommerceOrderPaidSubscriber.php` | Métricas de pedidos, receita, itens, cupons e descontos | Reutilizar; segmentar sem misturar loja/doação/LMS |
| `aculta_studio/src/Experience/` | `StudioExperienceManager`, provider GanjaLab420 e interface de providers | Reutilizar contrato para Green Stash; registro e rota ainda NÃO implementados |
| `aculta_portal/src/Controller/ShopController.php` | `data-portal-ui` com lista textual | Novo catálogo via SDC/UI Suite após auditoria do tema |
| Novo Content Index | Nenhum `aculta_content_index` ou `aculta_index` encontrado na árvore examinada | Contrato de adaptação documentado; descobrir implementação real antes de acoplar |

## Etapas

- **0.1.0-A (iniciada):** manifesto do novo submódulo, ADRs e inventário preliminar. Pendente: varredura completa de código, `config/sync`, entidades e operações comerciais; documento de caracterização com caminhos e consumidores.
- **0.1.0-B (auditoria estática entregue; runtime pendente):** contratos de gateway compartilhado, matriz dos três fluxos, webhooks e idempotência registrados em [PAYMENT-FLOW-CONTRACTS-0.1.0-B.md](PAYMENT-FLOW-CONTRACTS-0.1.0-B.md). **B-01 reclassificado:** seletor de modalidade de checkout é comportamento intencional; sua propriedade transversal migrará gradualmente de Donation para Portal, preservando `donate|cart|both` e evitando consequências cruzadas. Sem mudança de comportamento.
- **0.1.0-C:** auditoria de catálogo, estoque, taxas, shipping, views, cupons, formulários e ACL. Decisão sobre Commerce Stock mediante validação local.
- **0.1.0-D:** contrato Green Stash com `StudioExperienceProviderInterface`, configuração de serviço e rotas apenas após verificação dos propósitos de domínio; ACL por operação; testes.
- **0.2.0:** adaptação da loja como provider do orquestrador de checkout do Portal, sem mudar rotas públicas nem interferir em LMS/doação; migração seletiva de código comercial. `ShopController` sem ID fixo, filtros/paginação, preço sem `float`.
- **0.3.0:** catálogo público, SDC/UI Suite e componentes responsivos.
- **0.4.0:** Green Stash visão geral, meus produtos e CRUD autorizado.
- **0.5.0:** inventário e operações de estoque com consistência e logs.
- **0.6.0:** pedidos, fulfillment, estornos autorizados e conciliação (delegados a gateway/core).
- **0.7.0:** frete e expedição, CEP, rastreamento, integração brasileira conforme escolha validada.
- **0.8.0:** promoções, métricas e relatórios Statistics; eventos com deduplicação.
- **0.9.0:** suíte completa e hardening de domínio, acessibilidade, desempenho e segurança.
- **1.0.0:** release após homologação de fluxos reais e migração reversível.

## Trabalho reservado ao servidor de testes

1. Capturar branch e commit exatos; identificar configuração real e módulos habilitados, tipos de produtos e pedidos, stores, gateways, fluxos checkout, shipping methods, promotions, tax types e views; evitar expor segredos ou PII.
2. Rodar validações estáticas PHP/YAML, `composer validate`, `php scripts/validate-module-theme-boundary.php`; criar testes Kernel, Functional e Browser de isolamento loja/LMS/doações.
3. Homologar cada checkout com sandbox Mercado Pago e webhook assinado, pedidos criados/confirmados/reembolsados, retries/idempotência e conciliação por tipo de pedido.
4. Testar domínio SHOP/ACCOUNT e propósito de rotas, grants e cache por usuário/loja/domínio; login e checkout cross-domain.
5. Verificar catálogo volumoso, variações, preço, promoções, estoque concorrente, frete e expiração de carrinho.
6. Verificar visual 320px+, navegação AJAX com fallback sem JS, teclado, leitor de tela, modo claro/escuro e cabeçalho multidomínio.
7. Confirmar disponibilização do Content Index na branch antes de implementar adaptador; reindexação, permissão, purge e atualizações por eventos.
8. Salvar provas PASS/FAIL, logs sanitizados, resultados HTTP e commit no roadmap. Não afirmar homologação sem execução.

## Regras de colaboração

Permaneça na branch/PR existente; nunca abra outra PR. Não misture arquivos de outras frentes. Não faça reset, force push, migração de dados, ativação em produção ou alterações de checkout antes das provas. Documentação deve acompanhar cada alteração; testes completos no servidor após as fases curtas.

## Histórico de execução

- 2026-10-22 — 0.1.0-A: manifesto, README, índice e inventário de rotas commitados.
- 2026-10-22 — 0.1.0-B: auditoria estática de gateway Mercado Pago, assinatura de webhook, segregação de métricas e `CartCheckoutRouteSubscriber`; matriz de testes produzida. Testes e caracterização de configurações reais permanecem **PENDENTES** no servidor.

## Alinhamento transversal — ADR-013 (2026-10-22)

O gerenciador **não** pertence a `aculta_commerce`: é infraestrutura do `aculta_portal`. O Commerce entrega provider de contexto `store` e lógica de produto, frete, promoção, fulfillment e Green Stash. As quatro abas de carrinho pertencem ao Portal; **Green Stash é gerenciamento administrativo da loja no Studio**, não carrinho. Roadmap de integração: C-0.1 contratos de provider e tipos de pedidos, C-0.2 rotas e autorização por contexto; C-0.3 dados de catálogo, preços e estoque para a aba Loja; C-0.4 componentes SDC/UI Suite no tema; C-0.5 homologação loja + LMS + doação no homelab. Não implementar checkout unificado físico sem ADR adicional.

## RETIFICAÇÃO VIGENTE — Integração administrativa com Portal (2026-10-22)

A arquitetura normativa é o ADR-013 revisado: **a interface de abas Comum | Loja | LMS | Doações é exclusivamente administrativa no aculta_portal**; não é uma nova experiência de compra. O carrinho da barra multidomínio e o seletor atual de checkout permanecem intactos. `aculta_commerce` oferece ao Portal configuração/provider do contexto Loja, mas não recebe nem duplica o orquestrador, e Green Stash permanece administração operacional da loja no Studio.

**Fases Comércio-CART:** C-A inventariar tipos/flows/rotas da Loja; C-B provider de configuração do Portal; C-C formulários/validações da aba administrativa Loja; C-D integração segura com Green Stash e rotas existentes sem alterar carrinho; C-T regressões visuais/funcionais e pagamentos no homelab. Não executar trabalho de storefront nesta frente. Esta seção substitui referências antigas a abas de carrinho público.

## Verificação pós-auditoria C-0.1-C1 (2026-10-22)

[Auditoria do código vigente](CURRENT-CODE-AUDIT-0.1.0-C.md): `CartSite`, `CartUtility`, `CartConsolidator` e `CheckoutSlugMap` já implementam carrinho público único e consolidação de pedidos `curso/default/doacao`, exceto assinaturas. Foram introduzidos testes de caracterização **sem alteração de comportamento** em `tests/src/Unit/ExistingPortalCartContractTest.php`. Execução do PHPUnit e validação real ficam pendentes do ambiente local/homelab. Fases seguintes: C-0.1-C2 configurar fixtures e testar consolidator transacional, C-0.1-C3 provider administrativo da Loja somente depois de P-CART B, C-0.1-D Studio Green Stash. Qualquer sugestão antiga de quatro abas públicas ou proibição de consolidação de pedidos está supersedida pelo comportamento atual e ADR-013.

## C-0.1-C2 — Avanço de código e dívidas (2026-10-22)

- Criado `tests/src/Unit/PortalCartConsolidatorBoundaryTest.php` com duas provas não mutantes: carrinho vazio retorna NULL e assinatura isolada não é consolidada nem excluída.
- Mantidos `CartConsolidator`, `CartUtility`, `CartSite`, rota e barra multidomínio **sem alterações**; nenhuma lógica de checkout mudada.
- Registro central de dívida atualizado em `docs/operations/DEBT-REGISTER.md` sob IDs **DT-COM-01 a DT-COM-07**, com responsáveis e critérios de saída. Execução de PHPUnit, fixtures e testes de pagamento **não foi realizada neste ambiente**.
- A fase C-0.1-C2 permanece **parcial** até DT-COM-01/02/03/04; C-0.1-C3 depende de P-CART B (DT-COM-06). Não declarar gates PASS.

## 0.1.0-D — Green Stash (execução parcial, 2026-10-22)

Foi implementada a fundação de entrada administrativa `GreenStashAccessPolicy`, com permissão `access aculta green stash` restrita, serviço e teste unitário, sem atribuir permissão a roles e sem registrar rota/provider Studio. Detalhes e sequência em [GREEN-STASH-0.1.0-D.md](GREEN-STASH-0.1.0-D.md). Próximo passo D-A/D-B: resolver o contrato de domínio e registrar provider + rota próprios com testes; D-C/D-D ficam condicionados a isolamento por store/order type e SDC/UI Suite. **Sem impacto no carrinho multidomínio e checkout público.**

## Green Stash D-A/D-B — Código entregue (2026-10-22)

- Criados provider Studio `green_stash`, controller, rota `/green-stash` sob purpose `account`, registro de serviço condicionado ao módulo Commerce, dependência Studio e teste unitário de contrato.
- Rota **não é declarada pronta para uso operacional**: o provider renderiza apenas mensagem de preparação. A permissão de entrada não autoriza visualizar/alterar entidades de Commerce e nenhum papel recebeu grants.
- **Próxima fase D-C:** caracterizar provider/rota no homelab e adicionar dashboard exclusivamente administrativo com isolamento de loja e permissions Commerce. **D-D:** apresentação SDC/UI Suite e gates de tema. Dívidas DT-COM-11 a DT-COM-13 no registro central.
- Preservados carrinho multidomínio, CheckoutSlugMap, CartConsolidator, flows e gateway Mercado Pago.
