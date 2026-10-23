# ADR-013 — Coordenação administrativa de carrinhos e checkouts no ACULTA Portal

**Status:** aprovado como planejamento; nenhuma alteração funcional habilitada. **Revisão:** 2026-10-22. Esta versão substitui integralmente a proposta anterior de interface pública multi-carrinho.

## Decisão obrigatória

O `aculta_portal` será proprietário da **orquestração técnica e administrativa** de carrinhos e da seleção de modalidades de checkout de Loja, LMS e Doações. **Não criar novo carrinho público, não alterar o carrinho existente na barra multidomínio nem modificar a experiência ou os critérios atuais de seleção do checkout.**

A mudança consiste em reunir contratos, seleção e configurações hoje dispersas em uma infraestrutura central de administração, com transferência progressiva de ownership — **não uma migração de UX do consumidor**.

## Contratos de preservação

1. O **carrinho atual da barra multidomínio** permanece em sua posição, com o mesmo ícone, contador, comportamento, visibilidade, links, estilo e interações. Auditar código e criar testes de caracterização antes de tocar nesses contratos. Proibido mover o carrinho para `main`, criar página pública de quatro abas ou substituir menus públicos.
2. O atual seletor de checkout deve funcionar exatamente como antes, inclusive as três modalidades do Donation `donate`, `cart` e `both`, suas rotas, requisitos, redirecionamentos e configurabilidade.
3. **Drupal Commerce** mantém carrinhos, pedidos, order items, flows, preços, shipping e transações; **Mercado Pago central** em `aculta_pagamentos_mercadopago` atende loja, LMS e doações, preservados seus webhooks e regras de estorno.
4. `aculta_commerce` administra Loja e Green Stash; `aculta_lms` administra compras e matrículas educacionais; `aculta_donation` mantém campanhas, doações, checkout dedicado, assinaturas e regras específicas. Providers de configuração podem ser integrados ao Portal, sem perda dos fluxos originais.
5. `aculta_portal` não cria entidades duplicadas nem faz suposições de classificação usando somente o domínio: tipo de pedido, item, store e regras proprietárias continuam determinantes.
6. Não alterar configuração armazenada, IDs ou pedidos históricos sem compatibilidade testada e rollback.

## Interface interna administrativa: quatro abas

**Comum | Loja | LMS | Doações** são abas **administrativas exclusivas**, dentro do painel interno do Portal. Não são mostradas ao consumidor nem substituem o carrinho global.

- **Comum:** visão e configuração transversal de disponibilidade, roteamento, opções de checkout compartilhadas, políticas globais, prioridades/resolução de conflito, diagnósticos e referências de integração. Não cria pedido/carrinho agregado.
- **Loja:** mapeamentos de tipos de pedido/item, opções do checkout comercial, estados operacionais e configuração do provider `aculta_commerce`; gestão detalhada da loja continua no Green Stash.
- **LMS:** associações de tipos e flows educacionais, regras de elegibilidade/entitlement e referências ao provider do LMS; não modifica matrícula nem curso diretamente.
- **Doações:** painel das opções existentes `donate|cart|both`, mapeamentos, checkout dedicado/comum, assinaturas e regras configuráveis mantendo `aculta_donation` dono do negócio.

A interface deve ter rotas internas com **permissões administrativas específicas**, access checks no servidor, Form API e Config API, validação e mensagens de erro; impedir exposição de segredos. Layout em abas SDC/UI Suite no tema `aculta420`, mobile-first, light/dark, teclado/ARIA; AJAX apenas progressivo com fallback HTTP. **Não modificar o cabeçalho multidomínio.** O painel administrativo pode ser renderizado na região `main` sem mover o carrinho público.

## Contratos técnicos propostos, ainda não implementados

- Serviço central `aculta_portal.checkout_policy_manager` ou nome equivalente, como camada sobre Commerce e os providers.
- Registry de providers por contrato explícito e tags de serviços; providers de Loja, LMS e Doações indicam políticas e valores de configuração **sem** dependência circular entre Portal e submódulos.
- Separar **configuração administrativa** de **avaliação de acesso em runtime**. Não armazenar preferências novas por usuário/sessão e não alterar a seleção atual sem testes de equivalência.
- Portar/adaptar a coordenação transversal de `aculta_donation/src/EventSubscriber/CartCheckoutRouteSubscriber.php` e `DonationSettingsForm.php` gradualmente, conservando todas as modalidades atuais. Preservar os métodos próprios de checkout do Donation e o gateway.
- Uma rota global compilada não pode ser tornada específica de pedido via parâmetro de sessão. Caracterizar primeiro os efeitos atuais e somente então migrar seu ownership, sem mudanças observáveis.
- Administrar mapeamentos via validação dos tipos de pedido/checkout flow já existentes; evitar reatribuir silenciosamente tipos de pedido de outras experiências.

## Fases de entrega

- **P-CART A — Caracterização:** localizar componentes e testes do carrinho multidomínio (ícone, contador, comportamento, URLs), roteamento, seletores `donate|cart|both`, configs e order types. Capturar linha de base.
- **P-CART B — Contratos:** registry e interfaces de configuração no Portal, sem mudar o público; testes unitários.
- **P-CART C — Integração:** providers de Loja/LMS/Doações e leituras administrativas compatíveis; testes Kernel, sem corte de configuração.
- **P-CART D — Transferência do seletor:** portar coordenação do subscriber para Portal por passos, compatibilidade de config antiga e testes de equivalência/rollback. Não duplicar subscribers ativos.
- **P-CART E — Administração:** quatro abas **internas** Comum/Loja/LMS/Doações; UI Suite/SDC, Form API, ACL e fallback sem JS; NÃO criar carrinho público.
- **P-CART F — Hardening:** configuração, segurança, conflito de ownership, observabilidade com Statistics quando relevante; não indexar pedidos/carrinhos no Content Index.
- **P-CART T — Homologação:** homelab, testes unitários/Kernel/Functional/Browser, três fluxos no sandbox, gestão das configurações e regressão visual e de comportamento do carrinho do cabeçalho; registrar PASS/FAIL e rollback.

## Gates rígidos no servidor de testes

Antes e depois da migração, comparar as mesmas rotas, configs, botões, contadores, redirecionamentos, ordem dos checkouts, status HTTP, permissões, DOM e modo mobile/desktop do carrinho multidomínio. Confirmar `donate|cart|both`, pedidos de Loja/LMS/Doações, guest/auth, domínio cruzado, checkout e webhook assinado, assinaturas, pagamentos e estornos conforme política. Rodar suíte do Portal/tema e testes de acessibilidade. Sem essas provas, declarar **BLOCKED/DEFERRED**, jamais PASS.

## Regras de execução

Fases pequenas, commits isolados na branch/PR existente. Não forçar push, reset, apagar dados, criar PR, mudar checkout ativo sem baseline ou tocar código de outros agentes. Documentar cada etapa em Portal, Commerce e Donation; executar localmente o possível e deixar somente gates dependentes do homelab.

## Estado de implementação (2026-10-22)

P-CART A a D concluídas em código: testes de caracterização, contratos e registry no Portal, providers de Loja (`aculta_commerce`, módulo ainda não habilitado), LMS e Doações, e o seletor das rotas globais agora no Portal (`CommerceCheckoutRouteSubscriber`), com o subscriber do Donation removido após a prova de equivalência. Homologação em servidor de testes pendente (P-CART T).

## Adendo — decisões do responsável (2026-10-22)

Decididas pelo responsável durante a P-CART E, ampliando o que a versão inicial deste ADR previa:

1. **Configuração própria do Portal** `aculta_portal.checkout_settings`: `common_checkout_enabled`, `shop_order_type` e `lms_order_type`. Padrões idênticos ao comportamento anterior (`true`, `default`, `curso`); sem a config, valem os padrões. Não guarda preferência por usuário ou sessão.
2. **Chave global do checkout comum:** com `common_checkout_enabled` falso, as rotas globais do Commerce (`commerce_cart.page`, `commerce_checkout.checkout`, `commerce_checkout.form`) ficam fechadas, como no modo `donate`, e o checkout comum deixa de ser permitido a todos os contextos. O checkout dedicado de Doações não depende dela.
3. **Tipo de pedido do LMS configurável:** o LMS (`CourseOrderEnrollmentManager`, `EnrollmentFormHooks`, `CourseOrderHooks`, `CourseEnrollmentPurchaseForm`), o separador do carrinho (`CartUtility`) e a consolidação (`CartConsolidator`) leem `lms_order_type`. O tipo histórico `curso` continua reconhecido para que pedidos já feitos mantenham matrícula, separador e ausência de estorno.
4. **Estorno:** curso e doações **não têm estorno**. A regra fixa em `curso` não muda; `RefundEligibilitySubscriber` apenas **acrescenta** o tipo de pedido de curso configurado à lista de bloqueio. Isso deve constar nas políticas do site e nos termos de uso (ver pendência em `docs/portal/ROADMAP.md`).
5. **Tipo de pedido da Loja** classifica e consolida o carrinho no Portal; a resolução do tipo de pedido de cada produto continua no Commerce (tipo de item).

**P-CART E (2026-10-22):** abas administrativas em `/painel-administrativo/aculta/checkout` (Comum, Loja, LMS) e `/checkout/doacoes` (registrada pelo `aculta_donation`, que é dono do formulário e evita dependência do Portal em módulo opcional). Permissões específicas por aba; sem acesso a pedidos.

## Adendo — identidade fiscal compartilhada (Loja e LMS, 2026-10-23)

Decisão do responsável, registrada em 2026-10-23:

> Em 2026-10-23, o responsável determinou que os checkouts comerciais da Loja e do LMS utilizem a mesma política obrigatória de identidade fiscal, baseada no documento mantido em `/dados/outros-dados` pelo `aculta_id_cpf`. O checkout padrão de doações permanece dispensado. A exigência não se aplica à participação gratuita no LMS nem implica fusão física de carrinhos ou pedidos.

Consequências para esta ADR:

1. **Módulo oficial de CPF/CNPJ:** `aculta_id_cpf` (machine name preservado). `aculta_associado_id` está descontinuado; sua remoção depende de verificação de Runtime e está documentada em `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`, seção 4.
2. **Dono do contrato de exigência:** `aculta_portal` define o estado de identidade fiscal e a política de exigência por contexto. Os checkouts de `aculta_commerce` e `aculta_lms` são consumidores. `aculta_donation` é dispensado por padrão.
3. **A exigência vem do contexto validado do pedido** (tipo de pedido configurado em `CheckoutSettings`, loja e total maior que zero), nunca de URL, parâmetro de sessão ou indicação do navegador.
4. **Sem cópia do documento:** o CPF/CNPJ não é gravado em pedido, perfil de faturamento, entidade LMS ou sessão. A única fonte é `profile.participante.field_documento`.
5. **Sem fusão:** Loja, LMS e Doações mantêm tipos de pedido, carrinhos e flows próprios (Commerce indexa carrinhos por tipo, loja e conta).
6. **Matrícula inalterada:** o LMS continua dono da matrícula. Pagamento concluído continua sendo o gatilho. CPF/CNPJ cadastrado não libera matrícula.

**Auditoria I-A (carrinhos reais):** detalhada em `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`, seção 5. Resumo: o isolamento por tipo de pedido existe; nenhum checkout comercial exige identidade fiscal hoje; cursos gratuitos geram pedido de total zero que passa pelo checkout comercial.

**Situação:** contrato implementado (`FiscalIdentityPolicy`, `FiscalIdentityPane`); configuração do checkout comercial unificada no flow `default`; ativação em runtime pendente de `config:import` autorizado.

**Unificação do checkout comercial (2026-10-23):** Loja (tipo `default`) e LMS (tipo `curso`) passam a usar um único flow, o `default`, com o pane `aculta_fiscal_identity`. O flow `curso`, duplicado, sai da configuração. Os tipos de pedido continuam separados, como o responsável determinou. Login obrigatório e cadastro dentro do checkout permitido, como no LMS. Doações mantêm os flows `doacao` e `assinatura_flow`, sem o pane.

