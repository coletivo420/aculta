# ACULTA Commerce — 0.1.0-dev.1

Submódulo inicial de integração comercial da **loja**. A orquestração **multi-carrinho e multi-checkout** pertence ao `aculta_portal`, conforme ADR-013. Estado: fundação documental e manifesto, **sem serviços operacionais próprios ativos**. Não habilitar em produção antes da homologação da fase 0.1.0.

## Fronteiras imutáveis

- **Loja:** Drupal Commerce é proprietário das entidades de produto, variação, pedido, carrinho, pagamento e demais transações da loja; `aculta_commerce` coordena serviços específicos da loja.
- **Doações:** `aculta_donation` é proprietário de campanhas, checkout, doações e assinaturas. Green Stash não absorve essa experiência nem altera os pedidos de doação.
- **LMS:** `aculta_lms` é proprietário de matrículas, acesso a cursos, ensino e suas regras. Uma venda de acesso educacional depende de contrato explícito com o LMS.
- **Pagamento compartilhado:** `aculta_pagamentos_mercadopago` é o gateway central de **loja, LMS e doações**. Não duplicar o plugin nem implementar gateways adicionais em `aculta_commerce`.
- **Portal:** `aculta_portal` conserva o shell transversal, contratos de domínio e identidade; as integrações comerciais atuais só podem mudar de dono após caracterização e migração auditada.
- **Estatísticas:** `aculta_statistics` conserva eventos e agregações; Green Stash apenas consome relatórios autorizados.
- **Studio:** `aculta_studio` hospeda a experiência **Green Stash**, distinta de GanjaLab, GanjaLab420 e WE SATIVA.
- **Tema:** `aculta420` é o dono da implementação visual SDC/UI Suite e preserva o cabeçalho multidomínio.

## Estado

Não há `aculta_commerce` na árvore anterior desta branch; há `ShopController`, checkout panes, relatórios e integração com Mercado Pago no Portal. Esta primeira entrega **não move entidades, não altera checkout, não registra rotas nem habilita serviços**. O próximo passo é caracterização completa, classificação de ownership e migração incremental.

Documentos:
- [Roadmap e auditoria inicial](docs/ROADMAP.md).
- [Contrato de indexação do catálogo](docs/CONTENT-INDEX.md).

## Gate de habilitação no servidor de testes

Validar compatibilidade das dependências do manifesto com os módulos instalados, `drush pm:list`, `drush en aculta_commerce -y` apenas no runtime isolado, `drush cr`, `drush updatedb:status` e `php scripts/validate-module-theme-boundary.php`. Não ativar nem instalar automaticamente nesta fase.

## Integração com a carteira/carrinhos do Portal

`aculta_commerce` fornece o contexto **Loja** ao gerenciador transversal do Portal; o Portal administra as abas **Comum | Loja | LMS | Doações**, seleciona modalidade de checkout por carrinho/pedido e preserva o checkout específico de cada módulo. A aba Comum não significa fusão física de pedidos. O Green Stash permanece a experiência Studio de administração da loja. Ver [roadmap](docs/ROADMAP.md) e [ADR-013](../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md). Integração ainda **não implementada**.

## RETIFICAÇÃO ADR-013 — Abas de administração, não de compra

O `aculta_commerce` fornecerá configuração do checkout da **Loja** à orquestração administrativa do `aculta_portal`. **Comum | Loja | LMS | Doações** são exclusivamente abas internas administrativas; não criar uma interface de carrinho público. O carrinho da barra multidomínio e o seletor de checkout atuais são preservados. Green Stash continua gestão da loja no Studio. O Mercado Pago continua gateway transversal. Ver [ADR-013](../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md).

## Green Stash — fundação 0.1.0-D

Permissão restrita `access aculta green stash`, serviço `aculta_commerce.green_stash_access` e teste de caracterização implementados. **Ainda não há rota, provider Studio, controller nem acesso a entidades**: o gate de entrada não concede operações de Commerce. Ver [plano D](docs/GREEN-STASH-0.1.0-D.md). Não habilitar em produção sem `drush cr`, PHPUnit, validação de permissões e homologação de dependências.

## Green Stash — ponto de entrada Studio D-A/D-B

Criado provider independente `green_stash` e rota `/green-stash` (purpose `account`) protegida por `access aculta green stash`. O registro no `StudioExperienceManager` ocorre pelo hook de container do próprio Commerce, sem fazer Studio depender de Commerce. O conteúdo é **exclusivamente informativo** até a fase de integração de dados com autorização por entidade/store. Ainda não homologado no servidor. Ver [documentação D](docs/GREEN-STASH-0.1.0-D.md).

## Green Stash — navegação inicial GS-0.2-A/B

Criada navegação progressiva com nove áreas: Visão geral, Produtos, Pedidos, Estoque, Envios, Promoções, Clientes, Relatórios e Configurações, acessíveis via `/green-stash/{section}` no purpose `account`, sem operações de negócio. A permissão `access aculta green stash` protege as rotas e a verificação do Studio permanece no controller. Renderização básica de estado vazio, **sem componentes SDC definitivos ou operações comerciais ativas**. Consultar [proposta mobile-first](docs/GREEN-STASH-UX-MOBILE-FIRST.md).
