# Matriz de risco, evidências e testes - Runtime Sync v2

Referências: `docs/operations/RUNTIME-SYNC-PLANO-V2.md`, `docs/operations/RUNTIME-SYNC.md` e `docs/operations/GATES.md`. Este arquivo é normativo para **triagem** e descreve o classificador implementado em `scripts/runtime-sync/risk-rules.yml` e `scripts/runtime-sync/helper.py`; não autoriza nenhuma escrita estrutural.

## Classificação cumulativa

| Risco | Evidência | Decisão |
| --- | --- | --- |
| R0 | Só docs/testes/arquivos comprovadamente não consumidos pelo runtime | Atualizar SHA e registrar; dispensa `drush cr` quando o baseline de cache anterior já estiver validado |
| R1 | Alteração isolada de PHP/Twig/JS/CSS/SDC ou código conhecido, sem migration/instalação | Código e `drush cr` com validação e smoke do `site_key` afetado |
| R2 | `composer.json`, `composer.lock`, patches, arquivos `package*.json`, bundles e dependências, bibliotecas compiladas | Bloquear `sync-code`; exigir `structural-plan` e plano de dependências |
| R3 | `config/sync/**`, `*.install`, `*.post_update.php`, `*.deploy.php`, `*.info.yml`, `.permissions.yml`, schema, alterações de entidade/campo | Bloquear; inspecionar `updatedb:status`/`config:status`/`deploy:hook-status` e usar `structural-plan` |
| R4 | Exclusão de entidade/dado/config, `core.extension.yml`, uninstall, DROP, settings, segredos, symlinks públicos, DocumentRoot ou infra | Parar; autorização específica, snapshot comprovado e recuperação |
| RX | Baseline de cache desconhecido (`UNKNOWN`); diff remoto indisponível; renames/gitlinks/paths com newline ou fora do manifesto; estado corrompido | Estado indeterminado; bloquear e investigar (`doctor` / `plan --refresh`) |

**Inspeção cumulativa e semântica:** o classificador v2 usa `git diff --raw -z --find-renames` desde `last_validated_sha` até o commit alvo e inspeciona o conteúdo dos patches para elevar ao maior risco encontrado (hooks de schema/update em `.php`/`.module`, alterações em `core.extension.yml`, exclusões em `config/sync/` e operações destrutivas). Se faltar evidência, classifica como `RX` (fail-closed).

**Mudanças de UI:** tema e SDC exigem rebuild de cache e smoke visual por `site_key`. **Multidomínio:** selecione os hosts de `docs/architecture/multidomain.md` afetados (`affected_site_keys` no JSON); um único `--uri` no Drush não substitui o smoke HTTP dos subdomínios impactados.

## Suíte automatizada de regressão (`scripts/test-runtime-sync.sh`)

1. `HEAD == origin/dev` com último cache ainda pendente -> `plan` alerta pendência e `sync-code` executa `drush cr` e grava `last_validated_sha` (correção do bug do v1).
2. `HEAD == origin/dev` e cache validado -> `NOOP` sem novo `drush cr`.
3. Fast-forward somente de código (R1) e `plan --refresh` -> staging isolado sem tocar HEAD; `sync-code` aplica FF e reconstrói cache uma única vez.
4. Alteração apenas documental (R0) após baseline validado -> atualiza código e dispensa `drush cr` com motivo registrado.
5. `config/sync`, `composer.lock`, `*.install`, hooks, patches, rename -> `BLOCKED` e registra `pending_structural_risk`.
6. Worktree ou index sujo, untracked de outro agente, heads divergentes -> `BLOCKED` sem descartar arquivos.
7. Host/URI/banco errados, domínio de produção (`aculta.org`) ou saída malformada do Drush -> `BLOCKED`.
8. Pendência de `updatedb:status`, `config:status` ou `deploy:hook-status` -> `doctor` captura e normaliza como dados estruturados.
9. Erro em `drush cr` após fast-forward -> `PARTIAL`; atualiza `last_code_sync_sha`, preserva `last_validated_sha` anterior e recupera na reexecução.
10. Duas instâncias simultâneas de `sync-code` -> segunda instância bloqueada imediatamente por `flock`.
11. `state.json` corrompido ou substituído por symlink -> recusado (fail-closed); escrita atômica 0600 e redação de segredos.
12. Cutover de `commerce_shipping` -> `structural-plan` detecta e prescreve `updatedb` + `pm:uninstall` com vendor antigo antes de `composer install`.

## Evidências e limites

- `bash scripts/test-runtime-sync.sh` valida a **orquestração simulada** com Git bare e Drush falso.
- Homologação real requer o servidor `test` (`toca`), checkout compartilhado limpo, outputs Drush sem segredos e smokes por `site_key`. Isolamento entre checkout e Apache não é requisito.
- Estados de gate: `PASS`, `FAIL`, `SKIP`, `DEFERRED`, `N/A`, sempre acompanhados de motivo.
