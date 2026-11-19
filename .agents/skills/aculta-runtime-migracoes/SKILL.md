---
name: aculta-runtime-migracoes
description: Use quando alterações do ACULTA exigirem Composer, configuração Drupal, updatedb, deploy hooks, migrações, backups, restauração ou troca do checkout servido.
---

# ACULTA - mudanças estruturais supervisionadas

**Gatilho:** qualquer R2, R3, R4 ou RX estrutural da skill `aculta-runtime-sync`; alterações de banco, config, dependências, permissões, settings ou infraestrutura. Não autoriza execução automaticamente.

**Fontes:** `docs/operations/RUNTIME-SYNC-PLANO-V2.md`, `RUNTIME-SYNC.md` e `RUNTIME-UPDATE-WINDOW.md`. Para ordem e reversibilidade, leia o [runbook](references/ORDEM-E-RECUPERACAO.md) e a seção do módulo afetado.

## Procedimento seguro

1. **Classifique e gere o plano.** Rode `bash scripts/runtime-sync.sh structural-plan --refresh --format=json` e identifique SHAs, delta, módulos/`site_keys`, hooks, config, flags destrutivas e estado do site.
2. **Confirme ambiente.** `doctor` em `test`: origem `coletivo420/aculta`, checkout servido, host, URI HTTPS e banco `aculta_runtime`; preserve segredos e trabalho alheio.
3. **Confira exceções de ordem.** No `commerce_shipping`, `updatedb` e `pm:uninstall` precisam do **vendor antigo** antes do `composer install`; `structural-plan` detecta.
4. **Prove backup e restore.** Snapshot fora do Git/docroot (0600) com SHA-256, prova em destino autorizado (`aculta_runtime_copia`), bootstrap e contagens. `aculta_simpletest` nunca guarda o Runtime.
5. **Tenha ordem explícita.** Push, `structural-plan` e autorização de código não autorizam `updatedb`, `config:import`, `pm:uninstall` ou exclusões persistentes.
6. **Escreva só na janela aprovada.** Reconfirme `doctor`, pause agentes/cron e use `composer install` com lock (nunca update).
7. **Homologue e feche.** Após `drush cr`, rode `verify` e smoke dos dez `site_keys`. Com HEAD == `origin/dev`, worktree limpo, plano compatível e drift/updatedb/deploy zerados, o responsável roda `ACULTA_SYNC_STRUCTURAL_CONFIRM=1 … structural-complete --plan=<plano.json>`: não importa config nem roda updatedb, só valida, reconstrói cache e avança o baseline. Em falha, pare em `PARTIAL`/`FAILED` e restaure.
8. **Handoff.** Registre SHAs, gates, evidências de backup/restore e pendências, sem segredos ou caminhos absolutos.

## Fronteira com o Deployer

A CLI do `aculta_deployer` é standalone e o `var/deployer/status.json` mantém o schema 1; o painel administrativo consome `var/runtime-sync/state.json` e `latest.json` (schema 2) via `DeployerStatusReader::readRuntimeSync()`, somente leitura.

**Não existe executor autônomo (`structural-apply`).** `structural-complete` só fecha janela já executada, com confirmação e gates zerados; não executa mudança estrutural. O checkout compartilhado com Apache é o modo esperado no servidor de desenvolvimento.

## Banco de ensaio da cópia do Runtime

- Use `aculta_runtime_copia` para restaurar o snapshot antes de `updatedb`/`config:import`; o usuário de `aculta_runtime` já tem `GRANT`.
- `aculta_simpletest` nunca guarda cópia do Runtime (ver `docs/operations/BANCO-DE-TESTE.md`).
