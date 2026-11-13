# GitHub — baseline PHPStan do Content Index — 2026-11-13

- **Objetivo:** corrigir PHPStan no teste de janela da auditoria incremental.
- **Estado:** removida a asserção repetida `sources` dentro do loop; preservada a asserção da transição `relations` para `sources`. CI anterior mostrou baseline count=1 e ocorrência=2.
- **Decisões e alternativas descartadas:** manter o comportamento e teste do cursor; não alterar baseline nem enfraquecer a verificação de estado.
- **Cuidados:** executar lint e esperar CI remoto; não rodar Kernel local por histórico de travamento.
- **Commits e arquivos:** pendente; ContentIndexRebuilderKernelTest, TODO/status Index e handoff.
- **Verificações:** PHP lint e `git diff --check` pendentes; Semgrep e CI do commit anterior ainda em execução.
- **Pendências e bloqueios:** confirmar PHPStan, Kernel e Semgrep no SHA publicado; dívida antiga Semgrep 427 ainda sem SARIF reconciliado.
- **Próximo passo:** validar diff leve, commit/push e acompanhar workflows.

# GitHub — contrato das métricas de moderação — 2026-11-13

- **Objetivo:** corrigir expectativa Kernel obsoleta em `aculta_statistics`.
- **Estado:** o teste do provedor Fórum cobre `posts_pending`; `reports_open` é coberto no provider de `aculta_cgu`, conforme ownership FST-07. Kernel não foi rodado localmente por risco de travamento; aguarda CI.
- **Decisões e alternativas descartadas:** não reintroduzir denúncias no provider do Fórum nem ampliar o harness do módulo Statistics para instalar CGU, pois o owner já tem teste Kernel dedicado.
- **Cuidados:** tratar eventual falha remota com JUnit; PHPStan no CI do SHA anterior está em failure e ainda sem log disponível.
- **Commits e arquivos:** pendente; teste de integração Kernel, TODO/status de `aculta_statistics` e este handoff.
- **Verificações:** pendentes para este diff. CI estático no SHA `e3be2d9a` PASS; PHP lint, descoberta, Unit PASS e PHPStan FAIL no CI anterior; Kernel em execução.
- **Pendências e bloqueios:** obter o log do PHPStan e conclusão Kernel; Semgrep antigo reportado como 427 ainda sem SARIF de origem que permita reconciliação; Runtime Sync RX/BLOCKED.
- **Próximo passo:** validar diff/gates leves, commitar e publicar; seguir o CI remoto e corrigir o próximo achado comprovado.


