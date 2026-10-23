# ACULTA Commerce — Roadmap e auditoria 0.1.0-A

Data de início: 2026-10-22. Branch: `docs/aculta-forum-0.1.0-dev.1`. Execução por commits isolados na PR já aberta.

## Melhor Envio — dependência de CEP (ME-CEP 0.1)

- Auditoria em `docs/portal/CEP-SHARED-LOOKUP-0.1.md`. Serviço `aculta_portal_cep.lookup` e adaptador `aculta_commerce_melhorenvio` concluídos (Unit). Kernel pendente (DT-CEP-01).
- O submódulo `aculta_commerce_melhorenvio` fica em `modules/aculta_commerce_melhorenvio/` dentro de `aculta_commerce`. Roteiro em `modules/aculta_commerce_melhorenvio/docs/ROADMAP.md`.

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

**Fases Comércio-CART:** C-A inventariar tipos/flows/rotas da Loja; C-B provider de configuração do Portal (**entregue e habilitado em 2026-10-22** por decisão do responsável: `ShopCheckoutProvider` e `aculta_commerce.services.yml`; `aculta_commerce` consta em `config/sync/core.extension.yml` e está habilitado no runtime `toca`; a entrega C-B não adicionou configuração, esquema nem hooks, e o rollback é `drush pm:uninstall aculta_commerce` mais remover a linha do sync); C-C formulários/validações da aba administrativa Loja; C-D integração segura com Green Stash e rotas existentes sem alterar carrinho; C-T regressões visuais/funcionais e pagamentos no homelab. Não executar trabalho de storefront nesta frente. Esta seção substitui referências antigas a abas de carrinho público.

## Identidade fiscal no checkout (CPF-CHECKOUT-0.2-I, 2026-10-23)

- O checkout comercial da Loja exige CPF/CNPJ válido para concluir a compra. Política compartilhada com o LMS; o documento vem de `aculta_id_cpf`, nunca é copiado para o pedido.
- Flow e pane compartilhados com o LMS; tipo de pedido `default` mantido.
- Doações ficam fora da exigência padrão.
- Pendente: implementação (I-D) e testes. Ver `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`.

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

## Pesquisa e próxima frente COM-SF — Frete brasileiro / estoque (2026-10-22)

[Pesquisa comparativa e roteiro de implementação](SHIPPING-STOCK-BRAZIL-RESEARCH.md): manter `commerce_shipping:^3.0` já presente, auditar código `melhor_envio` antes de copiar/portar, avaliar integração Correios pela API moderna, instalar preferencialmente `commerce_stock:^3.0` em homelab (sem reimplementar), testar consolidação loja/LMS/doação e preservação do carrinho multidomínio. Decisões de shipping carriers, estoque, etiquetas e telas Green Stash são fases separadas, sem alterar gateway. Dívidas DT-COM-14–17 em `docs/operations/DEBT-REGISTER.md`. Sem dependências novas ativadas nesta etapa.

## Green Stash — UX mobile-first (2026-10-23)

[Plano de navegação, telas e componentes](GREEN-STASH-UX-MOBILE-FIRST.md). Experiência independente no Studio; navegação Início, Produtos, Pedidos, Estoque, Envios, Promoções, Clientes, Relatórios e Configurações. Em 320px priorizar quatro destinos + Mais, listas em cartões e ações contextuais; desktop oferece sidebar e tabela. Reutilizar SDC/UI Suite do ACULTA420, nunca mexer no cabeçalho multidomínio. Próxima etapa GS-0.2-A: contratos/ACL de navegação; GS-0.2-B: shell com estados vazios; GS-0.2-C: dados operacionais reais. Plano **não implementado em UI**.

## GS-0.2-A/B — navegação inicial implementada (2026-10-23)

`GreenStashNavigation` fornece nove áreas navegáveis com rotas internas `/green-stash/{section}`, controladas por `access aculta green stash` e purpose `account`. O controller aplica também os checks do StudioExperienceManager. Render arrays têm heading, navegação acessível e estado de preparação sem dados privados ou operações financeiras. Teste Unit criado: `GreenStashNavigationTest`. **Não existe nesta fase UI SDC final, bottom navigation nem listagem real de produtos/pedidos; não alegar interface operacional pronta.** Próximo GS-0.2-C: autorização por store/entidade + dados reais paginados; parte visual SDC/UI Suite no tema após auditoria. PHP/Kernel/Browser e rotas no homelab pendentes.

## GS-0.2-B.1 — Correção de rotas PT-BR e descoberta Studio (2026-10-23)

- URLs canônicas: `/estudio/loja`, `/estudio/loja/visao-geral`, `/estudio/loja/produtos`, `/estudio/loja/pedidos`, `/estudio/loja/estoque`, `/estudio/loja/envios`, `/estudio/loja/promocoes`, `/estudio/loja/clientes`, `/estudio/loja/relatorios`, `/estudio/loja/configuracoes`. URLs não usam acentos para estabilidade.
- IDs internos de rota `aculta_commerce.green_stash[_area]`, entidades, namespaces e API PHP preservados. O parâmetro de URL é `secao`; mapeamento explícito de slug público PT-BR para identificadores internos.
- Registro do provider Green Stash migrado do hook `aculta_commerce_container_alter` para `AcultaCommerceServiceProvider::alter()`, mecanismo Drupal próprio de alteração de definições de serviço. Continuidade condicionada a compilação do container e teste no homelab.
- Não adicionar redirecionamento público legado de `/green-stash` até revisar links de terceiros e rotas históricas. As rotas anteriores não foram homologadas para produção, mas validar a necessidade de redirecionamento HTTP 301/308 no ambiente.
- GS-0.2-C: **antes de exibir consultas reais**, implementar filtro por store/entidade e assegurar que totais/contadores não vazem dados. O shell de navegação atual ainda não implementa o design mobile-first do tema.

## GS-0.2-C.1 — Primeira consulta comercial de leitura (2026-10-23)

- A rota `/estudio/loja/produtos` agora apresenta um primeiro subconjunto de produtos `produto_loja` que passam simultaneamente em `view` e `update` da entidade, **além de** `update` em pelo menos uma store associada; não expõe produtos de LMS e não introduz CRUD alternativo.
- Leitor `Catalog/GreenStashProductReader.php`: query com `accessCheck(TRUE)`, amostra limitada a 100 IDs e exibição de até 20 produtos; sem totais e sem alegação de paginação completa. Ação `Editar produto` usa rota nativa Commerce.
- Renderização provisória em Render API, com seção do catálogo não cacheada (`max-age: 0`). O tema ACULTA420 continua proprietário da futura UI SDC/UI Suite mobile-first.
- Rotas PT-BR mantidas; corrigidas as chaves de links para usar os próprios slugs PT-BR. Não foi implementada consulta privada de pedidos, pagamentos, clientes nem métricas.
- **Gate GS-0.2-C.2**: revisar segregação por store (especialmente produtos presentes em mais de uma store); paginação baseada em filtros de acesso, performance em catálogos extensos, cache contexts e testes Kernel/Functional.
- **Gate homelab**: `drush cr`, instalação em site de teste, testes da matriz operador sem permissão / operador com permissões Commerce / multi-store / usuário comum, correção de alias e tradução e validação SDC. Nenhuma operação financeira habilitada.

## GS-0.2-C.2 — Catálogo com busca e paginação por cursor (2026-10-23)

- `GreenStashProductReader::page()` oferece busca `busca` em título e cursor `antes`, mantendo `accessCheck(TRUE)`, `view/update` da entidade e `update` em ao menos uma loja associada. Não há query de contagem nem exposição de produtos LMS.
- Rota PT-BR mantida: `/estudio/loja/produtos`. Render API exibe formulário GET acessível, resultado em lista simples e link Próxima página; acesso/consulta não são cacheados publicamente.
- Segurança: limite de 20 produtos por página, leitura de até cinco lotes de 100 IDs por solicitação. **Limitação:** se muitas entidades forem filtradas por grants, uma página pode ser parcial e não oferecer Próxima página embora existam produtos mais antigos. Não chamar isso de paginação exaustiva até integrar store filtering à consulta.
- Sem funcionalidades de pedidos ou pagamento; SDC mobile-first ainda pendente, conforme GS-NAV-02.
- Gates pendentes: Kernel/Functional para grants em produto e store, segurança de cursor e entrada de busca; desempenho em alto volume e regressão após `drush cr` no homelab.

## Decisão obrigatória — Green Stash substitui operação administrativa Commerce (2026-10-23)

[Contrato operacional Green Stash](GREEN-STASH-OPERATIONS-CONTRACT.md): todas as operações cotidianas da loja (novo/editar produtos, variações, estoque, expedição, etiquetas) devem ocorrer em rotas próprias PT-BR dentro de `/estudio/loja`. Usar Commerce/Stock/Shipping como backend; não apontar operadores para `edit-form` ou `/painel-administrativo`. Primeiro commit removeu o link nativo de edição e exibiu estado preparatório. Próximo GS-EDITOR-0.2: página interna de detalhe, acesso por loja e entidade, depois formulários Studio de criação e edição. Não considerar a fase concluída antes dos testes end-to-end com operador sem acesso administrativo Drupal.

## GS-EDITOR-0.2 — Detalhe próprio de produto (2026-10-23)

Implementado endpoint de leitura `/estudio/loja/produtos/{produto}`, com controller independente GreenStashProductController, status de publicação e retorno à listagem. A listagem aponta para o detalhe interno Studio, nunca para `edit-form`/painel administrativo. O mesmo método `GreenStashProductReader::canManage` é usado na listagem e no detalhe e exige `produto_loja`, acesso view/update de entidade e update em uma store associada. Rota exige entrada Green Stash e validação do ExperienceManager. Testes unitários básicos de negação criados, **não executados no homelab**. O detalhe é read-only, sem imagem, variações, alterações ou novos formulários.

Próxima fase GS-EDITOR-0.3: criação própria de produtos via Form API com API/validações Commerce, destino `/estudio/loja/produtos/novo`; antes de liberar, auditar store access no create, regras de SKU/variações, CSRF, validação de formulário e edição concorrente. SDC/UI Suite mobile-first continua dependência do tema.

## GS-EDITOR-0.3-A — Cadastro próprio de rascunho (2026-10-23)

Criada rota PT-BR `/estudio/loja/produtos/novo`, usando Form API próprio `GreenStashProductCreateForm`. O cadastro inicial solicita título e loja, exige autorização de experiência Studio, `createAccess('produto_loja')` do Commerce e `update` na loja selecionada. A seleção é revalidada no submit. Produto salvo como **não publicado**, sem acesso aos formulários admin, checkout, preço ou estoque. Formulário aplica validação da entidade e recusa gravação se houver violations. A listagem fornece link `Novo produto`.

**Limites obrigatórios:** não são cadastradas variações, SKU, preço, imagens, mídia, pesos nem disponibilidade. Não afirmar que o produto está pronto para comercialização; as fases GS-EDITOR-0.3-B/0.4 deverão usar API Commerce/Stock e UI SDC/UI Suite, verificar diferenças entre validação Form API e constraints reais e impedir publicação incompleta. Priorizar testes Kernel/Functional para createAccess por store e aplicação de `stores` na entidade, permissões e CSRF. Nenhum teste homelab executado nesta entrega.

## Loja unica Green Stash (2026-10-23)
Green Stash gerencia uma unica loja comercial. Nenhum seletor de loja no cadastro; LMS e Doacoes sao dominios separados. GreenStashStoreResolver determina a unica entidade commerce_store existente sem ID fixo, exige autorizacao update e bloqueia ambiguidades (zero ou varias lojas). O formulario cria rascunho produto_loja associado automaticamente a loja. O leitor do catalogo usa o mesmo resolvedor. Homologacao homelab e testes multi-entidade pendentes.

## Conciliação das frentes Portal e Green Stash (2026-10-23)

O registro de serviços preserva `aculta_commerce.checkout_provider` e os serviços da experiência Green Stash na mesma definição YAML. A entrega C-B e a identidade fiscal locais foram mantidas junto das fases remotas GS-EDITOR e do resolvedor de loja única. A conciliação altera o código do branch; não confirma instalação nem homologação de Green Stash no Runtime.

Validação da conciliação (dev.3): a fixture do leitor foi atualizada para receber o resolvedor obrigatório de loja. Unit Commerce 20/116 (9 depreciações PHPUnit), Melhor Envio 29/78 e CEP 9/36 passaram. Foi registrado o status documental do novo submódulo Melhor Envio; nenhuma credencial, chamada à API logística ou operação de checkout foi executada.

Gate módulo/tema após conciliação: removida a classe visual própria dos links Green Stash em favor de `data-portal-ui`. Permanecem cinco violações anteriores à integração em Studio, LMS e Wiki; o gate global não está aprovado. Gate estático de endereço PASS; nenhuma revisão visual foi executada nesta fase.
