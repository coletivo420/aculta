> **STORIES MARLEY / DISCOURSE (2026-11-20):** O SDC `aculta420_v2:studio-marley-workspace` e Stories de Review Queue, análise CGU, moderação tópico/resposta, Revista e Galerias já estão publicados na PR #394. [Handoff dos Stories/aceite SM-01..08](../../aculta_studio/docs/STORIES-MARLEY-DISCOURSE-2026-11-20.md). Fazer integração funcional real via `aculta_studio`, `aculta_cgu` e `aculta_forum` com ACL/POST/CSRF/HTMX. Não substituir testes reais por previews; não deixar a fila apontar ao backend. Prosseguir com toda a implementação do Fórum, não apenas com UI.

> **FDC-R14 incluído (2026-11-20):** [Sincronização automática de curtidas por mensagem e por usuário](PLANO-REACOES-SINCRONIZADAS-BLOCOS-2026-11-20.md) com testes T-62..70. Um clique no brócolis/descurtida/troca/Undo atualiza a mensagem e os cartões/perfil do usuário visíveis **na mesma página**, via HTML autoritativo do POST HTMX, sem segundo GET. Inclui cabeçalhos, emojis e cartões normal/espelhado; respetar cache tags, ACL, perfis e histórico. Implementação inicial remota, aceites Runtime/Browser DEFERRED. **Não restringir o agente a curtidas: concluir todo o Fórum FDC-R0..R14.**

# Handoff do agente CLI - conclusão de TODO o ACULTA Fórum / clone Discourse

**Data:** 2026-11-20.
**Repositório:** https://github.com/coletivo420/aculta.
**Branch de trabalho:** `feat/forum-paridade-discourse`, PR #394.
**Código novo deste ciclo:** `2eb08c16af9848424daa7c4d9dca831ee36f56ff` (proteção same-origin no POST sem JS, brócolis fixo no clique rápido, tests Unit/Kernel escritos, ainda sem execução no Homelab).
**Natureza:** prompt operacional integral; os marcos de implementação não são homologação.

Este documento SUBSTITUI o handoff limitado a descurtidas como instrução principal. O último pedido exige TODO o Fórum: Discourse Drupal-nativo, moderação frontend Studio, backend só administrator, Wiki, LMS, Revista, CGU, Index, Statistics, Game, Messenger, polls, segurança, UI Suite e gates. Reações permanecem uma das frentes.

## Prompt pronto para o agente local

```text
Você é um agente de implementação local no Homelab ACULTA, trabalhando no repositório coletivo420/aculta. Esta tarefa é a CONCLUSÃO INTEGRAL DO ACULTA FÓRUM como clone funcional Drupal do Discourse e sua integração transversal, NÃO apenas o sistema de reações/descurtidas. Faça auditoria pelo código real, implemente em ciclos executáveis, rode testes, corrija falhas, registre evidências e publique commits. Não trate documentação como prova de implementação.

REPOSITÓRIO: https://github.com/coletivo420/aculta
BRANCH/PR DESTA ENTREGA: feat/forum-paridade-discourse / PR #394
SNAPSHOT INICIAL A VERIFICAR: 2eb08c16af9848424daa7c4d9dca831ee36f56ff (ou novo HEAD posterior)
FONTES NORMATIVAS: AGENTS.md da raiz, do aculta_portal, do aculta_forum, aculta420_v2, aculta_studio e dos módulos tocados; ADR-054/055/056/057; docs de arquitetura, segurança, Runtime e gates. Ler as skills nativas aculta-runtime-sync, sdc, ui-patterns, phpstan e testes pertinentes. Revalidar tudo no checkout atual. PRIORIDADE da decisão explícita do responsável sobre planos antigos quando houver conflito.

REGRAS ABSOLUTAS DE GIT E SEGURANÇA:
- O checkout principal no Apache/Homelab PERMANECE em dev. NÃO trocar a branch desse checkout. Crie ou reutilize WORKTREE ISOLADA baseada em origin/feat/forum-paridade-discourse, em diretório separado. Trabalhe exclusivamente nela.
- Verifique git status, git fetch, commit base e concorrência com outros agentes. Não resetar, stash, clean nem sobrescrever alterações alheias. Não force push. Commits atômicos em português para a PR #394; git push origin feat/forum-paridade-discourse. Não mesclar em dev/main nem sincronizar Runtime compartilhado sem pedido específico; seguir política em AGENTS.md.
- Kernel/Functional e testes de DB usam banco de teste isolado, jamais aculta_runtime. NUNCA executar config:import/updatedb/composer estrutural/uninstall/exclusão em Runtime ou produção sem plano, backup e autorização operacional. Só o usuário pode autorizar tais operações.
- NÃO alegar testes PASS sem executá-los; registrar PASS/FAIL/DEFERRED e logs reproduzíveis. Segurança, isolamento de Domain/Group e dados históricos têm prioridade máxima.

DECISÕES DE PRODUTO VINCULANTES:
1. Clone do Discourse deve ter PARIDADE FUNCIONAL E UX no Drupal, não cópia de Ruby/Ember nem só aparência. Todos os comentários NOVOS da plataforma usam o motor próprio aculta_forum como serviço transversal de tópicos/respostas/Comment contextual, inclusive Wiki, Revista, LMS e módulos consumidores. Comentários históricos Growroom em aculta_forum_legacy são ARQUIVO separado, read-only e nunca convertidos para ForumPost ou misturados a reações, Game, Index público corrente ou CGU.
2. SÓ administrator acessa qualquer backend Drupal institucional ou rota administrativa (inclusive /admin/**, /painel-administrativo/** e endpoints equivalentes). Novo papel moderador abaixo de content_editor só recebe permissões de moderação comunitária fina, SEM backend, edição editorial, LMS pedagógico ou herança por peso. content_editor e professores também SEM backend. Administração continua funcional para administrator.
3. Todas as interfaces operacionais NÃO ADMIN são FRONTEND do aculta_studio, nas suites existentes: Marley (inclui aba Fórum + fila CGU), WE Sativa (Wiki), GanjaLab (LMS/pedagogia), Green Stash (loja), Culturas (eventos), Conta, etc. Não criar novo hub /studio nem painel paralelo. Domínios/paths canônicos preservados. Regra e autorização pertencem aos módulos owners; SDC/UI Patterns são do aculta420_v2; Studio compõe UI. Popups operam dentro da página frontend, inclusive edição/validação/confirmação/feedback; fallback sem JS não envia usuário ao backend.
4. CGU (aculta_cgu) é owner de denúncias/moderação global comunitária, com decisão independente da ação editorial. Moderador acessa fila do Fórum via Studio Marley e popups; Educador modera pedagogicamente apenas seus próprios cursos/Group no aculta_lms. Revisar ForumTab.php que ainda contém link a aculta_user.denuncias_forum backend. Nunca converter poder comunitário em poder pedagógico.
5. Novo composer de tópico com opção 'Adicionar enquete' no próprio popup: entidades/tabelas próprias revisionáveis quando justificadas, pergunta e 2..20 opções, escolha única/múltipla, voto único lógico por UID, estado/fechamento, resultados acessíveis, locks, auditoria e integração aculta_statistics. Sem shortcode como fonte ou dependência obrigatória externa. Topic Voting é funcionalidade separada da enquete.
6. Reações: O BOTÃO CURTIR RÁPIDO PADRÃO É SEMPRE O BRÓCOLIS 🥦 (id 'broccoli'); 1 voto por UID/alvo; qualquer emoji positivo antigo e atual conta +1; descurtir (id 'dislike') conta -1 no saldo max(0, positivos-negativos); usa o SVG Fluent Emoji **thumbs-down-medium 👎🏽** em BOTÃO PRÓPRIO fora do popin. O popin mostra APENAS emojis positivos + ação DESFAZER/UNDO (ícone de seta), NUNCA a mão para baixo nem descurtir como opção. Undo é comando 'undo', NÃO é reação persistida: remove a reação ativa (inclusive negativa); no-op com ausência de voto. Segundo clique no emoji/reação ativa remove voto. Trocar emoji positivo, brócolis ou descurtida atualiza o ÚNICO registro; sem apagar as curtidas alheias ou histórico. Positivo→negativo altera saldo bruto -2 e vice-versa +2. Exclusão do histórico/Archive e privacidade obrigatórias.
7. aculta_game + aculta_user: conquistas/troféus inspirados nos Discourse (First Like/Welcome, Nice/Good/Great Topic e Reply 10/25/50 usando SCORE DO MESMO ITEM, outras metas auditáveis), award idempotente, sem XP, ranking, níveis, ganho de papel/privilégio ou recompensa financeira. Separar tópicos criados, visitados, respostas publicadas, likes recebidos/dados, dislikes, leitura real e dados de revistas/LMS.
8. aculta_statistics: dono de métricas, ledger, snapshots, reconciliação/dry-run; aculta_index para índices públicos/contextuais access-aware; aculta_messenger para avisos opt-in/dedup; aculta_cgu para moderação; aculta_studio só apresenta. Evitar novas tabelas redundantes, vazamento de UID público e ciclos obrigatórios entre módulos.

DOCUMENTOS QUE DEVEM SER LIDOS NO INÍCIO (NÃO CONFIAR SÓ NELES):
- web/modules/custom/aculta_portal/modules/aculta_forum/docs/PLANO-PARIDADE-DISCOURSE-FRONTEND-MODERADOR-ENQUETES-2026-11-20.md (FDC-R0..8)
- .../aculta_forum/docs/ESTUDO-DISCOURSE-GAME-METRICAS-INTEGRACOES-2026-11-20.md (FDC-R9..13)
- .../aculta_forum/docs/MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md (T-01..57)
- .../aculta_forum/docs/MATRIZ-PARIDADE-DISCOURSE-UI-PATTERNS-2026-11-19.md
- .../aculta_forum/docs/IMPLEMENTACAO-DESCURTIDAS-2026-11-20.md (DV-01..19, patch de reações)
- .../aculta_forum/docs/POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md
- .../aculta_studio/docs/PLANO-FRONTEND-OPERACIONAL-2026-11-20.md
- .../aculta_statistics/docs/FORUM-ANALYTICS-PLANO-FST-00-11.md; aculta_game/docs/ROADMAP.md; docs/portal/FRONTEND-OPERACIONAL-BACKEND-EXCLUSIVO-ADMIN-2026-11-20.md.
- Inspecione de verdade src/Form, Entity, Controller, Access, Hook, Service, Event, Subscriber, SDC/Twig/CSS, routes, permissions, Config Sync, composer.lock, testes, logs, DB test e workflows GitHub.

EXECUÇÃO SEQUENCIAL OBRIGATÓRIA, CONTINUAR ENTRE ETAPAS:
F0 - PREFLIGHT: abrir worktree isolada na branch, confrontar commits recentes (incluindo dcbfa3e8 Undo e 2eb08c16 segurança), verificar CI, php -l, Twig lint, YAML SDC, phpunit unit/Kernel, PHPCS, PHPStan nível10 com baseline congelada, Stylelint e outros gates reais. CORRIGIR primeiro regressões introduzidas em reaction/redirect/default. Confirmar class availability LocalRedirectResponse, ReactionReturnUrl URI, cenários de redirects. Não mudar dev Runtime.
F1 - ACL ADMIN-ONLY: auditar todos user.role.* e routes custom/contrib/Core, capabilities, entity access e Domain/Group; implementar regra backend apenas para administrator sem quebrar o frontend. Moderador separado com grants finos; remover permissões amplas das outras roles só depois de disponibilizar as tarefas em Studio. Testes adversariais papéis isolados/compostos, uid1 e admin, GET/POST/HTMX/API, cache quente.
F2 - STUDIO FRONTEND: mapear no aculta_studio todos os providers existentes; terminar Marley Fórum (fila CGU e serviços próprios), Wiki/WE Sativa, GanjaLab, Revista, Galeria, Eventos, Loja e conta. Não criar segundo framework ou copiar business rules para Studio. Corrigir ForumTab e links de fallback a backend. SDCs no tema com stories e UI Patterns Library.
F3 - DISCOURSE CORE: composer de tópico/respostas/citação/edição/preview/mention, threaded replies, Markdown/CKEditor permitido, tags preservadas no TopicPopupLayout, modais sem redirects, navegação categoria/topic/post e UX de leitura, notificações, bookmark, Top/New/Unread, busca, permalinks, timeline, drafts e mark-read genuíno. Resolver bug real de split/merge que hoje manipula Comments, embora publicações novas usem ForumPost revisionável; manter parent, histórico, URLs e métricas. Não usar ForumPost como comentário histórico.
F4 - CGU/MODERAÇÃO: implementar commands tipados (approve, reject, hide, unhide, close/open, pin, archive, move/split/merge, flag/review e assign) para Fórum em frontend e backend admin; Form API HTMX, CSRF, transações, locks, replay, auditoria, cancelamento, foco, moderação educacional isolada. Rejeição não equivale necessariamente à despublicação. Separar denúncia de decisão de alteração de conteúdo.
F5 - POLLS: botão Adicionar enquete no composer existente, Storage nativo com opção de única/múltipla, voto e controles de concorrência, divulgação de resultados e fechamento; transações com criação de tópico, no-op/retry seguros, sem expor eleitores. Integrar FST e SDC/UI Patterns.
F6 - REAÇÕES E CONTADORES: concluir DV-01..19, corrigir troca/idempotência, lock e estado de publicação, score item/autor, cache, performance. Padrão broccoli IMUTÁVEL; Emoji negativo pardo em botão próprio; popin positivos + Undo; sem duplicação. Corrigir Query/View Stats N+1 e tratar conteúdo oculto e legado. Confirme métricas net_current/dislikes_current/active e eventos positivos corretamente.
F7 - ESTATÍSTICAS/GAME/INDEX/MESSENGER: entregar FDC-R9..12 e FST12..15, profile counts separados, badges de 10/25/50 por item de forma auditável, sem XP/ranks/cargos, stats de enquetes/moderação, Search API/Index/Sitemap, digest e notificações, ACL revalidada. Compatibilidade de módulos opcionais e User publisher. Não informar no índice ou jogo contas/reações privadas.
F8 - PARIDADE DISCOURSE AVANÇADA: Solved (accepted answer), Assign, Topic Voting (NÃO Polls), slow mode, scheduled close, notas de moderação, tags, backlinks, citação de seleção, menção tokenizada por UID, bookmarks/lembretes e interfaces dos controles, na ordem P0/P1/P2 do estudo. Marcar conscientemente diferenças ACULTA; não implementar chat duplicado do Messenger.
F9 - QUALITY RELEASE: T-01..57, DV-01..19 e testes adicionais de Redirect/Default+Undo, Unit/Kernel/Functional/Browser/Playwright; includes 320/390/768/1024/1440, dark/light, teclado, 200/400% zoom, no-JS, HTMX e 100/1k/10k/100k posts (benchmarks e SLO). MariaDB com banco ISOLADO. Atualizar docs por evidência, snapshots/Config Sync e CHANGELOG. NÃO considerar release enquanto P0/P1 aberto ou fluxo frontend sem acesso. Seguir gates de CI e autorização Runtime.

TRABALHO ITERATIVO: em cada fase: (1) inventariar código verdadeiro e testes, (2) alterar o mínimo seguro com SDC/serviço próprio, (3) executar unit/kernel/functional/QA pertinentes, (4) corrigir, (5) documentar evidência + status e commit atômico, (6) push na branch feat/forum-paridade-discourse, (7) atualizar planilha/matriz de aceites e seguir a próxima fase. Priorizar ações P0, não declarar completo aquilo que só tem doc. Não pedir esclarecimentos repetidos; quando precisar de permissão para Runtime/estrutura, marcar BLOCKED e continuar com outras tarefas independentes na worktree.

ENTREGA FINAL: relatório com HEAD inicial/final, commits e links, arquivos modificados, capacidades Discourse atuais/pendentes por módulo, matriz T e DV com PASS/FAIL/DEFERRED+logs, riscos de segurança, Status Runtime, CI, próximos passos com comandos exatos. Não alegar que homologou o Runtime a partir do commit remoto.
```

## Gates de aceitação em destaque

O agente não pode encerrar por considerar apenas DV-01..19 concluídos. A finalização integral requer inspeção de TODAS as lacunas FDC-R0..R13 + T-01..57, e execução dos testes de segurança, paridade e integrações. Onde houver bloqueio real de autorização Runtime, registrar `DEFERRED/BLOCKED` sem improvisar mutação.

## Evidência e status inicial

- Código de reação e de segurança já publicado: `ReactionScore`, `DiscussionReactionManager`, `ReactionReturnUrl`, SDCs Fluent/UI, `DiscussionReactionController`, SettingsForm, métricas e testes ainda sem homologação local comprovada.
- Reação padrão brócolis; negative button mão parda (Fluent), popin positivos + Undo.
- Trabalho em branch PR, NÃO em dev Runtime, sem merge.
- Outras frentes são planos com implementação parcial/variável conforme o código; revalidar antes de cada marco.

