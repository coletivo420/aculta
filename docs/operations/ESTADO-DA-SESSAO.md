# CI — evita duplicar varredura estática por SHA — 2026-11-13

- **Objetivo:** evitar dois scans estáticos quando o mesmo commit dispara `push` e `pull_request`.
- **Estado:** concorrência do workflow estático agora compartilha grupo pelo head SHA, mantendo um scan por conteúdo.
- **Decisões e alternativas descartadas:** manter push, PR e workflow_dispatch; não cancelar checks de commits diferentes.
- **Cuidados:** ação usa o SHA do head no PR e o SHA do commit no push; confirmar coalescência no GitHub.
- **Commits e arquivos:** pendente; `.github/workflows/quality-static.yml` e este handoff.
- **Verificações:** `git diff --check` pendente; actionlint local DEFERRED (binário ausente), validado no CI após push.
- **Pendências e bloqueios:** confirmar um único scan por SHA, incluindo Semgrep; Kernel global segue pendente por shutdown sem JUnit.
- **Próximo passo:** commit/push, acompanhar actionlint e verificar execução única.

# PHPStan — acessibilidade da busca do Fórum — 2026-11-16

- **Objetivo:** reduzir a dívida PHPStan do `ForumPostSearchAccess` em lote de pelo menos 30 ocorrências, preservando a segurança de grants.
- **Estado:** concluído e publicado em `11a89b63a9ec047494a6dcc7dc4023bc50ac8b34` (`Tipa grants de busca do Fórum`). `HEAD`, `origin/dev` e `git ls-remote origin refs/heads/dev` confirmam o mesmo SHA. Corrigidas 27 ocorrências do processor e 5 do teste; removidas as 26 entradas de baseline. Revisão independente R2 final sem blocker.
- **Decisões:** ausências e estruturas inválidas de grants falham fechado; realms numéricos são aceitos por causa da coerção de chaves do PHP. `alterIndexedItems()` tipa `ItemInterface<ComplexDataInterface>` sem stub/supressão.
- **Arquivos publicados:** `web/modules/custom/aculta_portal/modules/aculta_forum/src/Plugin/search_api/processor/ForumPostSearchAccess.php`, `web/modules/custom/aculta_portal/modules/aculta_forum/tests/src/Unit/Plugin/search_api/processor/ForumPostSearchAccessTest.php`, `phpstan-baseline.neon` e este handoff.
- **Gates:** análise canônica PHPStan (exit 0); guard `check --analysis`; guard `diff`; pre-push PHPStan; Unit focado (5 testes, 13 assertions); PHP lint; descoberta PHPUnit; validador Drupal 12 (1236 checks); `git diff --check` PASS. Check Runs do commit: `PHPStan level 10 gate` PASS, `phpstan` PASS, `phpunit-unit` PASS; os seis shards `phpunit-kernel` FAIL. A API de Status do GitHub retorna `pending` sem status entries. Push fast-forward foi confirmado.
- **Runtime Sync:** skill consultada após push. Host `toca`, remoto canônico `coletivo420/aculta`, branch `dev`, `HEAD == origin/dev == 11a89b63a9ec047494a6dcc7dc4023bc50ac8b34`. `status`/`doctor` BLOCKED e `plan` classifica o delta cumulativo como RX; cache/último validado ainda em `e37f3d8d45ecba9719d4b97dde67effc2236b13f`. Doctor: checkout sujo, 17 itens de config drift, 0 updatedb e 0 deploy hooks pendentes; bootstrap Drush e conexão com `aculta_runtime` PASS. Não houve sincronização/cache nem operação Drupal de escrita.
- **Pendência operacional:** RX permanece bloqueado também pelo cutover histórico de `commerce_shipping` e revisão estrutural cumulativa; o plano lista 1979 arquivos e 143 que requerem revisão. Resolver esse preflight segundo `docs/operations/RUNTIME-SYNC.md` antes de qualquer sync. Alteração local não publicada e preservada: `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/PrivateMessageController.php`.
- **Próximo passo:** encerrar este lote; sincronização do Runtime requer resolver os bloqueios externos e não faz parte desta publicação PHPStan.

# PHPStan — Product Card do tema V2 — 2026-11-17

- **Objetivo:** continuar a redução da dívida PHPStan do tema `aculta420_v2`, em lote mínimo de 30 ocorrências.
- **Estado:** correção commitada e publicada em `00454c61eed634ab1323626ded535f2f7c01f476` (`refactor(aculta420_v2): tipa teste product card eliminando 32 erros PHPStan`). `HEAD == origin/dev` nesse SHA. Worktree tem somente este handoff modificado e sem commit.
- **Correção:** leitores de YAML em `ProductCardComponentTest` agora validam cada contêiner/chave e preservam defaults de chaves opcionais. Removidas 21 entradas que correspondiam a 32 ocorrências resolvidas de `offsetAccess.nonOffsetAccessible` e `argument.type` em `phpstan-baseline.neon`; sem mudança de nível ou ignores novos.
- **Gates:** análise canônica PHPStan 2.3.5, PHP 8.4.27, nível 10: PASS (exit 0, zero erros). Guards `check --analysis` e `diff --include-worktree=true --analysis`: PASS. PHP lint: PASS. Unit focal: PASS (3 testes, 79 assertions); PHPUnit reportou uma depreciação preexistente. O socket local do PHPStan funcionou com a execução autorizada fora do sandbox.
- **Publicação:** PR #336 disparou o CI; `phpstan`, `PHPStan level 10 gate` e `phpunit-unit` concluíram SUCCESS no PR. O hook pre-push repetiu `check` e `diff`, ambos PASS. O push direto avançou `origin/dev` por fast-forward de `5c4960475` para `00454c61e`; o GitHub marcou o PR como merged automaticamente. Em execuções duplicadas após o push, PHPStan foi cancelado, o relatório não encontrou `artifacts/phpstan.json` e o gate reportou `result=cancelled`; isso não representa diagnóstico PHPStan nem PASS. Os jobs PHPUnit restantes tiveram estados pending/cancelled/skipped, sem convertê-los em PASS. Sem sincronização de Runtime: o diff só alterou teste e baseline.
- **Próximo passo:** encerrar este lote. Retomar nova redução PHPStan apenas em novo pedido.
