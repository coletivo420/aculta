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
