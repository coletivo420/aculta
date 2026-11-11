# Planejamento de updates, config e recuperação

## Gates antes de qualquer escrita persistente

- Host, checkout, branch/ref e Git remotos comprovados; banco obtido do Drush para URI `test` correta, sem mostrar segredo.
- Classificação R2/R3/R4 com diff semântico completo e risco de exclusões/renomes.
- Saídas `updatedb:status`, `config:status`, `deploy:hook-status` (se suportado na versão real) analisadas quanto à existência de pendências e baseline.
- `composer.lock` e versões instaladas conferidos. Não usar Composer para atualizar versões não travadas.
- Extensões Drupal, `core.extension`, schema de entidades, campos, recipientes de configuração e filas/cron auditados.
- Snapshot consistente, hash, escopo de arquivos persistentes, opção de recuperação, restore em banco descartável nomeado/autorizado e boot de ensaio válidos. Não presumir que backup de banco cobre vendor, symlinks ou uploads.
- Agentes escritores e processos afetados coordenados; manutenção do Apache/Drupal apenas com decisão local autorizada.
- Operações persistentes explicitamente aprovadas pelo responsável, com escopo, ordem, critério de parada e rollback.

## Ordem não universal: exemplo do Commerce

O runbook `docs/operations/RUNTIME-UPDATE-WINDOW.md` registra caso de remoção do contrib `commerce_shipping`: primeiro executar migrações com o **vendor antigo** que ainda oferece o pacote; validar dados e entidades; depois `pm:uninstall commerce_shipping` **somente com aprovação específica**; por último instalar dependências travadas no novo lock. Aplicar `composer install` de início poderia remover código requerido pela migração.

Portanto, não prescreva `composer install -> updatedb -> cim` de forma universal; revise todos os hooks e possíveis dependências antes de estabelecer a sequência. `drush deploy` só quando o encadeamento embutido for compatível e expressamente aprovado. A skill não executa a migração por si mesma.

## Invariantes de recuperação

- Após falha estrutural, o conjunto a restaurar pode incluir **banco + arquivos persistentes + código/lock/vendor + config + chaves compatíveis** no mesmo checkpoint. Restaurar só Git não reverte schema nem dados.
- Nunca usar `git reset --hard`, `git clean`, `DROP`, `pm:uninstall`, `composer update` ou redefinição de secrets como mecanismo automático de recuperação.
- Se o restore não estiver comprovado, **bloquear a escrita** até aprovação operacional. `deferred` é resultado legítimo, nunca PASS.
- Não presumir que um banco de teste de Kernel ou um snapshot antigo seja válido para o ensaio corrente. Exceções históricas em runbook não criam política geral.
- Logs ficam fora do Git; status para painel só com nomes, contagens, hashes e gates sanitizados. Evitar comandos com senha na linha de comando.
