# Sincronizacao do Runtime de desenvolvimento - ACULTA (v2)

> **Estado (2026-11-11):** Runtime Sync v2 implementado em `scripts/runtime-sync.sh`, `scripts/runtime-sync/helper.py`, `scripts/runtime-sync/risk-rules.yml` e `scripts/test-runtime-sync.sh`, com leitura neutra integrada ao painel do `aculta_deployer`. Veja a arquitetura detalhada em [Runtime Sync v2 - plano de arquitetura e implementacao](RUNTIME-SYNC-PLANO-V2.md). **Aviso de isolamento (F4):** enquanto o cutover da worktree servida nao for aprovado e executado pelo responsavel, o checkout editado pelos agentes (`/home/aculta -> /home/piradopirata/projetos/aculta`) continua sendo o `DocumentRoot` do Apache.

**Skills de agentes:** `aculta-runtime-sync` para pós-push, diagnóstico e código/cache; `aculta-runtime-migracoes` para Composer, banco, config, restauração, ordem de updates e isolamento de worktree. Ambas são canônicas em `.agents/skills` e acessíveis por Claude e Codex.

## Contratos verificados no projeto

- `AGENTS.md`: após push para `origin/dev`, a rotina cotidiana só atualiza código e reconstrói cache (`R0`/`R1`). `updatedb`, `config:import`, `pm:uninstall`, `DROP` e exclusão de dados exigem autorização específica.
- `docs/architecture/environments.md`: o servidor de desenvolvimento (`toca`) compartilha o checkout servido pelo Apache com os agentes, em Debian/PHP-FPM/MariaDB. O banco esperado é `aculta_runtime` e o remoto canônico é `coletivo420/aculta`.
- `config/sync`: fonte completa da configuração desejada. Drift não é resolvido por exportação massiva ou importação parcial automática.
- `web/modules/custom/aculta_portal/modules/aculta_deployer/`: CLI standalone sem dependências de Drupal, Drush ou `vendor/`. O painel administrativo em `aculta_portal` lê `var/deployer/status.json` (schema 1) e `var/runtime-sync/state.json` + `latest.json` (schema 2) de forma estritamente somente leitura.
- `docs/operations/RUNTIME-UPDATE-WINDOW.md`: snapshots e restaurações exigem banco descartável autorizado (como `aculta_runtime_copia`, nunca `aculta_simpletest`) e ordem revisada por janela (como o cutover do `commerce_shipping`).

## Comandos da CLI (`scripts/runtime-sync.sh`)

| Acao | Comando | Gravacao | Quando usar |
| --- | --- | --- | --- |
| Status local | `bash scripts/runtime-sync.sh status [--format=json]` | Nenhuma (sem rede/Drush) | Consultar último SHA validado, estado do cache (`VALIDATED`/`STALE`/`UNKNOWN`), falhas parciais e histórico |
| Planejamento | `bash scripts/runtime-sync.sh plan [--refresh] [--format=json]` | Nenhuma no checkout (`--refresh` usa staging temporário `0700` descartável) | Comparar `HEAD`, `origin/dev` e `last_validated_sha`, e classificar risco cumulativo `R0..R4`/`RX` |
| Diagnóstico | `bash scripts/runtime-sync.sh doctor [--format=json]` | Nenhuma | Auditar branch, limpeza do worktree, lock cooperativo, Drush, `updatedb:status`, `config:status`, `deploy:hook-status` e isolamento F4 |
| Código e cache | `bash scripts/runtime-sync.sh sync-code [--adopt-baseline] [--format=json]` | `git fetch`, `merge --ff-only`, `drush cr` e estado em `var/runtime-sync/` | Sincronização cotidiana pós-push quando o checkout estiver limpo e o delta for `R0`/`R1` |
| Verificação | `bash scripts/runtime-sync.sh verify [--format=json]` | Relatório em `var/runtime-sync/` | Confirmar SHA, cache validado e saúde Drush após sincronização ou janela estrutural |
| Plano estrutural | `bash scripts/runtime-sync.sh structural-plan [--refresh] [--format=json]` | Nenhuma no Runtime | Gerar checklist ordenado de backup/restore, Composer, `updatedb`, `config:import` e exceções (`commerce_shipping`) para deltas `R2..R4` |
| Plano de isolamento | `bash scripts/runtime-sync.sh isolation-plan [--format=json]` | Nenhuma no Runtime | Inspecionar mounts persistentes e comando `git worktree add --detach` para separar checkout de agentes do Apache (F4) |

O script **nunca** executa `composer install`, `composer update`, `updatedb`, `config:import`, `config:export`, `pm:uninstall`, `DROP`, `git reset`, `git stash` ou `git clean`.

## Procedimento diário para agentes no servidor de desenvolvimento

1. **Inspecionar estado e planejar (somente leitura):**

```bash
bash scripts/runtime-sync.sh status
bash scripts/runtime-sync.sh plan --refresh
```

2. **Sincronizar código e cache (apenas para `R0`/`R1` com checkout limpo):**

```bash
export ACULTA_SYNC_ENV=test
export ACULTA_SYNC_EXPECTED_HOST="toca"
export ACULTA_SYNC_EXPECTED_DB="aculta_runtime"
export ACULTA_SYNC_URI="${DRUPAL_URI_TEST:?defina DRUPAL_URI_TEST com HTTPS de um site_key de desenvolvimento}"
bash scripts/runtime-sync.sh sync-code
```

- Se `HEAD == origin/dev` mas `last_cache_success_sha` ainda não coincidir com `HEAD`, o v2 executa `drush cr` e atualiza `last_validated_sha` (corrigindo o falso positivo do v1).
- Se `HEAD == origin/dev` e o cache já estiver validado para esse SHA, o comando retorna `NOOP` sem reconstruir o cache novamente.
- Se `drush cr` falhar após o fast-forward, o estado persistido fica como `PARTIAL` (`last_code_sync_sha` atualizado, `last_validated_sha` preservado no valor anterior); corrigida a causa, basta repetir `sync-code`.
- Para adotar o baseline inicial de forma supervisionada quando `last_validated_sha` for `UNKNOWN`, o operador autorizado pode executar `ACULTA_SYNC_ADOPT_BASELINE=1 bash scripts/runtime-sync.sh sync-code --adopt-baseline`.

## Procedimento supervisionado para mudanças estruturais (`R2`, `R3`, `R4`, `RX`)

1. **Gerar o plano estrutural e o diagnóstico:**

```bash
bash scripts/runtime-sync.sh structural-plan --refresh --format=json
bash scripts/runtime-sync.sh doctor --format=json
```

2. **Congelar a janela e preservar trabalho alheio:** pausar agentes escritores, cron e filas afetadas. Nunca limpar alterações de outros agentes com `reset`/`clean`/`stash`.
3. **Backup e ensaio de restauração:** gerar dump consistente de `aculta_runtime` (`--single-transaction --routines --triggers --hex-blob`) e snapshot dos arquivos persistentes fora do Git e do DocumentRoot (modo `0600`), registrar SHA-256 e testar a restauração e o boot em banco descartável autorizado (`aculta_runtime_copia`, nunca `aculta_simpletest`).
4. **Ordem de execução aprovada:** seguir a sequência de `structural-plan`. Em casos como a remoção de `commerce_shipping`, executar `updatedb` e `pm:uninstall commerce_shipping` aprovado **com o vendor antigo** antes de rodar `composer install`.
5. **Homologação pós-janela:** executar `drush cr`, `bash scripts/runtime-sync.sh verify` e smokes HTTP dos `site_keys` afetados.
