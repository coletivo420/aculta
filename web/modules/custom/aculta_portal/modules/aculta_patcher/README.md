# ACULTA Patcher

Submódulo administrativo para registrar diffs destinados a pacotes Composer.
O formulário usa os elementos nativos do Drupal (Claro), controla acesso por
`administer aculta patches` e permite incluir/desativar registros no próximo
build, editar seus metadados e excluí-los.

## Aplicação segura

O painel nunca executa Composer, shell ou grava arquivos em Core/contrib. A
configuração do registro guarda o unified diff; o exportador Drush valida o
pacote e a versão contra `composer.lock`, faz `git apply --check` em uma cópia
temporária dos arquivos alvo e gera `patches-aculta.json` e os arquivos em
`patches/aculta_patcher/`. Esses artefatos precisam ser revisados e commitados;
só então Composer aplica os patches como parte do build. `--check` valida sem
escrever arquivos.

```sh
php vendor/drush/drush/drush.php aculta:patcher-export --check
php vendor/drush/drush/drush.php aculta:patcher-export
composer patches-relock
composer patches-repatch
```

Antes do exportador, use em Configurações > Desenvolvimento > Sincronização de
configuração > Exportar > Item único o item `aculta_patcher.patch.<id>` e salve
o YAML baixado como `config/sync/aculta_patcher.patch.<id>.yml`. Não use
exportação global para contornar diferenças de configuração não relacionadas.
Desativar um registro muda a próxima exportação. Depois de exportar, rode
`composer patches-relock` e `composer patches-repatch` no checkout local; o
release implantado usará `composer install` com o lock atualizado.
Excluir um registro não remove arquivos históricos gerados: a revisão Git
decide quando removê-los. O acesso é restrito porque patches podem introduzir
código executável. Não usar o formulário como editor de produção.

## Patch H5P inicial

O registro `h5p_restricted_upload` valida a permissão `CREATE_RESTRICTED` no
processamento de upload de conteúdo e recusa dependências locais marcadas como
restritas antes da gravação. Ele cobre bibliotecas incluídas e dependências
preloaded/dynamic. O pacote alvo é travado em `h5p/h5p-editor` 1.25; uma versão
diferente exige nova revisão e teste antes de exportar.
