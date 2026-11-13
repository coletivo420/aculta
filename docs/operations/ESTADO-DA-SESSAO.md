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

# GitHub — Kernel de acesso em Events — 2026-11-12

- **Objetivo:** corrigir falhas Kernel causadas pela ausência do serviço externo de acesso à organização nos containers isolados.
- **Estado:** handlers negam acesso delegado quando o serviço está ausente. Testes focais EventAccess 3/33 e EventLocationManagement 9 testes passaram. Na CI do SHA `103ac9c91`, estático, descoberta, PHP lint, PHPStan e Unit passaram; o job Kernel foi cancelado sem JUnit. Seleção local de 109 casos foi interrompida em 63/109, sem resultado integral.
- **Decisões e alternativas descartadas:** dependência opcional com falha fechada mantém o contrato seguro em produção, onde `aculta_user` fornece o serviço, e permite testes isolados.
- **Cuidados:** ausência de serviço nunca concede acesso; dependência de módulo segue obrigatória.
- **Commits e arquivos:** aguardando commit; serviços e handlers de acesso do módulo Events, TODO/status e README gerado.
- **Verificações:** EventAccessKernelTest 3/33; EventLocationManagementKernelTest 9; PHP lint, PHPCS, PHPStan focal, descoberta PHPUnit, gate Drupal 12, status index e jobs estático/Unit/PHPStan/lint no SHA `103ac9c91` PASS. Kernel global cancelado; seleção local ampla sem conclusão.
- **Pendências e bloqueios:** recuperar resultado Kernel em condições estáveis; dívida histórica inclui schema Commerce e tabelas Group, conforme auditoria. Sem evidência de causa dos reboots: logs de kernel indisponíveis ao usuário atual.
- **Próximo passo:** continuar análise por classe usando JUnit histórico e validações curtas isoladas, sem suíte integral até estabilizar o ambiente.

