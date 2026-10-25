# THEME-UX 0.8 — B6-S1a-L3b: inspeção dirigida e correção de fixture

**Data:** 2026-10-22 · **PR:** #297 · **Estado:** lote incremental; auditoria exaustiva ainda aberta.

## Evidência da branch

O HEAD inicial consultado para esta continuação foi `8fb4686e17c25cbc7d1908bffb8bfd6ceb16731c`. Foram examinados os seguintes **7 arquivos de conteúdo** neste lote: `cart-offcanvas.twig`, `cart-offcanvas.vazio.story.yml`, `context-tabs.wiki.story.yml`, `course_card.catalogo.story.yml`, `donation-pane.twig`, `donation-pane.exemplo.story.yml` e `revista-publication.twig`.

## Achados

| Arquivo | Evidência observada | Decisão |
| --- | --- | --- |
| `patterns/cart-offcanvas/cart-offcanvas.twig` | Loop percorre `sites`, depois `site.items`, diferenciando checkout geral e assinatura | Contrato atual deve ser preservado; nenhuma mudança no renderer |
| `patterns/cart-offcanvas/cart-offcanvas.vazio.story.yml` | Fixture usa `count: 0`, `empty: true`, sem itens | Mantida; renderização visual não homologada |
| `patterns/cart-offcanvas/cart-offcanvas.default.story.yml` (lote anterior) | Usava `props.items` na raiz, ausente da metadata e não consumido pelo Twig | **Corrigida neste ciclo:** fixture agora declara `sites` e `site.items`, com checkout ilustrativo; sem alterar Commerce |
| `patterns/context-tabs/context-tabs.wiki.story.yml` | Âncoras locais são demonstrativas e não representam rotas/autorização reais | Aferir navegação, focus e fallback sem JS no Runtime |
| `content/course_card/course_card.catalogo.story.yml` | Dados fictícios de LMS, `extra_fields: []`, slot de ação demonstrativa | Não inferir permissões ou integração com LMS |
| `donation-pane/donation-pane.twig` | Apenas imprime `elements`; comentário de scaffold legado | Migrar schema e revisar Form API em B6-S2, sem refazer formulário |
| `donation-pane/donation-pane.exemplo.story.yml` | String de placeholder no lugar do formulário real | Não homologa Form API/validação/CSRF |
| `patterns/revista-publication/revista-publication.twig` | Inclui `aculta420:figure` via Twig component include; demais slots chegam do provider | Composição SDC explícita; verificar cache, autoria e discussões no consumidor real |

## Correção registrada

**B6-S1a-L3-01 — story Cart Offcanvas:** a inconsistência entre `props.items` e o par metadata/Twig foi corrigida no arquivo da story, substituindo a prop sem consumidor por `sites` com item e link de checkout demonstrativos. Esta correção documental/fixture **não certifica a renderização em UI Patterns Library**. Recomenda-se validar a árvore de dados aninhados perante a versão Core/UI Patterns instalada, em especial o schema incompleto de `sites.items` já levantado em B6-S1a-01.

## Próxima microfase

Prosseguir com a auditoria **sistemática** do restante de Twig/CSS/stories, de preferência por script local que percorra a árvore e produza manifest por caminho com checks reproduzíveis. Depois rastrear consumidores B6-S1b e corrigir schemas legados das doações B6-S2. Os limites estáticos e Runtime devem continuar separados.

**Regra:** não duplicar Institution Bar / Domain Header; reutilizar apenas SDCs compatíveis com UI Suite; preservação de funcionalidade pertence aos respectivos kernels.
