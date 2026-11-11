# ADR-054/055 — Grade editorial da Home MAIN — 2026-11-11

- **Objetivo:** substituir o carrossel visual de `home_editorial_highlights:block_1` pelo SDC `layout-grid` via UI Patterns.
- **Estado:** Runtime importado após snapshot; IDs 14/15/16, ordem, 3 rows, acesso anônimo, pager/query/cache do View preservados. Firefox ESR A/B 10/10 em 320/390/768/1024/1440, claro/escuro; foco, H1/main, contraste ≥8,80:1 e reflow passaram. Cache-hit e zoom 200% DEFERRED.
- **Decisões e alternativas descartadas:** usuário confirmou não haver comportamento legado; removido o carrossel e testes/utilitários sem consumidores. Sem desinstalar VVJB, pois exige autorização específica.
- **Cuidados:** respostas privadas/no-cache e dynamic cache UNCACHEABLE; não alegar cache-hit. Snapshot e rollback YAML comparados antes do import.
- **Commits e arquivos:** pendente; View Sync, gerador/inventário, testes de navegador, verificadores e docs ADR/roadmap/tema.
- **Verificações:** Views 60, Styles 40, SDC schema 1774, render SDC 41, status-index 44, PHPCS Drupal, PHPStan focal, transacional Views, navegador A/B, Stylelint, lint e diff-check PASS. Browser-index e token-budget globais FAIL por worktrees/limites preexistentes.
- **Pendências e bloqueios:** cache-hit, zoom 200%, revisão visual humana e eventual desinstalação VVJB aguardando autorização; demais Views/domínios em aberto.
- **Próximo passo:** migrar próxima View com saída real usando snapshot, paridade funcional e validação A/B.

# ADR-054/055 — UI Patterns Views Notícias — 2026-11-11

- **Objetivo:** migrar `aculta_news:block_1` para UI Patterns Views usando o SDC existente `revista-listing`.
- **Estado:** quatro teasers e HTTP 200 preservados; Firefox ESR antes/depois passou 10/10 em cinco larguras e dois modos, foco visível, contraste mínimo 7,16:1, um H1/main e sem overflow. Cache-hit, zoom 200% e revisão visual humana DEFERRED.
- **Decisões e alternativas descartadas:** manter row nativa `entity:node`/`teaser`, access/filter/sort/pager/query e cache; slot `view_rows` do SDC. Removida classe visual genérica só desta View.
- **Cuidados:** a página é `private/no-cache` e dynamic cache `UNCACHEABLE`; dois requests foram MISS. Não inferir variação por cache-hit.
- **Commits e arquivos:** `e69380f39`; View Sync, inventário, runner, browser/evidências, SDC e documentação ADR/roadmap.
- **Verificações:** Views 60, UI Styles 40, SDC schema 1774, render SDC 41, foundations 83 pares, Stylelint, PHP lint e cache rebuild PASS. Browser-index global e token-budget FAIL por pendências preexistentes/worktrees aninhadas.
- **Pendências e bloqueios:** executar zoom e revisão visual; resolver as falhas globais fora do escopo; migrar demais Views e domínios.
- **Próximo passo:** selecionar a próxima View com saída real e repetir snapshot, paridade funcional e A/B visual no Runtime.
