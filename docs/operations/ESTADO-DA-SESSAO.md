# GitHub — interface de leitura dos avisos — 2026-11-13

- **Objetivo:** corrigir o filtro de avisos lidos apontado pelo JUnit Kernel histórico.
- **Estado:** `Aviso` implementa `EntityChangedInterface`, que `marcarLido()` exige antes de zerar `contador_site`; sem a interface, todas as entidades eram ignoradas. README raiz regenerado para sincronizar a tabela de status.
- **Decisões e alternativas descartadas:** implementar o contrato na entidade que já usa `EntityChangedTrait`; não remover a guarda nem alterar o filtro.
- **Cuidados:** validação Kernel local suspensa após reboots; CI remoto atual ainda sem JUnit.
- **Commits e arquivos:** pendente; entidade Aviso, TODO/status de `aculta_messenger`, índice README e handoff.
- **Verificações:** PHP lint, `git diff --check`, status index e Drupal 12 (1234 checks) PASS; token budget FAIL em 7 limites preexistentes de skills; JUnit histórico apontou `testForumReadFilterSelectsMatchingAggregates` com lista de lidos vazia.
- **Pendências e bloqueios:** confirmar o teste no CI; Kernel dos SHAs anteriores foi interrompido por shutdown do runner sem relatório.
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

# GitHub — contrato das métricas de moderação — 2026-11-13

- **Objetivo:** corrigir expectativa Kernel obsoleta em `aculta_statistics`.
- **Estado:** o teste do provedor Fórum cobre `posts_pending`; `reports_open` é coberto no provider de `aculta_cgu`, conforme ownership FST-07. Kernel do commit foi interrompido por shutdown do runner, sem relatório.
- **Decisões e alternativas descartadas:** não reintroduzir denúncias no provider do Fórum nem ampliar o harness do módulo Statistics para instalar CGU, pois o owner já tem teste Kernel dedicado.
- **Cuidados:** Kernel não rodado localmente por risco de travamento.
- **Commits e arquivos:** pendente; teste de integração Kernel, TODO/status de `aculta_statistics` e este handoff.
- **Verificações:** Semgrep, PHP lint, descoberta, Unit e PHPStan PASS em SHAs seguintes; Kernel continua sem relatório. O log do PHPStan documentou e motivou a correção separada do Index.
- **Pendências e bloqueios:** obter conclusão Kernel; Semgrep antigo reportado como 427 não corresponde diretamente aos SARIF arquivados; Runtime Sync RX/BLOCKED.
- **Próximo passo:** validar diff/gates leves, commitar e publicar; seguir o CI remoto e corrigir o próximo achado comprovado.


