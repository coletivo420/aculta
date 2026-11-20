# Handoff para agente CLI - PR #377, primeira rodada do clone Discourse

Data: 2026-11-19. Branch de trabalho: `docs/forum-clone-discourse-ui-cgu-integracoes-20261119`, PR: https://github.com/coletivo420/aculta/pull/377, destino: `dev`. **Manter commits nessa branch**, sem force-push ou bypass do PHPStan level 10. Esta é uma entrega de código inicial, **não é homologação do Runtime**.

## Fechamento de gates — 2026-11-20

Autorizado pelo responsável: Kernel no banco dedicado `aculta_simpletest`. Resultados nesta branch:

- Kernel CGU e Fórum (`aculta_cgu` + `aculta_forum`): 181 testes, 3.668 asserções, exit 0. Dois testes de denúncia falhavam por fixture sem dono no tópico (`CURRENT` exige `uid`); o hook fail-closed está correto e a fixture foi corrigida.
- Kernel LMS (`aculta_lms`): 74 testes, 2.966 asserções, exit 0.
- Unit completo: 3.379 testes, exit OK. A branch quebrava 17 arquivos de teste pelo contrato Portal (`#theme => portal_component`, `#name`, `data-portal-ui`, `portal_button`); os testes e um fixture pré-refator foram atualizados ao contrato atual.
- PHPStan nível 10: exit 0. Baseline reduzido legitimamente: 9 entradas removidas e 2 contagens reduzidas, sem aumento. `phpstan-debt-guard check` e `diff` PASS.
- Lint dos PHP alterados, `phpunit-suites --check`, `validate-agent-skills`, `validate-agent-token-budget` e `validate-aculta-portal-drupal12`: PASS.

Pendentes, sem execução nesta rodada: teste Runtime (exige decisão sobre checkout do servidor de desenvolvimento), PHPCS do escopo, Functional HTTP/HTMX autenticado e checagem do CI do SHA publicado. Plano P1/P2 (Index, Statistics, UI Suite, CGU além do P0, paridade Discourse) permanece aberto.

## Auditoria P0 — FDC-02 e CGU — 2026-11-20

- **FDC-02 (respostas de tópico):** `DiscussionReplyFormBuilder::buildForumPostReplyForm` já cria `ForumPost` para tópicos `aculta_forum_topic`. Respostas `Comment` ficam restritas a hosts contextuais (Wiki, Revista, LMS), como o handoff manda. Verificado pelo Kernel `DiscussionReplyForumPostKernelTest` (3 testes, 42 asserções, exit OK).
- **CGU — propósito por host:** `ReportService::resolvePurpose` resolve por host e recusa com erro explícito contextos sem adapter (fail-closed).
- **CGU — julgada vs. aplicada, propostas:** `ContributionLifecycle::decide` grava o desfecho e aplica os campos na mesma transação. Conflito de origem vira `conflict` sem aplicar nada.
- **CGU — julgada vs. aplicada, denúncias:** `ReportService::decideReport` grava apenas status, notas e autoria; não altera o conteúdo denunciado. O campo `batch_apply` em `CguReportDecisionForm` replica o mesmo julgamento a outras denúncias pendentes do mesmo alvo. O nome é enganoso, mas não há ação de moderação automática nesse caminho.

Pendências: P1 (Index, Statistics, UI Suite) e P2 (paridade Discourse) não iniciados.

## Revalidação de continuidade — 2026-11-20

Base: `origin/docs/forum-clone-discourse-ui-cgu-integracoes-20261119` em `506512bd8`. A cópia local de `docs/...` no checkout principal estava atrás do remoto em 141 commits sem commits próprios; o trabalho foi feito em worktree dedicado, sem tocar o worktree `aculta-pr377`, que tem alterações de outra frente.

Resultados medidos nesta rodada:

- Unit `aculta_forum`: 278 testes e 2.130 asserções PASS, com 17 depreciações PHPUnit.
- Unit `aculta_cgu`: 3 testes e 19 asserções PASS.
- Focal não-Kernel dos P0 (`DiscussionReplyControllerQuoteTest`, `DiscussionReplyAccessTest`, `ForumQuoteHooksTest`, `TopicDeletionRoutesTest`): 15 testes e 94 asserções PASS, com 49 depreciações.
- `php -l` nos PHP alterados existentes: PASS.
- Kernel focal (`CguReportPolicyKernelTest`, `ForumCguIntegrationKernelTest`): **DEFERRED**. Os 14 casos falham antes da execução por falta de `SIMPLETEST_DB`. A tentativa de carregar `secrets/simpletest-db.env` para rodar no banco `aculta_simpletest` foi bloqueada pelo classificador de permissões da sessão; não houve execução de teste nem acesso ao Runtime. Precisa de autorização explícita do responsável para repetir.

Não executados nesta rodada: PHPCS, PHPStan nível 10 do escopo, Functional HTTP/HTMX autenticado, ESLint e o CI deste SHA. Os itens P1/P2 do plano (Index, Statistics, UI Suite, paridade Discourse) permanecem pendentes.

## Handoff — fronteira módulo/tema e validação local — 2026-11-20

O código desta fase foi publicado na PR em `55f022ad3` (`refactor(portal): concluir fronteira entre módulo e tema`); o registro da redução PHPStan foi publicado em `006983f84` (`docs(phpstan): registrar redução de dívida validada`). O HEAD remoto da PR foi confirmado em `006983f84`. A revisão humana foi informada pelo responsável. Nenhuma alteração foi integrada em `dev` nem aplicada ao Runtime.

### Feito e validado

- Concluído o lote de fronteira Portal/tema: estilos de checkout e loja passaram ao tema V2, render arrays usam o contrato de componente do Portal e o validador da fronteira passou. O teste `ForumTopicDisplayConfigTest` foi corrigido para garantir que `aculta_forum` não dependa do campo histórico `field_autor_legado`, agora pertencente ao addon opcional `aculta_forum_legacy`.
- Corrigida dívida PHPStan em arquivos alterados e removidas entradas resolvidas do baseline, sem baixar nível ou adicionar `@phpstan-ignore`. Relatório e snapshot refletem o SHA do commit funcional.
- PHP lint: PASS, 196/196 arquivos. PHPStan 2.3.5 nível 10: PASS, zero erros não suprimidos. `phpstan-debt-guard check` e `diff`: PASS; 243 entradas removidas e 14 contagens reduzidas. Bats dos guardrails: PASS, 20 casos.
- Unit focal Fórum/H5P e testes Unit alterados: PASS, 330 testes e 2.356 asserções; PHPUnit reportou 17 depreciações. Stylelint, validador Portal Drupal 12 (1.248 verificações), fronteira módulo/tema (7.306 verificações), classes mapeadas e fundamentos V2: PASS.
- O acervo Growroom não foi migrado, convertido, excluído nem misturado. Não foram executados Drush, sync/import de configuração, migrações ou operações no Runtime.

### Pendências e resultados não verdes

- PHPCS retornou código 123: zero erros e 309 avisos nos PHP examinados (292 de linha longa, 16 funções globais e 1 descrição `t()`); não declarar PHPCS PASS.
- A suíte Unit completa não concluiu: o processo terminou por redeclaração de `aculta_messenger_schema()` ao carregar a cópia do módulo na worktree e no checkout principal. Os testes focais acima passaram após corrigir as expectativas obsoletas do campo legado.
- Kernel permanece DEFERRED sem `SIMPLETEST_DB` de teste isolado. ESLint permanece DEFERRED porque o executável `eslint` não está instalado. Os checks de CI disparados pelo push não foram consultados nesta rodada.

### Próximos passos

1. Consultar os checks da PR para o SHA `006983f84`, distinguindo falha, pendente, cancelado e skipped.
2. Repetir a Unit completa em ambiente que não carregue `aculta_messenger.install` das duas árvores; não usar banco do Runtime. Rodar Kernel focal somente com banco descartável de testes.
3. Resolver ou classificar os avisos PHPCS por comparação com a base, sem alterações cosméticas em massa; executar ESLint quando houver binário disponível.
4. Retomar os itens pendentes do plano FDC e da matriz de aceite deste handoff. O resumo desta fase de fronteira não equivale a aceite funcional integral do clone Discourse.

## Atualização Dev.5 — 2026-11-19

A importação histórica do Growroom **ainda não foi feita**. Não há conteúdo
histórico importado no alvo; esta afirmação não descreve nem remove o corpus de
origem. Dev.5 remove do motor a conversão Comment→ForumPost e a autoria
`ForumPost.legacy_author`, além dos displays, plugins, mapas e bypass de
Flagging que existiam exclusivamente para esse fluxo. Nenhum registro foi
apagado, migrado ou alterado no Runtime.

Validação local da fase: Unit focal Fórum/Legacy/User 453 testes e 2.837
asserções PASS (24 depreciações); lint PHP, PHPCS, PHPStan nível 10, `phpstan-debt-guard check`
e `diff`, descoberta de suítes, índice de status e gate Portal Drupal 12 PASS.
Kernel focal foi tentado, mas não iniciou por falta de `SIMPLETEST_DB` (9 casos,
zero asserções); HTTP/Functional está DEFERRED por falta de
`SIMPLETEST_BASE_URL`. A suíte Unit completa foi tentada, mas a descoberta
parou antes dos testes porque o checkout não contém o módulo contrib
`webform_score`, exigido por `WebformScoreAccessTest`. A revisão independente
R2 da Dev.5 foi concluída sem achados no escopo revisado. Nenhuma operação no
Runtime foi executada.

## Atualização Dev.6 — 2026-11-19

`ForumTopicMover` e `ForumCommentMover` agora usam `DiscussionOriginResolver`;
eles movem somente tópico, Comment e descendentes classificados `CURRENT`.
Origem `ARCHIVE` ou `UNKNOWN` falha fechada, sem ler `field_autor_legado` no
motor. As fixtures Kernel deixaram de instalar esse field.

Validação local Dev.6: PHP lint e PHPCS nos seis arquivos PHP alterados PASS;
PHPStan nível 10 global (0 erros/0 file errors) e `phpstan-debt-guard check`/
`diff` PASS; Unit focal Fórum/Forum Legacy/User-Identity 300 testes e 2.220
assertions PASS (25 depreciações); gate Portal Drupal 12 PASS (1.248 checks),
índice de status PASS (43 componentes) e `git diff --check` PASS. A correção de
tipos em `aculta_user` removeu nove ocorrências reais do baseline de
`AuthorCard.php`, sem supressões.

Revisão independente R2 da Dev.6 concluída sem achados. Os dois Kernel focais
estão DEFERRED por falta de `SIMPLETEST_DB`; a descoberta PHPUnit ampla também
falha antes da execução porque falta o módulo contrib `webform_score`. CI do
novo commit ainda estava pendente/em execução na última consulta. Nenhuma
operação no Runtime foi executada.

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
4. **Separação histórica:** `field_autor_legado` nos tópicos/Comments e a proveniência devem identificar o arquivo, sem permitir reply/edit/quote/reaction/flag em rotas alternativas, busca/caches, formulários administrativos e GraphQL/API se houver. `ForumPost` é corrente e não usa autoria histórica; o arquivo não vira Comment padrão nem ForumPost.

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
