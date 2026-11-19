# Handoff para agente CLI - PR #377, primeira rodada do clone Discourse

Data: 2026-11-19. Branch de trabalho: `docs/forum-clone-discourse-ui-cgu-integracoes-20261119`, PR: https://github.com/coletivo420/aculta/pull/377, destino: `dev`. **Manter commits nessa branch**, sem force-push ou bypass do PHPStan level 10. Esta é uma entrega de código inicial, **não é homologação do Runtime**.

## Instrução principal

Você está continuando o clone funcional Drupal do Discourse nativo sobre `aculta_forum`, sistema único de comentários de Growroom corrente, Wiki, Revista, LMS e demais hosts registrados. **Não misture os Comments históricos do Growroom com comentários padrão nem os converta em ForumPost**. A política permanente está em [POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md](POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md); ela prevalece sobre qualquer documentação histórica de migração. Históricos ficam read-only, com identidade, links e estatísticas próprios. Novas publicações Growroom usam ForumPost; Wiki/Revista/LMS usam Comment contextual sem criar tópicos públicos paralelos.

## Passo 0 - Inspeção do checkout e dos commits

```bash
git fetch origin
git switch docs/forum-clone-discourse-ui-cgu-integracoes-20261119 || git switch --track -c docs/forum-clone-discourse-ui-cgu-integracoes-20261119 origin/docs/forum-clone-discourse-ui-cgu-integracoes-20261119
git status --short
git rev-parse HEAD
git log --oneline -12
git diff --stat origin/dev...HEAD
git diff --check origin/dev...HEAD
```

Não limpar/stash/resetar mudanças de outros agentes. Se a worktree compartilha alterações, selecionar apenas arquivos próprios para commit. Conferir `AGENTS.md`, instruções do Portal e submódulo, `docs/operations/GATES.md` e regras R2/R3 de revisão independente.

## Fase de validação imediata - auditar e consertar o que entrou na PR

Commits desta rodada criaram o controle de citação por host/ACL e exclusão histórica, resolveram `ReportService::resolvePurpose()` por host com fail-closed em LMS sem adapter, alteraram rotas GET para formulários de confirmação, adicionaram Unit/Kernel e atualizaram documentação. **Nenhum teste PHPUnit ou PHPStan foi executado neste ambiente**: executar agora, corrigir os novos FAILs e não marcar PASS sem registro.

```bash
git diff --check origin/dev...HEAD
php scripts/phpunit-suites.php --check
vendor/bin/phpunit --filter 'DiscussionReplyControllerQuoteTest|DiscussionReplyAccessTest|ForumQuoteHooksTest|TopicDeletionRoutesTest'
vendor/bin/phpunit --filter 'CguReportPolicyKernelTest|ForumCguIntegrationKernelTest'
php scripts/validate-aculta-portal-drupal12.php
php scripts/validate-admin-routes-main-domain.php
python3 scripts/validate-agent-skills.py
php scripts/validate-agent-token-budget.php
vendor/bin/phpstan analyse
php scripts/phpstan-debt-guard.php check
php scripts/phpstan-debt-guard.php diff
```

Usar MariaDB **de testes**, não `aculta_runtime`, para Kernel; gates Drupal/Runtime conforme `docs/operations/GATES.md`. Rodar `php -l` nos PHP alterados e PHPCS do escopo. Há proteção do check `PHPStan level 10 gate` na `dev`. Se o job falhar, corrigir na PR; não contornar baseline/checks ou fingir PASS.

### Contratos a provar com prioridade P0

1. **Citação cruzada:** `DiscussionReplyController`, `ForumQuoteHooks`, `DiscussionReplyAccess`, `DiscussionReplyFormHooks` e form endpoint devem negar fontes de outro host, com ACL revogada, tombstone, contexto LMS restrito, wiki não publicada, campos/bundles diferentes e acervo histórico. Exigir teste Functional HTTP/HTMX autenticado além dos Unit.
2. **GET/HEAD não destrutivo:** `TopicDeletionDecisionForm` deve apenas apresentar confirmação em GET/HEAD e só executar em submit POST com CSRF da Form API. Testar GET/HEAD, POST inválido, token, perfil insuficiente, id ausente, dupla requisição, resultado efetivo e retorno de formulário. Revisar transação/idempotência em `TopicDeletionModerationManager` antes de aplicar exclusão real.
3. **CGU purpose real:** `ReportService` deve resolver Comment pelo host, não pelo bundle `comment`; Revista=revista, Wiki=wiki, Growroom corrente=forum; LMS/feedback Group ficam fail-closed até adapter Educador/Group, sem autorização global por conveniência. Validar fila/flagging e escopo de permissões.
4. **Separação histórica:** `field_autor_legado`/`legacy_author` e proveniência devem identificar o arquivo, sem permitir reply/edit/quote/reaction/flag em rotas alternativas, busca/caches, formulários administrativos e GraphQL/API se houver. Arquivo não vira Comment padrão ou ForumPost.

## Próximos pacotes de implementação

**P0 FDC-00 / classificador:** inventário completo de entidades, campos, hosts, relações, Views e endpoints; criar `ForumDiscussionOriginPolicy` (ou nome compatível) que classifica `historic_archive`, `forum_live`, `contextual_comment`, `unknown`. Não depender apenas de um marcador incompleto: identificar legado sem marker por proveniência e testar. Unknown falha fechado. Não fazer migração física sem autorização.

**P0 FDC-02 / motor universal:** revisar `DiscussionReplyFormBuilder`, que ainda cria Comment para qualquer host; novas respostas `aculta_forum_topic` devem passar a `ForumPostSubmissionForm` com parent ForumPost, workflow e URL no tópico. Arquivo histórico permanece read-only, sem converter registros. Wiki e Revista mantêm Comment contextual Core e seus field access; LMS tópicos preservam Group/curso/turma/Educador, feedback `aculta_lms_answer_comment` mantém semântica avaliativa. O compositor não pode redirecionar a pessoa para outro site; manter HTMX + fallback sem JavaScript com Form API.

**P0/P1 FDC-04 / CGU:** `aculta_cgu` é dono de denúncia/caso/decisão/ação. Criar adapter de alvo e autorização por host, evitar duplicação da fila Flagging, separar denúncia julgada de ação efetivamente aplicada; preservar trilha excepcional de acervo e moderação contextual LMS/Revista/Wiki. Testes de privacidade do denunciante e cases/replay.

**P1 FDC-05 / Index e Search API:** revisar `ForumContentIndexProvider` e todas as projeções para diferenciar acervo/novo/contextual. Não retirar acervo da pesquisa sem interface histórica substituta; não levar LMS/Group privados a índice público; contratos de relation/access devem falhar fechados. Não aumentar `projection version` nem rodar rebuild sem preflight e autorização.

**P1 FDC-06 / Statistics:** `ForumMetricsProvider` soma Comments Core e ForumPosts; separar `historic_archive` de eventos/métricas correntes, sem reincorporar legado em participação, novas respostas, XP e notificações. Reconciliar FST ledgers, `aculta_index.statistics_bridge` e métricas CGU sem dados pessoais. Cobrir migração desabilitada/zero replay.

**P1 FDC-07 / UI Suite:** inspecionar consumers reais antes de criar componentes; usar SDCs existentes `forum-list-page`, `forum-category`, `forum-topic-page`, `forum-post-card`, `forum-comment`, `forum-reply-compact`, `forum-reply-mirror`, composers, reaction bar e panels. Views/Layouts/Field Group por UI Patterns, UI Styles/Skins/tokens e UI Icons/Phosphor; variante `archive/read-only` sem ações; stories mobile/light/dark/erro/pendente; não habilitar UI Patterns CKEditor 5. Priorizar a migração de rows Fields para UI Patterns nas Views realmente ativas sem remover Twig que ainda é consumer.

**P2 FDC-08+ / paridade Discourse:** Latest/Top/New/Unread, tags, bookmarks, timeline, jump unread, rascunhos, menções, acompanhar/silenciar, review queue CGU, filtros e split/merge com auditoria. Preservar arquitetura Drupal e direitos de cada host, sem tentar copiar Rails/Ember.

## Gates e saída

- Usar `docs/MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md` para testes T-01 a T-28 e incluir casos de origem histórica incompleta. Teste que não rodou = DEFERRED, jamais PASS.
- Revisão independente R2 para acesso/CGU/queries privadas. Transações/banco/config/Composer são R3 e precisam aprovação por operação.
- Publicar commits pequenos em **origin/docs/forum-clone-discourse-ui-cgu-integracoes-20261119**, nunca diretamente em `dev` ou `main`, sem force. Verificar estado de PR #377, status CI, comparação e SHAs. Se proteção impedir merge, deixar PR pendente e informar check exato.
- Não alterar banco Runtime, importar config, desinstalar módulos, rodar DROP, apagar arquivo histórico nem gerar dados de migração. Runtime sync cotidiana somente após revisão/gates e conforme skill, estrutural somente com autorização.
- Entrega final do agente: tabela por ID com P0/P1, arquivos, commits, testes Unit/Kernel/Functional/browser, status do PHPStan, situação de Index/Statistics/CGU, evidência de histórico preservado, pendências/rollback e link PR.

**Documentação base:** `docs/AUDITORIA-CLONE-DISCOURSE-INTEGRACOES-2026-11-19.md`, `docs/PLANO-CLONE-DISCOURSE-UI-SUITE-CGU-2026-11-19.md`, `docs/MATRIZ-PARIDADE-DISCOURSE-UI-PATTERNS-2026-11-19.md`, `docs/MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md`, `aculta_cgu/docs/CONTRATO-INTEGRACAO-FORUM-CGU-2026-11-19.md`.
