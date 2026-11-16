# CI — particiona Kernel para obter JUnit — 2026-11-13

- **Objetivo:** corrigir os erros Kernel reportados pelo GitHub.
- **Estado:** `c9e971007` publicado. Kernel cancelado aos 244/1.154 (21%), após 8m26: 14 erros, 3 falhas, sem JUnit. Remover `--no-progress` mostrou avanço, sem evitar cancelamento. Seis lotes locais.
- **Decisões e alternativas descartadas:** balancear diretórios, `max-parallel: 1`; suíte completa não roda local após os reboots.
- **Cuidados:** `continue-on-error` pode deixar workflow verde com Kernel falhando; revisar job/JUnit.
- **Commits e arquivos:** `c9e971007`; workflow PHP, `scripts/phpunit-suites.php` e handoff pendentes.
- **Verificações:** no `c9e971007`, gates estáticos, PHP lint, PHPStan, descoberta e Unit PASS; Kernel parcial. Nova matriz: PHP lint, descoberta, seis lotes/250 arquivos, YAML e diff PASS; actionlint local indisponível, CI pendente. Token budget FAIL por sete limites preexistentes.
- **Pendências e bloqueios:** CI/JUnits; corrigir falhas; reconciliar os 427 achados Semgrep e relatórios de escopos distintos.
- **Próximo passo:** commitar/publicar e tratar os seis relatórios.

# CI — atualiza Actions e fixa runner — 2026-11-13

- **Objetivo:** remover avisos do GitHub sobre Node 20 e migração de `ubuntu-latest`.
- **Estado:** workflows PHP e estático usam Ubuntu 24.04; checkout v7.0.1, setup-node/setup-python v7.1.0 e upload-artifact v7.0.2 estão pinados por SHA oficial, com runtime Node 24.
- **Decisões e alternativas descartadas:** manter pins imutáveis e evitar a migração implícita do runner; não alterar lógica nem permissões dos jobs.
- **Cuidados:** o Kernel de `7ff52631e` segue em execução; não rodar testes pesados localmente.
- **Commits e arquivos:** pendente; `.github/workflows/php-quality.yml`, `.github/workflows/quality-static.yml` e este handoff.
- **Verificações:** `git diff --check` PASS; actionlint local DEFERRED (binário ausente), CI remoto pendente.
- **Pendências e bloqueios:** conferir actionlint e avisos no CI atualizado; obter relatório da suíte Kernel atual.
- **Próximo passo:** validar diff staged, commitar e publicar em `origin/dev`; acompanhar o CI.

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
