# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# Fórum — Comentários Compartilhados 0.2.0-dev.3 (Conclusão e Homologação Runtime) — 2026-11-08

- **Objetivo:** implementar o novo compositor inline Form API + HTMX (C02-03 a C02-08), unificar fluxos de resposta e citação na mesma página, fechar achados COM-01 a COM-12 e validar no Runtime Homelab.
- **Estado:** C02-03 a C02-08 concluídos e validados no Runtime com autorização do responsável. Implementados `DiscussionReplyAccess`, `DiscussionReplyUrlBuilder`, `DiscussionReplyFormBuilder`, `DiscussionReplyLazyBuilder`, `DiscussionReplyIdempotencyManager`, rota interna em português `/discussoes/compositor/{entity_type}/{entity_id}`, componente SDC `discussion-reply-composer` no tema V2 e hooks para supressão de redirects externos.
- **Decisões:** fechados COM-01, COM-05 e COM-12 mantendo navegação in-place via HTMX nativo do Core com fallback seguro na mesma página via parâmetros em português. Idempotência por hash de intenção com janela de 30 segundos no FormState.
- **Arquivos:** `DiscussionReplyAccess.php`, `DiscussionReplyUrlBuilder.php`, `DiscussionReplyFormBuilder.php`, `DiscussionReplyLazyBuilder.php`, `DiscussionReplyIdempotencyManager.php`, `DiscussionReplyController.php`, `DiscussionReplyEndpointAccess.php`, `DiscussionReplyFormHooks.php`, `DiscussionReplyActionsHooks.php`, `discussion-reply-composer` (SDC component, CSS, Twig, story), templates de comentário e testes unitários.
- **Verificações:** PHPUnit isolado 38/38 PASS (154 assertions); Kernel `ForumPostThreadHooksKernelTest` PASS (2/2); `validate-project-status-index.php --write` PASS (41 componentes); `validate-public-slugs.php` PASS (415 rotas); `drush cr` PASS no Homelab; renderização funcional no Runtime PASS (`discussion-reply-composer`, `data-hx-post`, biblioteca `core/drupal.htmx`).
- **Runtime:** Homelab ativo e validado; caches reconstruídos com sucesso via `drush cr`.
- **Próximo passo:** monitorar primeiras interações da comunidade e seguir roadmap de componentes.

# Fórum — Comentários Compartilhados 0.2.0-dev.2 (Saneamento Agente 1) — 2026-11-08

- **Objetivo:** executar saneamento da base de comentários antes do novo compositor inline (C02-01 e C02-02, achados COM-02/03/07/08/09/10/11) e preparar entrega para o Agente 2.
- **Estado:** C02-01 e C02-02 concluídos. `WikiDiscussionContextProvider` migrado e registrado em `aculta_forum` sem dependência reversa; contrato neutro de ações `#links['comment-quote']` em conformidade com o Drupal Core; atributos `id`, classes, autoria SDC sem leitura do User no Twig; permalinks sob D-65 apontando ao host canônico com pager 0 e `#comment-{cid}`; remoção de injeção insegura no cliente e escopo global em `forum-quote.js`.
- **Decisões:** COM-01, COM-05 e eliminação de redirects em COM-12 dependem da criação do compositor inline e foram atribuídos explicitamente ao Agente 2 (C02-03 a C02-08). Idempotência auditada no fluxo nativo (não possui deduplicação; recomendada inclusão de token no compositor inline em C02-07).
- **Arquivos:** `WikiDiscussionContextProvider.php`, `DiscussionCommentPermalinkBuilder.php`, `DiscussionCommentPermalinkHooks.php`, `ForumQuoteHooks.php`, templates `comment--field-aculta-discussion` e `comment--comments`, `forum-quote.js`, `forum-post-card.css`, planos e testes unitários.
- **Verificações:** PHPUnit isolado 32/32 PASS (123 assertions); `validate-project-status-index.php --write` PASS (41 componentes); SDC schemas e classes mapeadas validadas.
- **Runtime:** Homelab inacessível localmente para `drush cr`; status registrado como `DEFERRED`.
- **Próximo passo:** Agente 2 inicia C02-03 (experimento Form API + HTMX para resposta inline na mesma página) e fecha COM-01, COM-05 e COM-12.

# ADR-054 — P0/P2/P3/P4/P6/P7 executados; P5/P8/P9 parciais — 2026-11-09

- **Objetivo:** executar o plano ADR-054 de refatoração SDC First, Layout Builder, CSS multidomínio e Biblioteca UI.
- **Estado:** P0 inventário; P2 painel de container; P3 shell v2 opt-in com cinco áreas; P4 cinco layouts SDC e Layout Builder em **22 dos 24 displays compatíveis** (`allow_custom=false`, 170 checks, duas exclusões documentadas); P5 landing Links composta com `layout-section` e mecanismo de override por nó pronto (`page.full` com `allow_custom`); P6 `aculta_ui_library` com prévias reais administrativas; P7 oito folhas de aplicação contextuais por rota (`global-styling` 268 → 107 KB, −60%). P9 (cutover) não executado.
- **Arquivos/commits:** `66f6e0440`, `e990a8edc`, `0f48b901b`, `9b3d576c6`, `2b62e067b`, `cb9e463b2`, `887ff53c3`, `5da6f783d`, `6ad0bb482`, `f534cfbbe`, `030e92abe`, `ac6e99e67`. Relatórios em `docs/architecture/ACULTA420-V2-P0-INVENTARIO.md`, `ACULTA420-V2-P5-LANDING-PAGES.md` e `ACULTA420-V2-P8-HOMOLOGACAO.md`.
- **Verificações:** gates do tema, layouts, UI Library, CSS e lote LB PASS; falhas de fundações, classes mapeadas, boundary, Drupal 12 e token budget são pré-existentes. Playwright Firefox: 86 checks, 0 falhas (anônimo e autenticado, cinco larguras, escuro, foco); home/wiki/revista com 1 H1 e 1 `<main>`; rollback `classic` testado. Config ativa em `classic`/`light`.
- **Drift corrigido:** `main_menu`, `page_title`, `site_branding`, `help` e `breadcrumbs` estavam no Sync e ausentes da config ativa; restaurados. `institution_bar` segue só na config ativa.
- **Pendências:** conteúdo editorial da landing Links (não existe nó de landing); dois displays excluídos do LB (`document.full` e `basic.default`); falta o nó editorial da landing Links; aceitação visual humana; import/export em staging; `lint:css` sem binário; cache cross-domain em ordem inversa.
- **Próximo passo:** definir a landing Links com o responsável e avaliar os seis displays excluídos com adaptador próprio.

