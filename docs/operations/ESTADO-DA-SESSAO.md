# ADR-054/055 — Views de galeria em UI Patterns — 2026-11-11

- **Objetivo:** remover VVJS das galerias Forum/Commerce e concluir a correção da rota de catálogo UI Components.
- **Estado:** Fórum e produto usam `layout-grid`; Runtime/Synchronização parseada coincide; Forum 0 rows, produto 7 rows. Catálogo abre com 82 componentes/16 grupos e JS 200. Navegador da galeria: 11/15; 4 falhas da toolbar Gin no MAIN; Fórum passa todas as larguras.
- **Decisões e alternativas descartadas:** sem retenção dos visuais VVJS nas features; manter PhotoSwipe, View/Commerce APIs e exemplo upstream `vvjs_example` como `BLOCKED_UPSTREAM`.
- **Cuidados:** imports parciais isolados após snapshots; nenhum uninstall. Fórum sem fixture; produto sem revisão visual/cache no browser.
- **Commits e arquivos:** `2d49a9fdc`; Views Forum/Commerce, Gallery hooks, inventário, ADRs, tema, status e gates.
- **Verificações:** Views 60, Gallery contract, Portal Drupal 12 1243, PHPCS/PHPStan, status index, diff-check e cache rebuild PASS. Token-budget global FAIL preexistente (7 itens).
- **Pendências e bloqueios:** fixture Forum e aceites; browser product; toolbar Gin mobile `BLOCKED_UPSTREAM`; migração site-wide/US-14 continuam.
- **Próximo passo:** avançar para a próxima View/interface com dados reais e homologar as pendências de UI Skins.

# ADR-054/055 — galeria editorial em UI Patterns e correção do catálogo — 2026-11-11

- **Objetivo:** migrar `aculta_album_fotos:block_1` de VVJS para UI Patterns + SDC `layout-grid`, e corrigir/validar `/painel-administrativo/aparencia/ui/components` antes de continuar.
- **Estado:** migração da galeria publicada em `06c2769c67b49326774f05a8b8f7368db1a6910b`. Runtime preservou 63 imagens PhotoSwipe, ordem, acesso e cache; Fórum permanece isolado. Catálogo verificado em Firefox ESR com conta administrativa nova: 82 componentes/16 grupos, story SDC carregada e 11 agregados JS `200 text/javascript` após `drush cr`.
- **Arquivos principais:** configuração da View, `GalleryViewHooks`, gate de contrato, inventário CSV/JSON, ADR-054/055, changelog/roadmap/TODO/status e teste/matriz de captura em `tests/browser/magazine/aculta-gallery-ui-patterns/` e `tests/browser/capturas/ui-patterns-gallery/2026-11-11/manifest.json`.
- **Verificações:** Firefox A/B da galeria 10/10 antes e 11/11 depois em 320/390/768/1024/1440 e claro/escuro; PhotoSwipe teclado Escape PASS; PHPCS, PHPStan, Drupal 12 1243, módulo/tema, Views 60, gate de galeria, status index e diff-check PASS. Índice de navegador global FAIL por worktrees/pastas não versionadas e caminhos ausentes preexistentes.
- **Catálogo:** URL de agregado `locale/translations` antiga ainda responde 400 quando chamada diretamente; HTML recém-gerado no acesso administrativo carrega somente URLs válidas. Avisos CSP inline são `report-only` e vêm de script de navegação/estilos administrativos e markup; nenhuma diretiva foi enfraquecida.
- **Pendências:** zoom 200%, cache-hit, perfis editor/admin no álbum e revisão por tecnologia assistiva/aceite humano. VVJS permanece por consumidores fora do álbum; sem desinstalação. ADRs 054/055 continuam abertas.
- **Próximo passo:** conferir manifesto/SHA, publicar o registro da evidência e migrar a próxima View/superfície com uso real.
