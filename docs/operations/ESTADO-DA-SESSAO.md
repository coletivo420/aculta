# Janela estrutural do Runtime — drift de config e R3 — 2026-11-12

- **Objetivo:** executar a janela de configuração e fechar o delta R3 do Runtime de desenvolvimento.
- **Estado:** drift `config:status` zerado (20 itens → 0), `updatedb` e deploy hooks sem pendências, baseline avançado para `fe37ca701`. `plan` em R0, `verify` e `status` em `VALIDATED`. As três configs do R3 e as demais 17 foram alinhadas; as quatro configs de `aculta_forum_post` receberam UUIDs fixos no commit `fe37ca701`.
- **Decisões e alternativas descartadas:** como o `ConfigImporter` não persistia config entities (DT-O11), o Sync foi aplicado escrevendo o dado exato no storage ativo, em vez de reexecutar importações; nenhuma config foi apagada.
- **Cuidados:** o primeiro `config:import --diff` aplicou antes do snapshot (Drush não confirmou em não interativo); o snapshot do banco foi tirado imediatamente depois, em diretório privado 0700 com SHA-256. O snapshot de `files` ficou DEFERRED por permissão (DT-O04). O checkout foi rebaseado sobre dois commits paralelos de design antes do fechamento.
- **Commits e arquivos:** `fe37ca701` (UUIDs das configs do fórum) e a documentação da janela; o commit de execução não altera código de aplicação.
- **Verificações:** `config:status` 0; `updatedb:status` 0; deploy hooks 0; smoke HTTP 200 e um `h1` nos dez `site_keys` (conta 302 anônimo); `structural-complete` com plano R3 compatível; `plan` R0; `verify` VALIDATED.
- **Pendências e bloqueios:** investigar por que o `ConfigImporter` não persiste config entities neste Runtime (DT-O11); aceite autenticado e visual da configuração importada ainda pendente.
- **Próximo passo:** diagnosticar DT-O11 e validar as telas de moderação do fórum e o formulário de página com Layout Builder.

# PHPStan — rodada atual, microfase 24b — 2026-11-11

- **Objetivo:** reduzir diagnósticos PHPStan no fluxo de listagens do Studio e consolidar as microfases acumuladas.
- **Estado:** `MarleyListingBuilder` ficou sem erros brutos (30 → 0); total bruto global registrado em 2.223. Relatório e baseline atualizados.
- **Decisões e alternativas descartadas:** shapes explícitos para os seis formatos de listagem e narrowing de resultados de storage; sem suppressions.
- **Cuidados:** não incluir alterações paralelas de `composer.json`, browser e `web/tmp`; código de Eventos já está em `origin/dev`.
- **Commits e arquivos:** commit desta entrega inclui relatório/baseline, correções e fixtures PHPStan de Messenger, User e Studio, ajustes restantes de testes Eventos e este handoff.
- **Verificações:** PHPStan estrito `[OK]`; focal Studio e PHPUnit focais PASS; Unit global 3286/17661 PASS; descoberta 674, Portal Drupal 12 (1234), lint e diff check PASS. Token budget FAIL por 5 limites preexistentes em css/htmx/sdc/ui-patterns.
- **Pendências e bloqueios:** Runtime/cache DEFERRED; worktree compartilhado contém alterações não relacionadas não incluídas.
- **Próximo passo:** retomar a dívida PHPStan em rodada posterior.

# ADR-054/055 — RN-04 publicado e RN-05 parcial — 2026-11-11

- **Objetivo:** concluir a renomeação funcional `purpose` → `site_key` e revalidar o Runtime.
- **Estado:** classes, serviços, rotas, opções, State, sessão e a condição de bloco (`aculta_domain_site`/`site_key`) migrados sem aliases internos; 23 configs de bloco migradas no Sync e no install. PHPStan 0; unit 3287 PASS; Portal 1234 PASS; boundary 7888 PASS; browser index 151; admin routes e aliases PASS; Firefox ESR 70/70 nos dez domínios após `drush cr`.
- **Decisões e alternativas descartadas:** aceitar as versões do remoto nos conflitos do wiki e reaplicar a renomeação; manter o guard de relations com `@phpstan-ignore`; mesclar as entradas novas do baseline em vez de regenerá-lo.
- **Cuidados:** o import parcial autorizado das 22 configs de bloco foi executado após snapshot; o site responde 200 e o smoke 70/70 foi repetido. O login autenticado segue bloqueado (DT-S01); rotas públicas são `private/no-cache` e `UNCACHEABLE`.
- **Commits e arquivos:** `0c34d22cc`, `164608d1d`, `8e4816826` publicados; Portal, gates, baseline, inventário de contratos e ADRs.
- **Verificações:** PHPStan 0, unit 3287, Portal 1234, boundary 7888, browser index 151, admin routes, aliases, root policy e status index PASS; smoke Firefox 70/70 pós-import.
- **Pendências e bloqueios:** Kernel focal do domínio PASS com SQLite isolado; suíte Kernel completa DEFERRED por dependências de ambiente (`aculta_file_uploader`, `aculta_lms_resources`, schema do Commerce), sessão autenticada (DT-S01), cache-hit e aceite humano.
- **Próximo passo:** importar a configuração autorizada, rodar Kernel/Functional e concluir RN-05.
