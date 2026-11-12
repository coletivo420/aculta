---
name: aculta-runtime-migracoes
description: Use quando alterações do ACULTA exigirem Composer, configuração Drupal, updatedb, deploy hooks, migrações, backups, restauração ou troca do checkout servido.
---

# ACULTA - mudanças estruturais supervisionadas

**Gatilho:** qualquer R2, R3, R4 ou RX estrutural da skill `aculta-runtime-sync`; alterações de banco, config, dependências, permissões, settings ou infraestrutura. Não autoriza execução automaticamente.

**Fontes:** `docs/operations/RUNTIME-SYNC-PLANO-V2.md`, `docs/operations/RUNTIME-SYNC.md`, `docs/operations/RUNTIME-UPDATE-WINDOW.md`. Para ordem, aceite e reversibilidade, leia o [runbook de decisão](references/ORDEM-E-RECUPERACAO.md) e a seção exata do módulo afetado.

## Procedimento seguro

1. **Pare na classificação e gere o plano verificável.** Execute `bash scripts/runtime-sync.sh structural-plan --refresh --format=json`. Identifique SHAs, delta cumulativo, módulos/`site_keys` afetados, dependências, hooks, alterações de configuração, flags destrutivas e estado ativo do site.
2. **Confirme ambiente.** Execute `bash scripts/runtime-sync.sh doctor` em desenvolvimento `test`, confirmando origem Git (`coletivo420/aculta`), checkout servido, host real, URI HTTPS de desenvolvimento e banco `aculta_runtime`; preserve segredos, settings, arquivos públicos/privados e trabalho de outros agentes.
3. **Verifique exceções de ordem.** Nem toda janela segue `composer install -> updatedb -> config:import`. No cutover de `commerce_shipping` (`RUNTIME-UPDATE-WINDOW.md`), `updatedb` e `pm:uninstall` aprovado precisam rodar com o **vendor antigo** antes do `composer install`. `structural-plan` detecta esse caso automaticamente.
4. **Backup e restore comprovados.** Antes de updates/config/uninstall, gerar snapshot consistente fora do Git e docroot (modo 0600), checksum SHA-256, prova de restauração em destino descartável **autorizado** (como `aculta_runtime_copia`), teste de bootstrap e comparação de contagens. O banco `aculta_simpletest` nunca guarda cópia do Runtime.
5. **Obtenha ordem explícita.** Push, classificação RC, `structural-plan` e autorização de sincronizar código **não autorizam** `updatedb`, `config:import`, `pm:uninstall`, exclusões, migrações destrutivas ou cutover de DocumentRoot (`isolation-plan`).
6. **Execução apenas na janela aprovada.** Reconfirme `doctor` imediatamente antes de escrever; pause agentes/cron/filas afetadas. `composer install` usa lock (nunca `composer update`). Execute só operações autorizadas e registre efeitos sem expor credenciais.
7. **Homologue e recupere.** Após concluir a janela e `drush cr`, rode `bash scripts/runtime-sync.sh verify` (ou `sync-code --adopt-baseline` com `ACULTA_SYNC_ADOPT_BASELINE=1` quando homologado) e smoke dos dez `site_keys`; em falha, pare em `PARTIAL`/`FAILED` e restaure o conjunto consistente.
8. **Handoff.** Registre SHAs, gates e resultados reais, evidências de backup/restore, bloqueios, pendências e próximos passos. Sem segredos, dados pessoais ou caminhos absolutos em documentos versionados.

## Fronteira com o Deployer

A CLI standalone de `aculta_deployer` não depende de Drush/Drupal/vendor e o seu `var/deployer/status.json` mantém o schema 1 intacto. O painel administrativo (`/painel-administrativo/aculta/deployer`, restrito a MAIN e `administer aculta deployer`) consome `var/runtime-sync/state.json` e `latest.json` (schema 2) de forma **somente leitura** via `DeployerStatusReader::readRuntimeSync()`.

**Não existe executor autônomo (`structural-apply`).** Mudanças estruturais e o cutover real da worktree F4 (`isolation-plan`) exigem aprovação explícita do responsável.

## Banco de ensaio da cópia do Runtime

- Use `aculta_runtime_copia` para restaurar o snapshot antes de qualquer `updatedb` ou `config:import` estrutural. O usuário da aplicação `aculta_runtime`@`localhost` já tem `GRANT` nesse banco, então não é preciso pedir permissão de novo.
- `aculta_simpletest` nunca guarda cópia do Runtime. Detalhes em `docs/operations/BANCO-DE-TESTE.md`.
