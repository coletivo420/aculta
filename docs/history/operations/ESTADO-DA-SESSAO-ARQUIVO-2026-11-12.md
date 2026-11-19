# Histórico de sessões — 2026-11-12

# ADR-054/055 — RN-04 publicado e RN-05 parcial — 2026-11-11

- **Objetivo:** concluir a renomeação funcional `purpose` → `site_key` e revalidar o Runtime.
- **Estado:** classes, serviços, rotas, opções, State, sessão e a condição de bloco (`aculta_domain_site`/`site_key`) migrados sem aliases internos; 23 configs de bloco migradas no Sync e no install. PHPStan 0; unit 3287 PASS; Portal 1234 PASS; boundary 7888 PASS; browser index 151; admin routes e aliases PASS; Firefox ESR 70/70 nos dez domínios após `drush cr`.
- **Decisões e alternativas descartadas:** aceitar as versões do remoto nos conflitos do wiki e reaplicar a renomeação; manter o guard de relations com `@phpstan-ignore`; mesclar as entradas novas do baseline em vez de regenerá-lo.
- **Cuidados:** o import parcial autorizado das 22 configs de bloco foi executado após snapshot; o site responde 200 e o smoke 70/70 foi repetido. O login autenticado segue bloqueado (DT-S01); rotas públicas são `private/no-cache` e `UNCACHEABLE`.
- **Commits e arquivos:** `0c34d22cc`, `164608d1d`, `8e4816826` publicados; Portal, gates, baseline, inventário de contratos e ADRs.
- **Verificações:** PHPStan 0, unit 3287, Portal 1234, boundary 7888, browser index 151, admin routes, aliases, root policy e status index PASS; smoke Firefox 70/70 pós-import.
- **Pendências e bloqueios:** Kernel focal do domínio PASS com SQLite isolado; suíte Kernel completa DEFERRED por dependências de ambiente (`aculta_file_uploader`, `aculta_lms_resources`, schema do Commerce), sessão autenticada (DT-S01), cache-hit e aceite humano.
- **Próximo passo:** importar a configuração autorizada, rodar Kernel/Functional e concluir RN-05.

