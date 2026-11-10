# UI Patterns 2 + UI Styles — Otimização Universal e Catálogo sob Demanda — 2026-11-10

- **Objetivo:** executar integralmente as microfases UP-00 a UP-07: correção P0 de segurança em aculta_ui_library (SEC-01), eliminação da invalidação de descoberta em preprocess (PERF-01), catálogo progressivo universal com HTMX (PERF-02), desacoplamento de stories executáveis (PERF-03), confronto upstream com UI Patterns 2.0.23, preservação de UI Styles/Icons e validação nos 10 purposes do ACULTA.
- **Estado:** microfases UP-00 a UP-07 concluídas e publicadas em `dev`. 100% dos SDCs (83 totais, 72 do tema V2) e 115 stories permanecem disponíveis. HTML do overview reduzido de ~3.2MB para ~649KB, pico de memória reduzido de ~206MB para ~146MB e chamadas recursivas a `component_story()` eliminadas no overview inicial. Zero formulários reais construídos desnecessariamente na listagem.
- **Decisões:** UI Patterns universal mantido (proibição absoluta de excluir ou ocultar componentes). Verificação estrita de `isAllowed()` no plugin de bloco, negando `forbidden` e `neutral`. Carregamento progressivo de prévias via HTMX (`data-hx-get` apontando para a rota single com `data-hx-select="#component-stories-wrapper"`), preservando fallback noscript e links permanentes por componente. Busca em tempo real por nome/ID via `ui-library-tabs.js`.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_ui_library/src/Controller/UiLibraryPreviewController.php`, `web/modules/custom/aculta_portal/modules/aculta_ui_library/tests/src/Unit/UiLibraryPreviewControllerTest.php`, `scripts/validate-aculta-ui-library.php`, `web/themes/custom/aculta420_v2/src/Hook/UiPatternsStoryHooks.php`, `web/themes/custom/aculta420_v2/templates/ui-patterns-overview-page.html.twig`, `web/themes/custom/aculta420_v2/templates/ui-patterns-single-page.html.twig`, `web/themes/custom/aculta420_v2/js/ui-library-tabs.js`, `web/themes/custom/aculta420_v2/src/styles/ui-library-admin.css`, `docs/architecture/ACULTA-UI-PATTERNS-OTIMIZACAO-UNIVERSAL.md`.
- **Verificações:** todos os gates do escopo PASS: `validate-aculta-ui-library.php` (25 checks), `validate-aculta420-sdc-schemas.php` (1623 checks), `validate-aculta420-v2-foundations.php` (contrastes e a11y PASS), `validate-admin-routes-main-domain.php` (175 rotas MAIN PASS), PHPUnit Unit aculta_ui_library (9/9 PASS, 30 assertions), benchmark medido antes/depois.
- **Runtime:** `drush cr` executado; catálogo universal rápido, seguro e progressivo.
- **Próximo passo:** homologação visual contínua dos próximos SDCs integrados ao Layout Builder e Views.

# ADR-055 — Integração UI Icons, UI Styles Page e Sistema Unificado de Emojis — 2026-11-10

- **Objetivo:** executar integralmente os pacotes P0 a P7 da ADR-055: unificação do catálogo Fluent Emoji, integração com UI Icons, seletor de reações com busca e acessibilidade, UI Styles Page e verificação dos icon packs.
- **Estado:** P0 a P7 concluídos e publicados. Filtro `fluent_emoji` refatorado para parsing seguro de nós de texto DOMXPath; serviço `FluentEmojiIconAdapter` implementado com mapeamento bidirecional e categorias; seletor de reações do fórum expandido com busca instantânea, seções e a11y por teclado; 3 icon packs validados com presença física dos SVGs; rotas de UI Styles Page e UI Library protegidas e funcionais.
- **Decisões:** ADR-055 aprovada e registrada em `docs/decisions/ADR-055-integracao-ui-icons-ui-styles-page-e-sistema-unificado-de-emojis.md`. Preservada separação estrita de camadas: módulo neutro em templates, tema conectando os SDCs.
- **Arquivos:** `web/modules/custom/aculta_portal/modules/aculta_editor/`, `web/modules/custom/aculta_portal/modules/aculta_forum/`, `web/themes/custom/aculta420_v2/`, `docs/decisions/ADR-055-integracao-ui-icons-ui-styles-page-e-sistema-unificado-de-emojis.md`.
- **Verificações:** 100% dos gates do escopo PASS: `validate-module-theme-boundary.php` (7339 checks), `validate-aculta420-sdc-schemas.php` (1623 checks), `validate-aculta-ui-library.php` (23 checks), `npm run lint:css` (0 erros), PHPUnit Unit/Kernel `aculta_editor` (43/43 PASS), PHPUnit Unit `aculta_forum` (241/241 PASS), PHPUnit Unit `aculta420_v2` (18/18 PASS).
- **Runtime:** `drush cr` executado; catálogo e adaptador operacionais.
- **Próximo passo:** homologação contínua de novos displays editoriais e adoção do Fluent Emoji em novos formatos de texto sob demanda.

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
