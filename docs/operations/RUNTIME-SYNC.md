# Sincronizacao do Runtime de desenvolvimento - ACULTA (v2)

> **Estado (2026-11-14):** Runtime Sync v2 implementado em `scripts/runtime-sync.sh`, `scripts/runtime-sync/helper.py`, `scripts/runtime-sync/risk-rules.yml` e `scripts/test-runtime-sync.sh`, com leitura neutra integrada ao painel do `aculta_deployer`. Por decisão do responsável, o checkout compartilhado com Apache é permanente no servidor de desenvolvimento; isolamento F4 e `isolation-plan` foram retirados. `doctor`/`plan` trafegam deltas grandes por arquivo temporário, evitando `E2BIG` quando o JSON excede o limite de argumento do Linux. Veja a arquitetura detalhada em [Runtime Sync v2 - plano de arquitetura e implementacao](RUNTIME-SYNC-PLANO-V2.md).

**Skills de agentes:** `aculta-runtime-sync` para pós-push, diagnóstico e código/cache; `aculta-runtime-migracoes` para Composer, banco, config, restauração e ordem de updates. Ambas são canônicas em `.agents/skills` e acessíveis por Claude e Codex.

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
| Diagnóstico | `bash scripts/runtime-sync.sh doctor [--format=json]` | Nenhuma | Auditar branch, limpeza do checkout compartilhado, lock cooperativo, Drush, `updatedb:status`, `config:status` e `deploy:hook-status` |
| Código e cache | `bash scripts/runtime-sync.sh sync-code [--adopt-baseline] [--format=json]` | `git fetch`, `merge --ff-only`, `drush cr` e estado em `var/runtime-sync/` | Sincronização cotidiana pós-push quando o checkout estiver limpo e o delta for `R0`/`R1` |
| Verificação | `bash scripts/runtime-sync.sh verify [--format=json]` | Relatório em `var/runtime-sync/` | Confirmar SHA, cache validado e saúde Drush após sincronização ou janela estrutural |
| Plano estrutural | `bash scripts/runtime-sync.sh structural-plan [--refresh] [--format=json]` | Nenhuma no Runtime | Gerar checklist ordenado de backup/restore, Composer, `updatedb`, `config:import` e exceções (`commerce_shipping`) para deltas `R2..R4` |
| Fechamento da janela | `bash scripts/runtime-sync.sh structural-complete --plan=<plano.json> [--format=json]` | `drush cr` e `var/runtime-sync/` | Fechar uma janela já executada: exige `ACULTA_SYNC_STRUCTURAL_CONFIRM=1`, HEAD == `origin/dev`, worktree limpo, plano compatível e `updatedb`/deploy/config drift zerados. Não importa config nem roda updatedb. |

O script **nunca** executa `composer install`, `composer update`, `updatedb`, `config:import`, `config:export`, `pm:uninstall`, `DROP`, `git reset`, `git stash` ou `git clean`.

O checkout de desenvolvimento é servido pelo Apache. Edições em `web/` ficam
visíveis durante o trabalho; o modo compartilhado é esperado, não um gate. O
`sync-code` ainda exige árvore limpa, lock livre, ambiente e banco conferidos,
baseline confiável e delta permitido. O lock serializa sincronizadores, não
autores; nunca descartar alterações concorrentes.

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

> Plano da janela vigente (drift de configuração e delta R3): [RUNTIME-WINDOW-PLAN-2026-11-12.md](RUNTIME-WINDOW-PLAN-2026-11-12.md).

1. **Gerar o plano estrutural e o diagnóstico:**

```bash
bash scripts/runtime-sync.sh structural-plan --refresh --format=json
bash scripts/runtime-sync.sh doctor --format=json
```

2. **Congelar a janela e preservar trabalho alheio:** pausar agentes escritores, cron e filas afetadas. Nunca limpar alterações de outros agentes com `reset`/`clean`/`stash`.
3. **Backup e ensaio de restauração:** gerar dump consistente de `aculta_runtime` (`--single-transaction --routines --triggers --hex-blob`) e snapshot dos arquivos persistentes fora do Git e do DocumentRoot (modo `0600`), registrar SHA-256 e testar a restauração e o boot em banco descartável autorizado (`aculta_runtime_copia`, nunca `aculta_simpletest`).
4. **Ordem de execução aprovada:** seguir a sequência de `structural-plan`. Em casos como a remoção de `commerce_shipping`, executar `updatedb` e `pm:uninstall commerce_shipping` aprovado **com o vendor antigo** antes de rodar `composer install`.
5. **Homologação pós-janela:** executar `drush cr`, `bash scripts/runtime-sync.sh verify` e smokes HTTP dos `site_keys` afetados.
6. **Fechamento auditável:** com o checkout em `origin/dev`, worktree limpo e as pendencias zeradas, gerar o plano JSON (`structural-plan --format=json > plano.json`) e executar:

```bash
ACULTA_SYNC_STRUCTURAL_CONFIRM=1 bash scripts/runtime-sync.sh structural-complete --plan=plano.json
```

O comando não executa `config:import` nem `updatedb`: ele confere o plano, exige `updatedb`, deploy hooks e drift de config zerados, reconstrói o cache e avança `last_validated_sha`, limpando `pending_structural_*`. Sem essas condições ele falha fechado e preserva o baseline anterior.

## Fotografia histórica do Runtime (2026-11-13)

`doctor` conectou ao Runtime e confirmou zero updates e zero deploy hooks; apontou
seis drifts de configuração e dez módulos sem schema ativo. `HEAD` e `origin/dev`
estão em `f381f37722cc87ff567819a5f4e81d00adc82967`, mas o último SHA validado e o
último cache válido continuam em `e37f3d8d45ecba9719d4b97dde67effc2236b13f`.
O checkout estava sujo e era compartilhado com o Apache. A decisão vigente de
2026-11-14 mantém esse compartilhamento como modo permanente de desenvolvimento;
ele não é um bloqueio operacional.

O delta cumulativo de 823 caminhos está em `R4`: 221 R0, 553 R1, 2 R2,
43 R3, 4 R4 e zero RX. `.gitignore` e `.stylelintrc.json` foram inspecionados e
incluídos como R0 por regra explícita; T16 fixa essa classificação. Há ainda o
runbook especial de `commerce_shipping`. O plano estrutural lista 37 arquivos de
config e um post-update de `aculta_cgu`. Os estados desta seção são uma
fotografia histórica; consulte `doctor` e `structural-plan` para evidência atual.
Checkout sujo, delta estrutural/indeterminado e pendências Drupal continuam
sendo gates separados e nunca devem ser contornados com `drush cr` manual.
