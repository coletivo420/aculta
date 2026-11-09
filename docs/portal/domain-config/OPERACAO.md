# Operação — Domain Config

## Cache

- Após alterar `domain_config_entity_ui.settings` (tipos cobertos), o próprio
  módulo invalida as definições de tipo de entidade; `drush cr` é seguro.
- Após alterar overrides pela interface, o `domain_config_ui` grava na coleção e
  invalida as tags de configuração. Se o front-end não refletir, executar
  `drush cr` no Runtime de desenvolvimento.
- O contexto de cache `domain` isola o valor por domínio. Não remova esse
  contexto de `required_cache_contexts` em `services.yml`.
- Teste de isolamento: aquecer um domínio, abrir outro, repetir na ordem
  inversa. Ver [TESTES.md](TESTES.md).

## Config Sync

`drush cex` exporta as coleções como subdiretórios:

```
config/sync/
  domain/
    conta_aculta_org/
      block.block.<id>.yml
      system.site.yml
```

Nesta entrega, por causa das divergências prévias do Runtime, **não** foi
executado `drush cex` sobre `config/sync`. Os arquivos que precisam entrar no
versionamento desta mudança são:

- `config/sync/core.extension.yml` — módulos `domain_config_ui`,
  `domain_config_entity_ui`, `domain_config_switcher` e
  `aculta_domain_config`.
- `config/sync/domain_config_entity_ui.settings.yml` —
  `covered_entity_types: [block]`.
- `composer.json` e `composer.lock` — pacotes Domain/Extras.

Para conferir o que será exportado sem gravar no repositório:

```bash
drush config:export --destination=/mnt/disco1/tmp/aculta-cex -y
find /mnt/disco1/tmp/aculta-cex/domain -maxdepth 2 -type f
```

## Importação

Em um ambiente de staging limpo:

```bash
drush config:import --source=/caminho/do/export -y
```

A importação no Runtime atual está **DEFERRED**: há divergências de configuração
anteriores que não pertencem a esta entrega. Não execute `drush cim` no Runtime
sem reconciliar o drift primeiro.

## Rollback

1. Restaurar o banco:

   ```bash
   drush sql:cli < /mnt/disco1/tmp/aculta-backups/aculta_runtime-20261109-183554.sql
   ```

2. Desabilitar a ponte e os módulos Domain Config, se necessário:

   ```bash
   drush pm:uninstall aculta_domain_config domain_config_switcher \
     domain_config_entity_ui domain_config_ui -y
   drush cr
   ```

3. Remover os pacotes do Composer (`drupal/domain_config_entity_ui`,
   `drupal/domain_config_ui`, `drupal/domain_config`, `drupal/domain_extras`)
   somente depois de confirmar que nenhum código custom os usa.

4. Overrides são removidos por domínio com "Remove domain configuration". Eles
   não apagam a entidade global.

## Limitações conhecidas

- `domain_config_entity_ui` é **experimental** (alpha, lifecycle experimental).
  A cobertura está restrita a `block`.
- Somente blocos existentes recebem override. Criar uma nova entidade de bloco
  por domínio não é suportado; crie no MAIN e faça override de posição/visibilidade.
- O `domain_config_switcher` cobre `ConfigFormBase` com `#config_target`. A
  ponte do ACULTA cobre o Layout de Blocos; outros `EntityForm` (view modes,
  search pages) não estão cobertos.
- Tradução de configuração por domínio não é retargetada pelo switcher.
- A administração permanece no MAIN; subdomínios continuam respondendo 404 nas
  rotas administrativas.
- Blocos de menu podem exigir `domain_menu_extras` se o cache de descoberta do
  menu vazar entre domínios. Não foi habilitado nesta rodada; avalie se houver
  override de label/itens de menu.

## Manutenção dos tipos cobertos

Painel: `/painel-administrativo/configuracoes/domain/config-entity-ui`.

- O formulário lista apenas tipos de entidade cujo storage o submódulo consegue
  trocar com segurança.
- O padrão é vazio. Manter **somente `block`** nesta fase.
- Alterar a seleção invalida o cache de definições; não é necessário `drush cr`,
  mas ele é seguro.

## Órfãos do Runtime

Antes desta entrega, `core.extension` tinha `aculta_forum_lms` e
`aculta_mail_confirmer_user` sem código no repositório. As entradas foram
removidas apenas do Runtime para permitir `drush en`. Não recrie módulos com
esses nomes sem restaurar o código correspondente. O aviso de
`jquery_ui_resizable` em `system.schema` é prévio e não bloqueia a operação.
