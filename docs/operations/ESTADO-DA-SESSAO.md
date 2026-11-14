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
