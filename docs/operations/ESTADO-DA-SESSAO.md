# CI — Kernel publica progresso durante PHPUnit — 2026-11-13

- **Objetivo:** tornar observável o gate Kernel cancelado sem relatório.
- **Estado:** no SHA `d2a8c042`, jobs estáticos, PHP lint, PHPStan, descoberta e Unit passaram. Kernel foi cancelado após 9m54 durante `Run Kernel suite`; sem JUnit. A suíte usava `--no-progress`; removido para expor avanço no log. Mudança aguardando commit e nova execução remota.
- **Decisões e alternativas descartadas:** manter timeout de 45 min e a suíte Kernel; não ocultar o cancelamento tornando o job verde nem rodar suíte integral local após os reboots reportados.
- **Cuidados:** `continue-on-error` mantém o workflow agregado verde, mas o job Kernel continua falhando/cancelado e deve ser avaliado à parte.
- **Commits e arquivos:** `d2a8c042` (deduplicação push/PR); `.github/workflows/php-quality.yml` e este handoff pendentes.
- **Verificações:** actionlint, Semgrep local, Bats, shell, frontend, PHP lint, PHPStan, descoberta e PHPUnit Unit PASS no PR #304. Kernel cancelado sem artefato JUnit. `git diff --check` e YAML PASS; validador de token budget FAIL por sete violações preexistentes em skills fora do diff.
- **Pendências e bloqueios:** obter execução Kernel conclusiva e corrigir falhas reais; reconciliar o inventário Semgrep histórico de 427 com os relatórios locais, que usam escopos distintos.
- **Próximo passo:** validar diff e YAML, commitar/publicar, acompanhar o Kernel com progresso visível e inspecionar JUnit.

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
