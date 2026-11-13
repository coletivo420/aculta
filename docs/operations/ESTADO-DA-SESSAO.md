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
# CI — evita duplicar a suíte PHP por SHA — 2026-11-13

- **Objetivo:** impedir dois workflows PHP completos em paralelo quando o mesmo SHA dispara `push` e `pull_request`.
- **Estado:** workflow ganhou grupo de concorrência por head SHA; no SHA `f91edb04`, actionlint passou e o run PHP de push foi cancelado enquanto o run do PR permaneceu.
- **Decisões e alternativas descartadas:** usar head SHA para preservar uma execução por conteúdo; não remover os gatilhos de push ou PR.
- **Cuidados:** Kernel continua informativo com dívida histórica; não executar suíte local após reboots.
- **Commits e arquivos:** pendente; `.github/workflows/php-quality.yml` e este handoff.
- **Verificações:** actionlint, lint, descoberta, PHPStan e Unit PASS; Kernel do PR segue ativo. actionlint local DEFERRED (binário ausente).
- **Pendências e bloqueios:** Kernel de SHAs anteriores terminou por shutdown sem JUnit; confirmar execução única no SHA atual.
- **Próximo passo:** validar diff, commit/push e conferir actionlint/concorrência no GitHub.
