# CI — evita duplicar a suíte PHP por SHA — 2026-11-13

- **Objetivo:** impedir dois workflows PHP completos em paralelo quando o mesmo SHA dispara `push` e `pull_request`.
- **Estado:** workflow ganhou grupo de concorrência por head SHA, cancelando a execução duplicada mais antiga.
- **Decisões e alternativas descartadas:** usar head SHA para preservar uma execução por conteúdo; não remover os gatilhos de push ou PR.
- **Cuidados:** Kernel continua informativo com dívida histórica; não executar suíte local após reboots.
- **Commits e arquivos:** pendente; `.github/workflows/php-quality.yml` e este handoff.
- **Verificações:** diff check pendente; actionlint local DEFERRED (binário ausente); workflow estático do GitHub valida o arquivo após push.
- **Pendências e bloqueios:** kernels de SHAs anteriores foram encerrados por shutdown sem JUnit; verificar que o novo grupo deixa apenas um run ativo no SHA.
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

# GitHub — baseline PHPStan do Content Index — 2026-11-13

- **Objetivo:** corrigir PHPStan no teste de janela da auditoria incremental.
- **Estado:** removida a asserção repetida `sources` dentro do loop; PHPStan identificou `$sourcePhase` sem leitura e a atribuição do loop foi removida. PHPStan, lint, descoberta e Unit passaram em `f5dcfd832`.
- **Decisões e alternativas descartadas:** manter o comportamento e teste do cursor; não alterar baseline nem enfraquecer a verificação de estado.
- **Cuidados:** executar lint e esperar CI remoto; não rodar Kernel local por histórico de travamento.
- **Commits e arquivos:** pendente; ContentIndexRebuilderKernelTest, TODO/status Index e handoff.
- **Verificações:** PHPStan, lint, descoberta, Unit e Semgrep PASS em `f5dcfd832`; Kernel sem resultado pois o runner foi encerrado por shutdown no run anterior.
- **Pendências e bloqueios:** Kernel global continua sem JUnit; dívida antiga Semgrep 427 ainda sem SARIF reconciliado.
- **Próximo passo:** validar diff leve, commit/push e acompanhar workflows.

