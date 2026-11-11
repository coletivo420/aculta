# PHPStan — microfase 22b aculta_events — 2026-11-11

- **Objetivo:** zerar erros PHPStan de produção em `aculta_events`.
- **Estado:** produção em zero; 112 erros permanecem somente nos testes. PHPStan global estrito `[OK] No errors`; Unit focal PASS. Unit global interrompido por fatal de função duplicada entre checkouts.
- **Decisões e alternativas descartadas:** narrowing de entidades/storage e correções de contrato; sem ignorar diagnósticos. `FOR UPDATE` preservado no objeto da consulta.
- **Cuidados:** suíte global falhou aos 1708/3286 por duplicidade de `aculta_messenger_schema()`; nenhum Kernel executado para evitar o banco operacional.
- **Commits e arquivos:** commit desta microfase inclui Eventos, baseline, relatório e este handoff; hash informado na entrega.
- **Verificações:** Unit do módulo 30/62 PASS; lint, YAML, gate Portal 1234, descoberta de suítes e PHPStan global PASS. Unit global FAIL pela duplicidade citada. Runtime DEFERRED pelo checkout principal sujo.
- **Pendências e bloqueios:** 112 erros em testes de `aculta_events`; Runtime/cache não sincronizados.
- **Próximo passo:** retomar a limpeza PHPStan dos testes de `aculta_events`.

# Runtime — ativação de aculta_activity — 2026-11-11

- **Objetivo:** habilitar ACULTA Activity no Runtime de desenvolvimento.
- **Estado:** `aculta_activity` Enabled no `aculta_runtime`; cache reconstruído. Dump prévio protegido fora do Git. Atualização pendente `media_duplicates_update_11001` permanece sem execução.
- **Decisões e alternativas descartadas:** habilitação direta solicitada; sem `updatedb` ou `config:import`, pois exigem ordem explícita e há drift de configuração preexistente.
- **Cuidados:** Drush confirmou `toca`, Drupal 11.5.0-beta1 e banco `aculta_runtime`. Snapshot fora do Git, modo 600.
- **Commits e arquivos:** commit de registro pendente; status `aculta_activity.yml` e este handoff.
- **Verificações:** `pm:list`/`cget core.extension` confirmaram Enabled/0; `drush cr` PASS; `updatedb:status` mostra pendência preexistente de media_duplicates.
- **Pendências e bloqueios:** revisão visual no Firefox/Runtime; update de banco e drift de config não tratados.
- **Próximo passo:** validar formulário e Schema.org no navegador e encaminhar a pendência de banco em janela autorizada.

# ADR-054/055 — RN-04 publicado e RN-05 parcial — 2026-11-11

- **Objetivo:** concluir a renomeação funcional `purpose` → `site_key` e revalidar o Runtime.
- **Estado:** classes, serviços, rotas, opções, State, sessão e a condição de bloco (`aculta_domain_site`/`site_key`) migrados sem aliases internos; 23 configs de bloco migradas no Sync e no install. PHPStan 0; unit 3287 PASS; Portal 1234 PASS; boundary 7888 PASS; browser index 151; admin routes e aliases PASS; Firefox ESR 70/70 nos dez domínios após `drush cr`.
- **Decisões e alternativas descartadas:** aceitar as versões do remoto nos conflitos do wiki e reaplicar a renomeação; manter o guard de relations com `@phpstan-ignore`; mesclar as entradas novas do baseline em vez de regenerá-lo.
- **Cuidados:** o import parcial autorizado das 22 configs de bloco foi executado após snapshot; o site responde 200 e o smoke 70/70 foi repetido. O login autenticado segue bloqueado (DT-S01); rotas públicas são `private/no-cache` e `UNCACHEABLE`.
- **Commits e arquivos:** `0c34d22cc`, `164608d1d`, `8e4816826` publicados; Portal, gates, baseline, inventário de contratos e ADRs.
- **Verificações:** PHPStan 0, unit 3287, Portal 1234, boundary 7888, browser index 151, admin routes, aliases, root policy e status index PASS; smoke Firefox 70/70 pós-import.
- **Pendências e bloqueios:** Kernel focal do domínio PASS com SQLite isolado; suíte Kernel completa DEFERRED por dependências de ambiente (`aculta_file_uploader`, `aculta_lms_resources`, schema do Commerce), sessão autenticada (DT-S01), cache-hit e aceite humano.
- **Próximo passo:** importar a configuração autorizada, rodar Kernel/Functional e concluir RN-05.
