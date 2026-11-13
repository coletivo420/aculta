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

# GitHub — interface de leitura dos avisos — 2026-11-13

- **Objetivo:** corrigir o filtro de avisos lidos apontado pelo JUnit Kernel histórico.
- **Estado:** `Aviso` implementa `EntityChangedInterface`, que `marcarLido()` exige antes de zerar `contador_site`; sem a interface, todas as entidades eram ignoradas. README raiz regenerado para sincronizar a tabela de status.
- **Decisões e alternativas descartadas:** implementar o contrato na entidade que já usa `EntityChangedTrait`; não remover a guarda nem alterar o filtro.
- **Cuidados:** validação Kernel local suspensa após reboots; CI remoto atual ainda sem JUnit.
- **Commits e arquivos:** pendente; entidade Aviso, TODO/status de `aculta_messenger`, índice README e handoff.
- **Verificações:** PHP lint, PHPStan, descoberta, Unit, Semgrep, `git diff --check`, status index e Drupal 12 (1234 checks) PASS; token budget FAIL em 7 limites preexistentes de skills. Kernel de `a35cb0b2` foi encerrado pelo runner sem JUnit; JUnit histórico apontou lista lida vazia.
- **Pendências e bloqueios:** aguardar uma execução Kernel conclusiva após reduzir duplicatas.
- **Próximo passo:** validar diff leve, commit/push, acompanhar CI e seguir os demais casos Kernel com evidência.

