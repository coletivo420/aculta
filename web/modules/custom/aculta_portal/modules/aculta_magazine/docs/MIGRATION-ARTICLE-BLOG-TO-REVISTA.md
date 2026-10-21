# Migração editorial para `revista`

## Escopo e salvaguardas

O comando `aculta-magazine:migrate-publications` recria Nodes `article` e
`blog` pelo Entity API como `revista`, preservando NID, UUID, VID, campos e
aliases Path Alias (ID, UUID, revisão, caminho e alias). A operação não usa
`NodeStorage::updateType()`, SQL de escrita nem o conversor legado.

Por padrão, o comando só valida o estado e mostra quantidades. `--execute`
recusa qualquer host que não seja `toca`, banco que não seja
`aculta_runtime`, URI que não seja o purpose Magazine de teste, dump incompleto
ou backup de configuração ausente. O backup precisa estar fora do checkout,
privado e ter menos de 30 minutos.

A migração só prossegue se cada Node e alias tiver uma revisão, sem traduções,
Comments hospedados, referências de entrada em campos Entity Reference ou
registros Entity Usage. Uma condição nova interrompe o processo antes de
qualquer escrita. A operação ocorre em transação e verifica bundle, IDs, UUIDs,
revisões e aliases antes do commit. Em falha, a transação é revertida.

O Runtime inventariado em 2026-10-21 tem 16 Nodes (11 `article`, 5 `blog`),
uma revisão por Node, 26 aliases com uma revisão cada, zero Comments e nenhuma
referência de entrada detectada. Os campos editoriais de `article`, `blog` e
`revista` têm os mesmos nomes e tipos. Revalidar essa forma no dry run; não
tratar este inventário como autorização para conteúdo de outra instalação.

## Procedimento no Runtime de desenvolvimento

1. Confirmar `hostname`, `pwd`, branch, estado Git e
   `drush status --uri=http://revista420.aculta.toca.net.br`. O resultado deve
   indicar `toca`, `aculta_runtime` e a URI do purpose Magazine.
2. Rodar primeiro o dry run:

   ```sh
   php vendor/drush/drush/drush.php aculta-magazine:migrate-publications --uri=http://revista420.aculta.toca.net.br
   ```

3. Criar backup privado do banco e da configuração ativa em diretórios novos,
   fora do checkout. Restringir permissões antes de continuar:

   ```sh
   umask 077
   php vendor/drush/drush/drush.php sql:dump --gzip --result-file=/tmp/aculta-magazine-before-migration.sql --uri=http://revista420.aculta.toca.net.br
   mkdir -m 0700 /tmp/aculta-magazine-config-before-migration
   php vendor/drush/drush/drush.php config:export --destination=/tmp/aculta-magazine-config-before-migration --uri=http://revista420.aculta.toca.net.br
   chmod -R go-rwx /tmp/aculta-magazine-before-migration.sql.gz /tmp/aculta-magazine-config-before-migration
   gzip -t /tmp/aculta-magazine-before-migration.sql.gz
   ```

4. Repetir hostname, branch, URI, banco, dry run e confirmar que os Nodes
   inventariados continuam os mesmos. O dump deve ter sido criado nos últimos
   30 minutos e permanecer fora do repositório.
5. Executar explicitamente:

   ```sh
   php vendor/drush/drush/drush.php aculta-magazine:migrate-publications --execute --backup-file=/tmp/aculta-magazine-before-migration.sql.gz --config-backup-dir=/tmp/aculta-magazine-config-before-migration --uri=http://revista420.aculta.toca.net.br
   ```

6. Consultar o bundle por NID, UUID e VID, comparar a contagem dos 26 aliases,
   verificar ausência de Nodes legados, abrir páginas públicas por alias,
   revisar watchdog e respostas HTTP. Fazer nova exportação de Config Sync
   somente quando a limpeza de bundles/configuração estiver planejada e isolada.

## Rollback

Se o comando falhar, ele reverte sua transação; validar contagens e HTTP antes
de nova tentativa. Se a migração concluir mas uma verificação posterior falhar,
interromper novas escritas editoriais, guardar os logs minimizados e restaurar
o dump em uma cópia isolada para validar o procedimento. No Runtime autorizado,
restaurar apenas com o runbook operacional e depois de confirmar novamente o
host e banco. Nunca aplicar esse backup em produção nem restaurar sobre dados
mais recentes sem decisão do responsável.

O export de configuração e o dump pós-configuração de F0.2 são backups
anteriores à migração de conteúdo; não substituem o dump novo exigido acima.


## Resultado do Runtime de teste — 2026-10-21

A migração foi executada em `toca`, URI `http://revista420.aculta.toca.net.br`,
com banco `aculta_runtime`, após dump gzip e export completo de configuração
privados. Foram migrados 16 Nodes e 16 revisões; NID, UUID, VID e os 26 aliases
foram preservados. Não havia Comments, referências de entrada ou Entity Usage
nos Nodes inventariados. Nenhum conteúdo ou arquivo compartilhado foi excluído.

Após a contagem zero dos bundles antigos, `article` e `blog` foram removidos via
Entity API. A tela de criação agora tem somente o tipo editorial `revista`.
Backup do banco: `/tmp/aculta-magazine-before-migration-20261021.sql.gz`;
backup de configuração: `/tmp/aculta-magazine-before-migration-20261021-config`.
Esses arquivos são privados do Runtime de teste e não devem ser versionados.
