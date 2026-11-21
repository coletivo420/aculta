> **PARIDADE VISUAL - 21/11/2026:** os componentes recentes de enquetes, histórico e leitura passaram a compor o SDC `surface`; resultados, rascunhos e estados utilizam os SDCs `icon`/`badge`. A `content-reply-bar` e a denúncia Phosphor compartilham alvos de 44 px e botão circular. Acrescentadas 13 stories de estados e 4 novos SDCs visuais (`forum-solution-state`, `forum-tracking-level`, `forum-assignment-state`, `forum-topic-vote-summary`), com 10 stories adicionais. São **somente apresentação**, sem provider de Solved, preferências, Assign ou Topic Voting. Auditoria e pendências em `SDC-PARIDADE-VISUAL-DISCOURSE-2026-11-21.md`. Prévia Runtime/browser permanece DEFERRED.

> **PARIDADE CGU/UI - 21/11/2026:** SDC reutilizável `cgu-report-button` com Phosphor `flag`, popup já existente, story isolada, story de fallback e story composta em `content-reply-bar`. Novo formulário CGU controla motivo, descrição, confirmação, autorização, duplicidade e CSRF. Apresentação de denúncias em tópicos e respostas, e demais superfícies CGU renderizadas, não implica que todas as integrações e Browser estejam homologados. [Inventário atualizado](../../aculta_cgu/docs/CGU-DENUNCIA-COMPONENTE-TRANSVERSAL-2026-11-21.md). 

> **ATUALIZAÇÃO 21/11/2026 - FDC-SDC-TL/POLL:** o construtor de enquetes agora possui SDC `forum-poll-builder` usado após Form API processar os controles; `forum-reading-timeline` consome History e `ForumPostUnreadManager` para links e contagens de tópico público. O percentual é baseado em recibos, não rolagem real. Ver [auditoria de SDC e históricos](SDC-HISTORICOS-PARIDADE-DISCOURSE-2026-11-21.md). Sem homologação Browser/Kernel até executar os gates.

> **INVENTÁRIO SDC E HISTÓRICOS - 21/11/2026:** revisão dos 42+ componentes de comunidade e stories, do histórico revisionável de ForumPost e da enquete nativa. Implementado o pacote de 5 SDCs: `forum-poll-card`, `forum-poll-results`, `forum-revision-history`, `forum-reading-timeline` e `forum-draft-status`. Integrações reais para os três primeiros, com acesso mantido pelos owners; os dois últimos são contratos visuais sem estado persistido. Matriz de lacunas, gates e limites: [SDC-HISTORICOS-PARIDADE-DISCOURSE-2026-11-21.md](SDC-HISTORICOS-PARIDADE-DISCOURSE-2026-11-21.md). Homologação em Runtime permanece pendente.

> **COMPLEMENTO DE PARIDADE 2026-11-20:** painel de participação `curtidas dadas/recebidas`, `tópicos criados`, `tópicos distintos abertos`, `respostas publicadas` e troféus Discourse via `aculta_game`/`aculta_user`/`aculta_statistics`, sem rankings; novas experiências Solved, Assign, Topic Voting separado de enquete e bookmarks/reminders. Projeções SDC/UI Patterns em perfil User e Conta/Studio, usando owner real. [Estudo e FDC-R9..R13](ESTUDO-DISCOURSE-GAME-METRICAS-INTEGRACOES-2026-11-20.md).

> **ADENDO 2026-11-20:** paridade inclui `Adicionar enquete` no popup `discussion-topic-composer`, SDCs para builder/cartão/resultados, moderação comunitária por popups no frontend, interfaces operacionais Wiki/Revista/LMS e demais suítes no frontend, backend exclusivo a admins. Dados de enquete nativos em `aculta_forum`, séries em `aculta_statistics`; moderação pedagógica pertence ao LMS. [Plano executivo atualizado](PLANO-PARIDADE-DISCOURSE-FRONTEND-MODERADOR-ENQUETES-2026-11-20.md). A tabela histórica abaixo não representa conclusão de testes.

> **POLÍTICA VIGENTE (2026-11-19):** [acervo histórico Growroom](POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md). Comentários históricos **não são comentários padrão** e **não serão migrados** para ForumPost. Coexistência técnica não autoriza conversão, mesclagem, recontagem como atividade corrente ou moderação pela fila comum. A separação física requer inventário e homologação; alterações desta PR são apenas guardas iniciais.

# Política ACULTA Forum: clone funcional Drupal do Discourse

Decisão de produto aprovada em 2026-11-19. **Paridade funcional e de experiência com Discourse**, implementação nativa sobre Drupal 11/12, identidade ACULTA e UI Suite/SDC como arquitetura visual obrigatória. O `aculta_forum` é o **motor único de discussões e comentários de todos os módulos do site**. Isso **não** significa transformar qualquer comentário editorial, pedagógico ou restrito em tópico público; cada host preserva entidade, acesso, workflow e propósito. A moderação de denúncias e os casos usam `aculta_cgu` como dono, com adapters de ações por contexto.

## Matriz Discourse -> SDC/UI Patterns/consumers

**Regra:** componente de catálogo ≠ display ativo ≠ recurso funcional. Para cada linha medir (1) componente e schema, (2) story real, (3) consumer PHP/Twig/Views, (4) configuração install/sync, (5) Runtime e browser, (6) acesso/cache/RTL/teclado.

| Experiência Discourse | SDCs já existentes no aculta420_v2 | Integração exigida / evolução |
| --- | --- | --- |
| Lista Latest/Top/New/Unread | `forum-list-page`, `forum-topic-row`, `forum-tabs`, `forum-topic-meta` | Views público/não lidos via UI Patterns Views; tabs por rotas, indicadores sem contar privados; adicionar estados Top/New/unanswered com semântica |
| Categorias e subcategorias | `forum-category`, `forum-category-header`, `forum-category-page` | Taxonomy/Views row UI Patterns; detectar displays antigos `fields`; hierarquia e descrição; filtros access-aware |
| Tópico e timeline | `forum-topic`, `forum-topic-page`, `forum-opening-post`, `forum-post-card`, `pagination` | Slots de abertura, lista, timeline, progresso, pager; manter paginação servidor e acesso, adicionar carregamento progressivo acessível |
| Comentário contextual | `forum-comment`, `forum-reply-compact`, `forum-reply-mirror`, `reply-indent`, `content-reply-bar` | Mesmo contrato de render para Wiki/Revista/LMS; preserve article attributes, DOM IDs, thread, moderado, cache e card de autor; sem fixar layout CSS no PHP |
| Composer de tópico/resposta/citação | `discussion-topic-composer`, `discussion-content-composer`, `discussion-reply-composer`, `forum-quote`, `forum-composer-guidelines` | Form API com HTMX sem redirecionar, feedback, preview, errors, draft, escape, fallback sem JS, parent/host validados |
| Reação e engajamento | `discussion-reaction-bar`, `discussion-reactions-received`, `reaction-emoji`, `author-card`, `forum-author-line` | UI Icons Picker/Phosphor, evento FST com idempotência e política alvo/contexto; resolver cache por viewer |
| Sidebar/navegação | `forum-sidebar-panels`, `forum-search-page`, `forum-topic-card`, `navigation-bar`, `tabs` | UI Patterns Layouts/Layout Builder com filtros por contexto e sem menus redundantes; placements versionados |
| Estados/tarefas da moderação | `comment-admin-controls`, `comment-admin-trigger`, `badge`, `data-table`, `empty-state` | Exibir CGU review queue via dados neutros, privacidade do denunciante, casos/ações/histórico e opções por ACL; CGU é dono do estado |
| UI Skins/UI Styles | `surface`, `surface-accent`, `surface-panel`, `layout-page`, `layout-section`, `layout-grid`, `button` | Reusar tokens e skin semântica; left/right/mirror para posts e citações, dark/light, acessibilidade, responsividade |

### Componentes novos somente se lacuna for demonstrada

- `forum-reading-timeline`: saltar para primeiro não lido, teclado e fallback com pager.
- `forum-draft-status`: salvo, editando, erro, retomar, sem armazenar material privado sem retenção.
- `forum-tracking-level`: observar/rastrear/normal/silenciar; consentimento e Messenger.
- `forum-mention-suggestions`: sugestões autorizadas, sem listar usuários privados de outros contexts.
- `forum-review-case` e `forum-review-timeline` (ou variantes genéricas `cgu-*` no tema): reuso pela moderação CGU de todos os contexts, não widgets de armazenamento do Fórum.
- `forum-topic-admin-menu`: fechar/reabrir/fixar/mover/split/merge somente ações autorizadas e reversíveis conforme política.

### Discourse como política de experiência

Paridade de fluxos, não pixel-perfect: lista, categoria, tópico, timeline, composer contextual, citação por seleção, rascunho com recuperação, tags, bookmarks, acompanhamento, notificações, pesquisa e review queue. Interfaces devem usar identidade visual ACULTA e componentes coerentes com demais suítes. Conferir variações reais do Discourse e estado opcional/experimental antes de replicar.

### Gates UI Suite obrigatórios

1. `enforce_prop_schemas: true` respeitado; manifests com `additionalProperties: false`, props tipadas, slots testados, stories com dados sintéticos, sem identidades reais.
2. SDC render não decide ACL nem faz EntityQuery; render arrays de serviços carregam `#cache` por contexto/usuário/Domain/Group e evitam vazamentos.
3. Validar `ui_patterns_views` nos displays ativos e `ui_patterns_layouts`/Field Group na configuração efetiva, não apenas no `config/install`; não remover Twig adapter que preserve campos estruturais enquanto a substituição não estiver homologada.
4. CSS pelos tokens `ui_skins` e `ui_styles`; evitar verde escuro em superfícies escuras sem contraste; modos claro/escuro com mesma geometria; responsividade 320/390/768/1024/1440.
5. Tests: schema validation, render SDC, UI Patterns Library, claro/escuro, contraste WCAG 2.2 AA, zoom 200%/400%, teclado, leitor de tela, leitor de RSS e sem JS quando aplicável.
6. Inspecionar versão instalada/corrigida do UI Patterns frente ao advisory https://www.drupal.org/sa-contrib-2026-075; não pressupor correção pelo nome da branch.
7. Não instalar `UI Patterns CKEditor 5` nesta fase. `UI Patterns Field Group` prefere dev se tecnicamente compatível com APIs instaladas e decisão ADR-055.

### Inventário de corte: prioridade por consumer

- Primeiro: `views.view.aculta_forum_public_topics`, `views.view.aculta_forum_categories`, `views.view.aculta_forum_unread_topics`, `views.view.aculta_forum_search`; rows, exposed forms, empty-state, pager e sidebar.
- Segundo: view modes de Node topic, `aculta_forum_post` e Comment, renderização de respostas de Revista/Wiki/LMS e formulários do composer.
- Terceiro: formulários e filas CGU com components/admin layouts SDC e UI Patterns, preservando MAIN.
- Quarto: estados avançados de Discourse, preferências pessoais, timelines e drafts, todos com stories antes da ativação.

Refs: https://www.drupal.org/project/ui_patterns ; https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/ui-patterns/use-patterns-with-views ; https://www.drupal.org/project/ui_patterns_field_group .
