# FST - Plano consolidado Forum + Statistics - 2026-11-10

- **Objetivo:** consolidar auditoria estática e pesquisa Discourse/Flarum/XenForo em um plano executável FST-00 a FST-11.
- **Estado:** planejamento documentado; nenhum arquivo PHP, configuração, banco ou Runtime foi modificado. Prioridade: proteger reações e contagens públicas com Group, depois idempotência e reconciliação.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_statistics/docs/FORUM-ANALYTICS-PLANO-FST-00-11.md`, documentação de integração/roadmaps/README/TODO/status de ambos os módulos e este handoff.
- **Verificações:** inspeção por GitHub connector, checagem de correspondência dos três eventos com catálogo/serviços e leitura das referências externas; PHPUnit, Drupal e Runtime **não executados**.
- **Próximo passo:** revalidar HEAD/AGENTS e iniciar FST-00 (inventário read-only); executar FST-01 somente com testes isolados e plano de correção/rollback de agregados.

# SDCs de Superfície e Conteúdo — Moldura Tricolor Lateral Esquerda e Direita — 2026-11-10

- **Objetivo:** implementar a moldura tricolor na lateral esquerda (`position: start`, `accent: tricolor`) com base na imagem de referência oficial (`03_bloco_tricolor_esquerda.png`) e registrar story no UI Patterns, complementando a família de molduras unitárias e tricolores.
- **Estado:** implementado suporte para `.aculta-surface-accent--tricolor.aculta-surface-accent--pos-start .aculta-surface-accent__stripe` com faixa vertical tricolor idêntica à referência (vermelho no topo 16%, amarelo intermediário 18% e verde predominante 66%), com suporte a modo claro e escuro. Criada a story `surface-accent.tricolor-left.story.yml` na biblioteca UI Patterns. Validação visual real executada via Playwright em `/painel-administrativo/aparencia/ui/components` com captura `live-moldura-tricolor-na-lateral-esquerda.png`.
- **Decisões:** reutilização da geometria de faixa lateral com `flex: 0 0 1.25rem`, gradiente vertical idêntico nos dois lados (`pos-start` e `pos-end`), alinhando ambas as extremidades ao padrão das referências visuais do projeto.
- **Arquivos:** `web/themes/custom/aculta420_v2/components/surface-accent/surface-accent.css`, `surface-accent.tricolor-left.story.yml`, `docs/portal/studio-visual/referencias/sdc-superficies/03_bloco_tricolor_esquerda.png`.
- **Verificações:** SDC Schema PASS (72 components, 1623 checks), PHPUnit 18/18 PASS (123 assertions), CSS Scope PASS (43 checks), validação visual com Playwright PASS.
- **Runtime:** `drush cr` executado; exibição e renderização da moldura tricolor esquerda confirmadas no UI Patterns.
- **Próximo passo:** composição editorial nas landing pages do portal utilizando as novas superfícies.

# UI Suite — Integração Completa de UI Icons, UI Patterns e UI Styles — 2026-11-09

- **Objetivo:** integrar a suíte oficial da UI Suite (`drupal/ui_icons`, `drupal/ui_patterns`, `drupal/ui_styles`) ao tema `aculta420_v2` e módulos de suporte (`aculta_portal`, `aculta_editor`, `aculta_ui_library`), respeitando a ADR-054.
- **Estado:** Icon packs `aculta_phosphor_regular`, `aculta_phosphor_duotone` e `aculta_fluent_emoji` registrados nativamente via `aculta420_v2.icons.yml`; suporte estruturado a `$ref: 'ui-patterns://icon'` implementado no SDC `button` e template `aculta-portal-button`; `FluentEmojiAssetResolver` implementado em `aculta_editor` desacoplando regras de caminhos físicos; novas coleções de estilos semânticos de espaçamento e superfície adicionadas a `aculta420_v2.ui_styles.yml`; link de catálogo de ícones adicionado à `aculta_ui_library`.
- **Decisões:** uso do subsistema nativo de Icon Packs do Drupal Core 11.5 + UI Icons 2.1.0; render arrays nativos do tipo `#type => 'icon'` e suporte a render array em props de SDCs; preservação estrita de zero classes de apresentação em módulos (`validate-module-theme-boundary.php` 100% PASS).
- **Arquivos:** `aculta420_v2.icons.yml`, `aculta420_v2.ui_styles.yml`, `button.component.yml`, `button.twig`, `aculta-portal-button.html.twig`, `FluentEmojiAssetResolver.php`, `FluentEmojiAssetResolverInterface.php`, `FluentEmojiFilter.php`, `UiLibraryPreviewController.php`, `validate-aculta420-sdc-schemas.php`, `UiIconsPackTest.php`.
- **Verificações:** 100% dos gates e testes aprovados: `UiIconsPackTest` (20 assertions), `FluentEmojiFilterTest` (24 assertions), `validate-aculta420-sdc-schemas.php` (1547 checks), `validate-module-theme-boundary.php` (7329 checks), `validate-aculta-ui-library.php` (23 checks), `npm run lint:css` (0 erros), renderização runtime testada via Drush (`aculta_phosphor_regular`, `aculta_fluent_emoji`, `aculta_portal_button`).
- **Runtime:** caches reconstruídos via `drush cr`; icon packs e componentes renderizando com sucesso em runtime.
- **Próximo passo:** monitorar novas implementações de SDC e expansões de templates pelo time editorial.

