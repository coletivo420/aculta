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

# ADR-054/055 — galeria editorial em UI Patterns e correção do catálogo — 2026-11-11

- **Objetivo:** migrar `aculta_album_fotos:block_1` de VVJS para UI Patterns + SDC `layout-grid`, e corrigir/validar `/painel-administrativo/aparencia/ui/components` antes de continuar.
- **Estado:** migração da galeria publicada em `06c2769c67b49326774f05a8b8f7368db1a6910b`. Runtime preservou 63 imagens PhotoSwipe, ordem, acesso e cache; Fórum permanece isolado. Catálogo verificado em Firefox ESR com conta administrativa nova: 82 componentes/16 grupos, story SDC carregada e 11 agregados JS `200 text/javascript` após `drush cr`.
- **Arquivos principais:** configuração da View, `GalleryViewHooks`, gate de contrato, inventário CSV/JSON, ADR-054/055, changelog/roadmap/TODO/status e teste/matriz de captura em `tests/browser/magazine/aculta-gallery-ui-patterns/` e `tests/browser/capturas/ui-patterns-gallery/2026-11-11/manifest.json`.
- **Verificações:** Firefox A/B da galeria 10/10 antes e 11/11 depois em 320/390/768/1024/1440 e claro/escuro; PhotoSwipe teclado Escape PASS; PHPCS, PHPStan, Drupal 12 1243, módulo/tema, Views 60, gate de galeria, status index e diff-check PASS. Índice de navegador global FAIL por worktrees/pastas não versionadas e caminhos ausentes preexistentes.
- **Catálogo:** URL de agregado `locale/translations` antiga ainda responde 400 quando chamada diretamente; HTML recém-gerado no acesso administrativo carrega somente URLs válidas. Avisos CSP inline são `report-only` e vêm de script de navegação/estilos administrativos e markup; nenhuma diretiva foi enfraquecida.
- **Pendências:** zoom 200%, cache-hit, perfis editor/admin no álbum e revisão por tecnologia assistiva/aceite humano. VVJS permanece por consumidores fora do álbum; sem desinstalação. ADRs 054/055 continuam abertas.
- **Próximo passo:** conferir manifesto/SHA, publicar o registro da evidência e migrar a próxima View/superfície com uso real.
