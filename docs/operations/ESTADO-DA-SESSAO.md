# Runtime Sync v2 — fases F0 a F6, classificador, skills e Deployer — 2026-11-11

- **Objetivo:** concluir a implementação técnica do Runtime Sync v2 (`F0` a `F6`) e sua integração às skills e ao painel do ACULTA Deployer.
- **Estado:** `scripts/runtime-sync.sh` v2 e `scripts/runtime-sync/helper.py` implementados com estado persistente atômico (`0600`, anti-symlink, `var/runtime-sync/`), correção do bug `HEAD == origin/dev` com cache pendente, classificador cumulativo `R0`–`R4`/`RX`, coordenação cooperativa (`flock`), `structural-plan`, `isolation-plan`, leitura no `DeployerStatusReader`/`DeployerStatusController` e suíte `scripts/test-runtime-sync.sh`.
- **Decisões e alternativas descartadas:** worktree isolada em `/tmp` para não tocar alterações de outros agentes no checkout compartilhado; staging isolado em `plan --refresh`; schema 1 de `var/deployer/status.json` intacto; cutover do Apache (`F4`) e `media_duplicates_update_11001` mantidos pendentes de autorização explícita.
- **Cuidados:** nenhuma operação destrutiva (`updatedb`, `config:import`, `pm:uninstall`, `git reset/clean`) foi executada; redação automática de segredos em JSON/logs.
- **Commits e arquivos:** `scripts/runtime-sync.sh`, `scripts/runtime-sync/*`, `scripts/test-runtime-sync.sh`, `DeployerStatusReader.php`, `DeployerStatusController.php`, `DeployerStatusReaderTest.php`, skills de Runtime/entrega/gates, runbooks e status YAML.
- **Verificações:** `test-runtime-sync.sh` 24/24 PASS; `DeployerStatusReaderTest` 3/10 PASS; `aculta_deployer/tests/run.php` PASS; `validate-agent-skills.py` PASS (12 cenários RS); `validate-project-status-index.php`, `validate-aculta-portal-drupal12.php`, `validate-admin-routes-main-domain.php` e `validate-module-theme-boundary.php` PASS.
- **Pendências e bloqueios:** checkout servido (`/home/piradopirata/projetos/aculta`) possui arquivos modificados/untracked de outros agentes e 1 `updatedb` pendente (`media_duplicates_update_11001`), bloqueando `sync-code` no Runtime até limpeza/janela autorizada; cutover F4 aguarda aprovação.
- **Próximo passo:** quando o checkout compartilhado estiver limpo e a janela de `media_duplicates_update_11001` for autorizada, adotar baseline (`--adopt-baseline`) e avaliar cutover F4.

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
