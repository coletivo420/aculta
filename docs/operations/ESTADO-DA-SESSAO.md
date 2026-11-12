# GitHub — Kernel de acesso em Events — 2026-11-12

- **Objetivo:** corrigir falhas Kernel causadas pela ausência do serviço externo de acesso à organização nos containers isolados.
- **Estado:** handlers agora negam acesso delegado quando o serviço está ausente; implementação validada localmente.
- **Decisões e alternativas descartadas:** dependência opcional com falha fechada mantém o contrato seguro em produção, onde `aculta_user` fornece o serviço, e permite testes isolados.
- **Cuidados:** ausência de serviço nunca concede acesso; dependência de módulo segue obrigatória.
- **Commits e arquivos:** aguardando commit; serviços e handlers de acesso do módulo Events, TODO/status e README gerado.
- **Verificações:** EventAccessKernelTest 3/33 e EventLocationManagementKernelTest 9 testes PASS; PHP lint, PHPCS, PHPStan focal, descoberta PHPUnit, gate Drupal 12 e status index PASS.
- **Pendências e bloqueios:** CI GitHub desta fase e demais classes Kernel com falhas antigas ainda pendentes.
- **Próximo passo:** publicar a fase e seguir pelos próximos erros do CI.

# Janela estrutural do Runtime — drift de config e R3 — 2026-11-12

- **Objetivo:** executar a janela de configuração e fechar o delta R3 do Runtime de desenvolvimento.
- **Estado:** drift `config:status` zerado (20 itens → 0), `updatedb` e deploy hooks sem pendências, baseline avançado para `fe37ca701`. `plan` em R0, `verify` e `status` em `VALIDATED`. As três configs do R3 e as demais 17 foram alinhadas; as quatro configs de `aculta_forum_post` receberam UUIDs fixos no commit `fe37ca701`.
- **Decisões e alternativas descartadas:** como o `ConfigImporter` não persistia config entities (DT-O11), o Sync foi aplicado escrevendo o dado exato no storage ativo, em vez de reexecutar importações; nenhuma config foi apagada.
- **Cuidados:** o primeiro `config:import --diff` aplicou antes do snapshot (Drush não confirmou em não interativo); o snapshot do banco foi tirado imediatamente depois, em diretório privado 0700 com SHA-256. O snapshot de `files` ficou DEFERRED por permissão (DT-O04). O checkout foi rebaseado sobre dois commits paralelos de design antes do fechamento.
- **Commits e arquivos:** `fe37ca701` (UUIDs das configs do fórum) e a documentação da janela; o commit de execução não altera código de aplicação.
- **Verificações:** `config:status` 0; `updatedb:status` 0; deploy hooks 0; smoke HTTP 200 e um `h1` nos dez `site_keys` (conta 302 anônimo); `structural-complete` com plano R3 compatível; `plan` R0; `verify` VALIDATED.
- **Pendências e bloqueios:** DT-O11 resolvida — não era o `ConfigImporter`; os YAMLs omitiam defaults de entity displays e a ordem canônica do CKEditor 5. YAMLs canônicos no commit `f52598397`, com import comprovado nos dois sentidos e drift 0. Aceite autenticado e visual da configuração importada ainda pendente.
- **Próximo passo:** validar as telas de moderação do fórum e o formulário de página com Layout Builder.

# PHPStan — rodada atual, microfase 24b — 2026-11-11

- **Objetivo:** reduzir diagnósticos PHPStan no fluxo de listagens do Studio e consolidar as microfases acumuladas.
- **Estado:** `MarleyListingBuilder` ficou sem erros brutos (30 → 0); total bruto global registrado em 2.223. Relatório e baseline atualizados.
- **Decisões e alternativas descartadas:** shapes explícitos para os seis formatos de listagem e narrowing de resultados de storage; sem suppressions.
- **Cuidados:** não incluir alterações paralelas de `composer.json`, browser e `web/tmp`; código de Eventos já está em `origin/dev`.
- **Commits e arquivos:** commit desta entrega inclui relatório/baseline, correções e fixtures PHPStan de Messenger, User e Studio, ajustes restantes de testes Eventos e este handoff.
- **Verificações:** PHPStan estrito `[OK]`; focal Studio e PHPUnit focais PASS; Unit global 3286/17661 PASS; descoberta 674, Portal Drupal 12 (1234), lint e diff check PASS. Token budget FAIL por 5 limites preexistentes em css/htmx/sdc/ui-patterns.
- **Pendências e bloqueios:** Runtime/cache DEFERRED; worktree compartilhado contém alterações não relacionadas não incluídas.
- **Próximo passo:** retomar a dívida PHPStan em rodada posterior.
