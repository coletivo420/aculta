# Planejamento de updates, config e recuperação (Runtime Sync v2)

## Gates antes de qualquer escrita persistente

- Host (`toca`), checkout, branch `dev` e remoto (`coletivo420/aculta`) comprovados via `bash scripts/runtime-sync.sh doctor`; banco `aculta_runtime` confirmado no Drush para URI `test`, sem expor segredos.
- Classificação R2/R3/R4/RX e plano operacional gerados com `bash scripts/runtime-sync.sh structural-plan --refresh --format=json`.
- Saídas de `updatedb:status`, `config:status` e `deploy:hook-status` analisadas como dados estruturados e confrontadas com o `drift_baseline` registrado em `var/runtime-sync/state.json`.
- `composer.lock` e versões instaladas conferidos. Proibido rodar `composer update` no Runtime.
- Snapshot consistente fora do Git e do DocumentRoot (diretório 0700, arquivos 0600), hash SHA-256, restore testado em banco descartável autorizado (ex.: `aculta_runtime_copia`, nunca `aculta_simpletest`) e prova de boot com `drush status` e `drush updatedb:status`.
- Agentes escritores e processos concorrentes pausados; operações persistentes nominalmente autorizadas pelo responsável.

## Ordem não universal: exemplo do Commerce (`commerce_shipping`)

O runbook `docs/operations/RUNTIME-UPDATE-WINDOW.md` documenta a remoção do contrib `commerce_shipping`, detectada automaticamente pelo `structural-plan`:

1. Executar `drush updatedb` com o **vendor antigo** que ainda contém `drupal/commerce_shipping` (`aculta_commerce_update_8001`, `migrate_shipping_methods`, `retarget_shipments`).
2. Validar dados migrados, pedidos intactos e `blockersForUninstall()` vazio.
3. Executar `drush pm:uninstall commerce_shipping` **somente com aprovação explícita**.
4. Por último executar `composer install` com o novo `composer.lock`.

Nunca prescreva `composer install -> updatedb -> config:import` como sequência universal. `drush deploy` só pode ser usado quando a sequência padrão inteira for compatível e aprovada, sem uninstall ou migração intercalada.

## Checkout compartilhado com o Apache

- `/home/aculta` aponta para o checkout editado pelos agentes (`/home/piradopirata/projetos/aculta`) e `public_html -> web`; essa composição é intencional no servidor de desenvolvimento.
- Alterações em `web/` podem afetar imediatamente as respostas do Apache. Antes de `sync-code`, preservar trabalho alheio e exigir checkout limpo; após o lock, o script revalida `HEAD` e limpeza antes e depois das operações.
- Não criar worktree separada nem mudar symlinks ou `DocumentRoot` como pré-requisito para o fluxo cotidiano.

## Invariantes de recuperação

- Após falha em `drush cr` depois do fast-forward, o `runtime-sync.sh` registra `PARTIAL` em `var/runtime-sync/state.json` (atualiza `last_code_sync_sha`, mas mantém `last_validated_sha` anterior). Corrigida a causa, repetir `sync-code` reconstrói o cache mesmo com `HEAD == origin/dev`.
- Após falha estrutural (banco/config/arquivos), restaurar apenas o Git não reverte schema nem dados: restaure o conjunto consistente (banco + arquivos persistentes + código/lock/vendor) a partir do snapshot validado.
- Nunca usar `git reset --hard`, `git clean`, `DROP`, `pm:uninstall` não autorizado ou redefinição de secrets como recuperação automática.
