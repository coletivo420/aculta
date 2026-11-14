# ACULTA - Runtime Sync v2: plano de arquitetura e implementacao

**Data:** 2026-11-11  
**Status:** F0-F3 e F5-F6 implementados (`scripts/runtime-sync.sh`, `scripts/runtime-sync/helper.py`, `scripts/runtime-sync/risk-rules.yml`, `scripts/test-runtime-sync.sh` e adaptador somente leitura do `aculta_deployer`). Em 2026-11-14, o responsável confirmou o checkout compartilhado com Apache como modo permanente; F4/isolation-plan foi retirado. Mudanças estruturais no Runtime continuam sob plano, recuperação e autorização por operação.
**Escopo:** somente desenvolvimento `*.aculta.toca.net.br`, branch `origin/dev`. Nao e deploy de producao.  
**Base:** `scripts/runtime-sync.sh`, `docs/operations/RUNTIME-SYNC.md`, `AGENTS.md`, `docs/architecture/environments.md`, `docs/architecture/multidomain.md` e contratos do `aculta_deployer`.

## 0. Diagnostico fundamentado e Auditoria F0 (2026-11-11)

1. **Bug de cache em SHA coincidente (corrigido em F1):** quando o agente fazia commit/push no checkout servido e `HEAD == origin/dev`, o script v1 saia cedo sem `drush cr`. O v2 separa `checkout_sha`, `last_code_sync_sha`, `last_cache_success_sha` e `last_validated_sha`, reconstruindo o cache quando `last_cache_success_sha != target` e retornando `NOOP` quando ja validado.
2. **Fotografia tecnica do Runtime real (F0 auditado em `toca`):**
   - **Checkout e symlinks:** `/home/aculta` aponta para `/home/piradopirata/projetos/aculta`; `public_html -> web`; `private -> /home/aculta-system-data/private`. Por decisão do responsável (2026-11-14), o checkout compartilhado com Apache é permanente e esperado; não há cutover de isolamento planejado.
   - **Remoto Git:** `origin` aponta para `git@github.com:coletivo420/aculta.git` (`fetch`/`push`). `TODO.md` raiz ainda mencionava pendencia historica de `coletivo420/aculta-site`, reconciliada na documentacao.
   - **Stack e banco:** PHP 8.5.12 CLI, Composer 2.8.8, Drush 13.8.0.0, Drupal 11.5.0-beta1, tema padrao `aculta420_v2`, tema admin `gin`, banco `aculta_runtime` conectado (`Drupal bootstrap: Successful`). Nota: em Drush 13.8 o campo se chama `bootstrap` (e nao `drupal-bootstrap`).
   - **Pendencias estruturais e drift medidos:** `updatedb:status` reporta 1 update pendente (`media_duplicates_update_11001`); `deploy:hook-status` reporta `[]` (0 pendentes); `config:status` reporta drift preexistente (`aculta420_v2.settings`, `aculta_forum.settings`, blocos `default_admin_*`, etc.) e avisos de entradas orfas em `system.schema` (`aculta_contributions`, `aculta_forum_lms`, `aculta_forum_wiki`, `aculta_lms_agenda`, `aculta_lms_resources`, `aculta_mail_confirmer_user`, `aculta_ugc`, `jquery_ui*`).
   - **Concorrencia observada:** multiplas sessoes paralelas de agentes e 24 worktrees listadas pelo Git; o checkout compartilhado continha arquivos modificados e diretórios `tests/browser/*` nao rastreados de outros agentes, preservados integralmente e que bloqueiam legitimamente `sync-code` no checkout compartilhado.

## 1. Contratos e invariantes implementados

- **Fonte de codigo:** `origin/dev` autenticado e validado por regex allowlist (`coletivo420/aculta`), SHA imutavel por operacao; nao aceita refs arbitrarias.
- **Integridade:** sem `reset --hard`, `clean`, `stash`, `checkout -f`, force push, sobrescrita de arquivos alheios, escrita em settings ou segredos.
- **Fail-closed:** falha/indisponibilidade de host, URI, banco, Drush, Git, deteccao de risco, estado corrompido ou symlink inesperado bloqueia a escrita (`BLOCKED` / `INDETERMINATE`). Dominios de producao (`*.aculta.org`) sao estritamente recusados.
- **Estado persistente v2 (`var/runtime-sync/`):** fora do Git e fora do `DocumentRoot` (`web/`), diretorio `0700`, arquivos `0600` (`state.json`, `latest.json` e `runs/<run_id>.json`), escrita em temporario + `os.replace` + `fsync`, verificacao anti-symlink e redacao automatica de credenciais/DSNs/e-mails.

## 2. Interface CLI implementada (F1-F5)

| Comando | Escrita | Contrato |
| --- | --- | --- |
| `plan [--format=json]` | Nenhuma | Remoto, local, `last_validated_sha`, `last_cache_success_sha`, divergencia e risco cumulativo; desconhecido explicitamente (`INDETERMINATE`) |
| `plan --refresh [--format=json]` | Apenas staging temporario `0700` fora do checkout, removido ao sair | Busca objetos remotos com `objects/info/alternates` sem alterar HEAD, refs locais, index ou worktree |
| `doctor [--format=json]` | Nenhuma | Confere ambiente, branch, checkout limpo, lock cooperativo, baseline, Drush, `updatedb:status`, `config:status` e `deploy:hook-status` |
| `status [--format=json]` | Nenhuma | Le `var/runtime-sync/state.json` sem rede e sem Drush; indica `VALIDATED`, `PARTIAL`, `INDETERMINATE` ou `UNKNOWN` |
| `sync-code [--adopt-baseline] [--format=json]` | Git FF quando aplicavel, `drush cr`, relatorio v2 | Executa codigo e cache com lock cooperativo e gates; reconstrói cache mesmo quando `HEAD == origin/dev` se pendente; retorna `NOOP` se ja validado |
| `verify [--format=json]` | Relatorio em `var/runtime-sync/` | Confere SHA, cache validado e Drush pos-operacao |
| `structural-plan [--refresh] [--format=json]` | Nenhuma no Runtime | Gera plano verificavel por etapas para mudancas R2-R4, incluindo caso especial do `commerce_shipping` |
| `structural-complete --plan=<plano.json> [--format=json]` | `drush cr` e estado em `var/runtime-sync/` | Fecha a janela ja executada: valida confirmacao explicita, plano, HEAD/origin, worktree limpo e zeragem de updatedb/deploy/config drift; avanca o baseline e limpa `pending_structural_*` |

## 3. Classificador cumulativo de risco (F2)

Regras declaradas em `scripts/runtime-sync/risk-rules.yml` e avaliadas por `scripts/runtime-sync/helper.py` usando `git diff --raw -z --find-renames` e inspecao semantica dos patches:

| Nivel | Escopo e deteccao | Acao em `sync-code` |
| --- | --- | --- |
| R0 - documental | `docs/**`, `.agents/**`, `.claude/**`, `.codex/**`, `tests/**`, `README.md`, `TODO.md`, `CHANGELOG.md` | Permite sync; dispensa `drush cr` se o baseline de cache anterior ja estava validado |
| R1 - codigo/cache | PHP, Twig, CSS, JS, SDC e scripts sem hooks estruturais novos | Permite sync; exige `drush cr` e smoke dos `site_keys` afetados |
| R2 - dependencias | `composer.json`, `composer.lock`, patches, `package*.json`, `web/libraries/**`, mudanca de modo | Bloqueia (`BLOCKED`); requer `structural-plan` |
| R3 - Drupal estrutural | `config/sync/**`, `*.install`, `*.post_update.php`, `*.deploy.php`, `*.info.yml`, `.permissions.yml`, schemas, hooks de schema/update em `.php`/`.module`, remocao de `.services.yml`/`.routing.yml` | Bloqueia (`BLOCKED`); requer `structural-plan` |
| R4 - destrutivo/infra | `web/sites/**`, `secrets/**`, `private/**`, `.htaccess`, `core.extension.yml`, remocao em `config/sync/`, `dropTable`/`uninstall` em hooks, cutover `commerce_shipping` | Bloqueia (`BLOCKED`); requer janela supervisionada, snapshot e restore testado |
| RX - indeterminado | Baseline `UNKNOWN` nao adotado, diff ausente, gitlink/submodulo, newline em caminho ou arquivo fora das regras | Bloqueia (`BLOCKED` / `INDETERMINATE`) |

## 4. Coordenacao de Runtime Sync no checkout compartilhado (F3)

- **Ordem unica de locks:** `var/deployer/runtime-sync.lock` via `flock -n` antes de qualquer preflight Drush ou escrita Git, seguido da escrita atomica em `var/runtime-sync/`.
- **Idempotencia e debounce:** `git fetch` dentro da secao critica captura o SHA mais recente de `origin/dev`; se outro processo ja tiver atualizado e validado o cache para o mesmo SHA, a releitura de `state.json` apos o lock resulta em `NOOP` sem segundo `drush cr`.
- **Auditoria antes e depois da escrita:** compara `HEAD` e `git status --porcelain=v1` antes do fast-forward e imediatamente apos `drush cr`. Modificacao concorrente durante a execucao registra `PARTIAL` e falha fechado.
- **Checkout compartilhado:** arquivos do checkout que o Apache serve podem afetar requisições imediatamente, inclusive antes do commit. Isso é esperado. O preflight exige checkout limpo para iniciar `sync-code`, e a sincronização mantém lock e revalida alterações concorrentes; não se exige worktree isolada.

## 5. Integracao somente leitura com ACULTA Deployer (F6)

- `var/deployer/status.json` permanece no `schema: 1`, exclusivo da CLI standalone do Deployer.
- `DeployerStatusReader::readRuntimeSync()` le `var/runtime-sync/state.json` e `latest.json` (`schema_version: 2`), recusando symlinks ou schemas invalidos, sem executar comandos nem introduzir dependencia de Drupal/Drush/vendor em `modules/aculta_deployer`.
- `DeployerStatusController` exibe no painel administrativo (`/painel-administrativo/aculta/deployer`, restrito ao dominio `main` e a permissao `administer aculta deployer`) os SHAs local/remoto/validado, situacao do cache, risco classificado, pendencias estruturais, gates da ultima execucao e historico resumido.

## 6. Suite automatizada e avaliacao das skills (F1-F6)

- No histórico desta arquitetura, `bash scripts/test-runtime-sync.sh` executava 34 asserções dos cenários T01 a T16 com repositórios Git fictícios, remoto bare e Drush simulado. T15 cobria delta de 1.400 caminhos sem `E2BIG`; T16 cobria a regra R0 de `.gitignore` e `.stylelintrc.json`. A alteração de remoção do fluxo de isolamento ainda requer revisão dos gates de shell e revalidação da suíte antes de declarar PASS.
- `docs/operations/TESTES-SKILLS-RUNTIME-SYNC.md` registra a avaliacao completa dos 12 cenarios RS-01 a RS-12 confrontada com os comandos v2 implementados.
