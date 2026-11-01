# Conteúdo declarado da home e do cabeçalho de projetos (DT-T10)

Fonte versionada do conteúdo que as colocações de bloco da home e do cabeçalho de projetos
referenciam. Os IDs são UUIDs, porque é por eles que `config/sync` liga os blocos às regiões.

## Arquivos

| Arquivo | Função |
| --- | --- |
| `home-content.json` | Conteúdo declarado: 13 blocos `basic` (seções, missão, cabeçalho de projetos), o hero do nó 1 (`nid` 1) e os 3 destaques editoriais publicados da home (`editorial_highlight`, por UUID). |
| `export-home-content.php` | Exporta do Runtime para o JSON. Somente leitura. |
| `load-home-content.php` | Carrega o JSON por UUID. Idempotente. Em dry-run por padrão. |

## Uso

Na raiz do Drupal (`web/`), com o Drush do Composer:

```sh
php ../vendor/drush/drush/drush.php php:script load-home-content --script-path=../scripts/content/institution
ACULTA_APPLY=1 php ../vendor/drush/drush/drush.php php:script load-home-content --script-path=../scripts/content/institution
```

A primeira linha só compara e relata. A segunda grava. Sem `ACULTA_APPLY=1`, nada é escrito.

## Por que UUID

Entidades criadas com o mesmo UUID são as que as colocações de `config/sync` referenciam
(`block.block.aculta_home_mission`, `aculta_projects_header_*` etc.). Buscar por título
quebraria essa ligação se o título mudasse.

## Limites

- Os campos de seção, hero e cabeçalho são configuração (`config/sync`). Um ambiente novo precisa
  importar a configuração antes de carregar o conteúdo; o loader falha se o campo não existir.
- Nós que não existem são criados: o nó da home com o `nid` declarado (`/node/1`, page.front), e os
  destaques pelo UUID. Nó existente com outro UUID é pulado com aviso.
- Verificado em ambiente novo (perfil `standard`, `config/sync` importado): a primeira execução cria os 13 blocos
  e o nó da home; uma execução seguinte cria os 3 destaques e, ao repetir, não altera nada (17 itens
  inalterados). A home mostra 27 de 27 textos declarados.
- Dados institucionais (`aculta_institution`, "Dados oficiais da Associação") estão fora deste
  conteúdo; são criados por `scripts/install-institution.php`.
- Scripts pontuais de migração (`create-fields`, `migrate-sections`, `split-who`,
  `projects-header`) ficam fora do Git: já foram aplicados ao Runtime e são substituídos por este
  JSON.

## Gate

`php scripts/validate-institution-content.php` confere que o JSON declara todo UUID que as
colocações da home e do cabeçalho referenciam, que os UUIDs são únicos e que não sobrou marcado
legado nas seções e no hero.
