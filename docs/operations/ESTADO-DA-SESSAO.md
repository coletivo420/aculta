# GitHub — contrato das métricas de moderação — 2026-11-13

- **Objetivo:** corrigir expectativa Kernel obsoleta em `aculta_statistics`.
- **Estado:** o teste do provedor Fórum cobre `posts_pending`; `reports_open` é coberto no provider de `aculta_cgu`, conforme ownership FST-07. Kernel não foi rodado localmente por risco de travamento; aguarda CI.
- **Decisões e alternativas descartadas:** não reintroduzir denúncias no provider do Fórum nem ampliar o harness do módulo Statistics para instalar CGU, pois o owner já tem teste Kernel dedicado.
- **Cuidados:** tratar eventual falha remota com JUnit; PHPStan no CI do SHA anterior está em failure e ainda sem log disponível.
- **Commits e arquivos:** pendente; teste de integração Kernel, TODO/status de `aculta_statistics` e este handoff.
- **Verificações:** pendentes para este diff. CI estático no SHA `e3be2d9a` PASS; PHP lint, descoberta, Unit PASS e PHPStan FAIL no CI anterior; Kernel em execução.
- **Pendências e bloqueios:** obter o log do PHPStan e conclusão Kernel; Semgrep antigo reportado como 427 ainda sem SARIF de origem que permita reconciliação; Runtime Sync RX/BLOCKED.
- **Próximo passo:** validar diff/gates leves, commitar e publicar; seguir o CI remoto e corrigir o próximo achado comprovado.


# GitHub — contrato site_key na apresentação do Portal — 2026-11-13

- **Objetivo:** corrigir falhas Kernel históricas causadas pelo uso de `purpose` após RN-04.
- **Estado:** DomainPresentationBuilder, teste de apresentação e README alinhados a `site_key`/DomainSiteManager; Kernel não executado localmente.
- **Decisões e alternativas descartadas:** preservar o contrato RN-04, sem alias de `purpose` nem mudança de autorização.
- **Cuidados:** CI Kernel em `afb62d168` encerrou após SIGTERM/código 143 sem JUnit; causa do runner não identificada.
- **Commits e arquivos:** commit desta fase; builder, teste Kernel, README, TODO/status e handoff.
- **Verificações:** `php -l` nos dois PHP e `git diff --check` PASS; token budget FAIL em 7 skills preexistentes; PHPUnit DEFERRED para CI remoto após reboots locais.
- **Pendências e bloqueios:** falhas históricas de Fórum, Index, Messenger, Statistics e Events ainda precisam de execução Kernel completa; contagem Semgrep 427 sem SARIF de origem; Runtime Sync RX/BLOCKED.
- **Próximo passo:** verificar CI desta fase; auditar os demais casos do JUnit histórico e corrigir defeitos atuais com evidência.

# GitHub — providers do Content Index no Kernel — 2026-11-13

- **Objetivo:** corrigir a expectativa histórica do registry no harness Kernel do Index.
- **Estado:** testes de registry, backfill vazio e auditoria percorrem os providers até a conclusão; asserções de projeção e teto por fixture foram preservadas. PHPUnit não executado localmente.
- **Decisões e alternativas descartadas:** alinhar os testes aos lotes por provider, sem remover nem desativar providers no harness.
- **Cuidados:** o teste é Kernel e precisa de confirmação CI; o runner recente foi instável.
- **Commits e arquivos:** commit desta fase; ContentIndexSchemaKernelTest, ContentIndexRebuilderKernelTest, TODO/status e handoff.
- **Verificações:** PHP lint e `git diff --check` PASS; validação de orçamento DEFERRED (7 falhas preexistentes em outras skills); PHPUnit DEFERRED ao CI remoto.
- **Pendências e bloqueios:** conferir workflows do novo SHA; falhas antigas do Kernel ainda não auditadas integralmente; Runtime Sync RX/BLOCKED.
- **Próximo passo:** corrigir o próximo achado Kernel comprovado, após conferir o relatório JUnit remoto mais recente.

