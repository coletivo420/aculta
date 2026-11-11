# ADR-054/055 — UI Icons — 2026-11-11

- **Objetivo:** conferir UI Icons 2.1 e inventário Sync/Runtime.
- **Estado:** menus principal/conta usam `icon_picker`, restritos a Phosphor; UI Patterns Sync usa Picker e Runtime Popover. Packs Fluent Emoji continuam no fluxo editorial do tema. Sem aceite visual/SDC.
- **Decisões:** não importar ainda; snapshot mostra submódulos Runtime adicionais ausentes da Sync, e `cim` amplo poderia desativá-los.
- **Cuidados:** runtime/sync selector drift; alinhar extensões/config num delta revisado. Snapshot: três configs exportadas; recriar antes do import.
- **Commits e arquivos:** pendente; inventário, ADR-055, snapshot Runtime, CSV e handoff.
- **Verificações:** `UiIconsPackTest` 2/47 PASS; SDC 82/1774 PASS; UI Skins 134 PASS; php -l, inventário e `git diff --check` PASS. Token-budget gate global tem 7 falhas preexistentes.
- **Pendências:** revisar delta de `core.extension`, alinhar Picker/Popover e validar formulários/acessibilidade.
- **Próximo passo:** revisar delta antes de novo `drush cim`.

# ADR-054/055 — Piloto Views row/style/display — 2026-11-10

- **Objetivo:** integrar row/style/display na View Álbuns sem perder funções nativas.
- **Estado:** `aculta_albuns:page_1` usa `revista-album-card`, `layout-grid`, `views-display`; 3 resultados, 200 e acesso anônimo antes/depois. Firefox 1280/320 claro/escuro: 3 cartões, sem overflow ou alvo <44 px.
- **Decisão:** sources preservam field handlers; display SDC declara header, filtros, rows, empty, pager, more, footer e anexos.
- **Cuidados:** pager multi-página (só 3 resultados), cache-hit, teclado e zoom DEFERRED. Suíte transversal parou no probe independente da Flag; checks focados de View passaram.
- **Commits/arquivos:** base `9ef4ad36e`; config View, dois SDCs, gate, runner, inventário, ADRs, P8/plano, TODO/status e capturas.
- **Verificações:** contrato 40; SDC 82/1774; UI Library 25; Foundations 83 pares; browser/Runtime PASS no escopo focal.
- **Pendências:** demais Views/domínios, P8/P9, Page/Entity Status, Icons e RN-00–05.
- **Próximo passo:** migrar a próxima View com comparação de render, acesso e cache.



# UI Skins — configuração e piloto Runtime — 2026-11-11

- **Estado:** UI Skins 1.2.1, mapa Domain ID → skin e cinco grupos de CSS variables estão ativos; `aculta_purpose_palette` permanece removida sem alias/cópia.
- **Evidência:** A/B/A anônimo confirmou Links=Ruderalis e outros domínios testados=Skunk, sem carryover. `ui_styles_ckeditor5` e `ui_patterns_views` também ativos no Runtime.
- **Arquivos/verificações:** `config/sync`, Portal/tema, ADRs, gate UI Skins; A/B/A documentado no inventário Runtime. Cutover autorizado e importado após snapshot.
- **Pendências:** contraste e aceite visual dos dez domínios, breakpoints, zoom, cache-hit; ADRs continuam abertas. Próximo: completar Views e homologação ampla.

