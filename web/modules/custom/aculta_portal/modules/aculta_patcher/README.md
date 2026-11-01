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

O exportador recusa `patches/` ou `patches/aculta_patcher/` quando forem
symlinks, para manter os artefatos dentro da raiz do projeto. Também valida os
ancestrais dos arquivos fonte e faz preflight dos destinos do manifesto e dos
patches antes de escrever.

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

## Verificação de ciclo e prontidão — 2026-10-28

- Suíte Unit: 41 testes / 73 assertions PASS; inclui comparação byte a byte
  dos artefatos em duas execuções e regressões para symlink no pacote, no
  manifesto e no arquivo de patch.
- PHP lint: 12 arquivos PASS; PHPCS Drupal no módulo PASS.
- O comando `aculta:patcher-export --check` no Runtime de teste validou o
  registro ativo sem gravar artefatos.
- Em cópia isolada do projeto, `composer patches-relock` e duas execuções de
  `composer patches-repatch` passaram com rede desabilitada. Quatro pacotes
  patchados, `composer.lock`, `patches.lock.json`, `.htaccess` e assets de
  Bootstrap tiveram hashes iguais após a segunda execução.
- Firefox ESR autenticado no Runtime de teste após o cache rebuild de dev.4:
  13/13 verificações passaram. Lista e formulário abriram; o patch H5P, a ação
  de inclusão e campos mínimos aparecem. Títulos visíveis e sem overflow em
  1280/390/320px, com o menu lateral recolhido durante as medições.
- Runtime de teste: a rota da coleção exige `administer aculta patches`; a
  conta comum foi negada e o papel administrator autorizado. O comando
  `aculta:patcher-export --check` não grava artefatos. Nenhum artefato foi
  exportado no Runtime e nenhum `composer require`/`composer update` foi feito.

## Revisão de RC — 2026-10-29

- Candidato promovido a `0.1.0-rc.1`: Unit 41/73 PASS, PHP lint 12/12 e PHPCS Drupal PASS. O ciclo completo com registros ativos foi repetido em cópia isolada, com rede desabilitada; a segunda execução permaneceu byte a byte estável.
- Firefox ESR visível (`DISPLAY=:0`) no servidor de desenvolvimento (`tests/browser/aculta-patcher/overview.py`), servido no head da PR com cache reconstruído: 13/13 verificações PASS para listagem e formulário em 1280/390/320px. Sem overflow horizontal e com títulos visíveis; seletor semântico `patcher-table-wrapper` validado no markup servido.
- Gate módulo-tema validado no repositório; critérios de RC concluídos e componente pronto para deploy Alpha.

## Patch H5P inicial

O registro `h5p_restricted_upload` valida a permissão `CREATE_RESTRICTED` no
processamento de upload de conteúdo e recusa dependências locais marcadas como
restritas antes da gravação. Ele cobre bibliotecas incluídas e dependências
preloaded/dynamic. O pacote alvo é travado em `h5p/h5p-editor` 1.25; uma versão
diferente exige nova revisão e teste antes de exportar.


## Reversão

A reversão do build é o revert do commit que atualizou manifesto, patches e
composer.lock, seguido de composer install a partir do lock anterior. No
checkout administrativo, desative o registro antes de uma nova exportação; o
próximo manifesto deixa de incluir o diff. Não executar Composer no Runtime.
