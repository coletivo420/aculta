---
name: aculta-runtime-migracoes
description: Use quando alterações do ACULTA exigirem Composer, configuração Drupal, updatedb, deploy hooks, migrações, backups, restauração ou troca do checkout servido.
---

# ACULTA - mudanças estruturais supervisionadas

**Gatilho:** qualquer R2, R3, R4 ou RX estrutural da skill `aculta-runtime-sync`; alterações de banco, config, dependências, permissões, settings ou infraestrutura. Não autoriza execução automaticamente.

**Fontes:** `docs/operations/RUNTIME-SYNC-PLANO-V2.md`, `docs/operations/RUNTIME-SYNC.md`, `docs/operations/RUNTIME-UPDATE-WINDOW.md`. Para ordem, aceite e reversibilidade, leia o [runbook de decisão](references/ORDEM-E-RECUPERACAO.md) e a seção exata do módulo afetado.

## Procedimento seguro

1. **Pare na classificação.** Identifique os SHAs, o delta cumulativo, módulos/sites afetados, versão exata de dependências, hooks, alterações de configuração, flags destrutivas, dados/filas/cron afetados, estado ativo do site e impacto de incompatibilidade.
2. **Confirme ambiente.** Desenvolvimento `test`, origem do Git, checkout servido, host real, URI HTTPS de desenvolvimento e banco `aculta_runtime`; preserve segredos, settings, arquivos públicos/privados e trabalho de outros agentes.
3. **Faça um plano, não uma suposição.** Registre ordem de aplicação e comandos concretos, pré-requisitos, modo de manutenção, bloqueio de writers/queues, contingência, reversão e impacto nos dez site_keys. `drush deploy` não é sempre adequado: hooks e remoções de pacotes podem exigir uma ordem não convencional.
4. **Backup e restore comprovados.** Antes de updates/config/uninstall, gerar snapshot consistente fora do Git e docroot, checksum, prova de restauração em destino descartável **autorizado**, teste de bootstrap e comparação. Snapshot sem restore testado não basta. O banco `aculta_simpletest` não é destino por padrão.
5. **Obtenha ordem explícita.** Push, classificação RC, estado do commit e autorização de sincronizar código **não autorizam** `updatedb`, `config:import`, `pm:uninstall`, exclusões, migrações destrutivas ou alteração de infraestrutura. Documento plano também não é autorização.
6. **Execução apenas na janela aprovada.** Reconfirme os gates imediatamente antes de escrever; pause agentes/cron/filas afetadas. `composer install` deve usar lock e depender da sequência validada. Executar só operações autorizadas e registrar efeitos, sem expor credenciais.
7. **Homologue e recupere.** Drush bootstrap, schema, config, hooks, PHP, rotas e smoke dos hosts afetados; em falha, pare e sinalize PARTIAL/FAIL. Nunca tente rollback de banco com `git reset` ou SQL improvisado; restaurar conjunto consistente conforme plano e autorização.
8. **Handoff.** Registre SHAs, gates e resultados reais, evidências de backup/restore, bloqueios, pendências e próximos passos. Sem segredos, dados pessoais ou caminhos absolutos em documentos versionados.

## Fronteira com o Deployer

A CLI standalone de `aculta_deployer` não depende de Drush/Drupal/vendor e o seu `var/deployer/status.json` tem schema próprio. Não introduza esse acoplamento. Se publicar histórico no painel, forneça um adaptador **somente leitura**, neutro, validado em boundary e permissões.

**Não existe executor v2 de migrações autônomas neste momento.** Conclua primeiro F0-F4 do plano, incluindo isolamento entre checkout de agentes e DocumentRoot, antes de propor disparos automáticos.
