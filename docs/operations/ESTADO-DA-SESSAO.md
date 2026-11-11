# ADR-054/055 — Wiki em UI Patterns Views — 2026-11-11

- **Objetivo:** migrar listagem pública e blocos recentes Wiki para UI Patterns + SDC existente.
- **Estado:** `wiki_entries` page e dois blocos usam `layout-grid`; teaser reaproveita `wiki-entry`. Runtime/Sync parseados iguais; seis node IDs e filtros/acesso/order/pager preservados; MAIN não recebe a rota Wiki.
- **Verificações:** Firefox ESR 215/215 em home/listagem/artigo, 320/390/767/768/1440, claro/escuro; Views 85, schemas SDC 84/1801 e render SDC 42 esperados nesta rodada. Capturas e SHA em `tests/browser/capturas/ui-patterns-wiki-entries/2026-11-11/manifest.json`.
- **Pendências:** cache-hit, zoom 200%, perfis editor/admin, tecnologia assistiva e aceite visual do responsável DEFERRED; Stylelint focal PASS; CSS-scope mantém duas falhas em preprocess_html account/wiki, em código fora deste diff. ADRs abertas.
- **Próximo passo:** conferir gates finais, regenerar inventário/status e publicar microfase; seguir para outro consumidor Views.

# ADR-054/055 — lista de transparência em UI Patterns — 2026-11-11

- **Objetivo:** substituir o teaser visual genérico da View pública de documentos pelo SDC `document-card` e UI Patterns `layout-grid`.
- **Estado:** `aculta_documents:block_1` importada no Runtime após snapshot; 3 rows anônimas, H1/main únicos e cache contexts presentes. Sync/Runtime parseada coincide. Links de arquivo não comprovados: fixtures têm descrições vazias. Captura visual/teclado, escuro, zoom 200%, perfis editor/admin e cache-hit pendentes.
- **Decisões e alternativas descartadas:** preservar row nativa `entity:node` teaser e passar slots como render arrays; não reproduzir marcação da Field API em Twig.
- **Cuidados:** browser `aculta-document-viewer` testa mídia incorporada, não esta lista. Import alterou apenas a config da View.
- **Commits e arquivos:** `42295a4d3` publicado; componente `document-card`, template teaser, View Sync, inventário, ADRs e docs do tema.
- **Verificações:** schema SDC PASS (83/1786); render SDC Drupal PASS; Views PASS (60); PHPUnit 3/19 PASS com depreciação PHPUnit; PHPStan/PHPCS PASS; índice status PASS; `git diff --check` PASS. Stylelint DEFERRED (binário ausente). Token budget FAIL por 7 violações globais preexistentes.
- **Pendências e bloqueios:** browser, link com dados reais, perfis, zoom/cache e homologação ampla; ADRs permanecem abertas.
- **Próximo passo:** executar captura focal de `/transparencia` com browser disponível, revisar e publicar esta entrega isolada.

# ADR-054/055 — catálogo da Loja em UI Patterns — 2026-11-11

- **Objetivo:** migrar o grid nativo do catálogo Commerce para UI Patterns Views sem alterar a apresentação funcional de produto/compra.
- **Estado:** `aculta_shop_catalog` block/page usa `layout-grid`; adapter do view mode `catalog` compõe `product-card` com render arrays nativos. Sync/Runtime coincidem. `/produtos` HTTP 200, produto 1 e add-to-cart iguais antes/depois. Firefox headless 10/10 em 320/390/768/1024/1440 claro/escuro, sem overflow, foco PASS, contraste ≥12,4:1. Capturas PNG locais e hashes no manifest.
- **Decisões e alternativas descartadas:** manter row `entity:commerce_product`/`catalog`, formulário e plugin Commerce; UI Patterns substitui só o style grid.
- **Cuidados:** cache dinâmico continua `UNCACHEABLE`; só há um produto publicado, sem prova de colunas com várias rows. Browser-index global falha em pastas/worktrees preexistentes.
- **Commits e arquivos:** pendente deste commit; View Sync, gate Views, gerador/inventário, ADRs, changelog/roadmap/status e suíte browser reutilizável.
- **Verificações:** Runtime 200 antes/depois, 1 row, cache private; paridade Sync/Runtime; Views 72; Firefox 10/10; schema SDC 84/1798, render Drupal SDC 42, PHPUnit 3/17 (1 depreciação), PHPCS, PHPStan, foundations 83 pares, UI Styles 40 e status index PASS. Stylelint DEFERRED (binário ausente); browser-index global FAIL preexistente; token-budget FAIL por 7 violações globais. Zoom 200%, cache-hit, perfis e aceite visual humano DEFERRED.
- **Pendências e bloqueios:** migrações site-wide, outros papéis/tecnologia assistiva, zoom/cache e homologação ampla; ADRs continuam abertas.
- **Próximo passo:** migrar outra superfície pública/admin do inventário e manter testes por domínio/owner.
