# CTools no ACULTA Portal

O projeto usa CTools 4.1 como API de apoio ao Views, por meio do submódulo
`ctools_views`. O pacote já chegava ao Composer de forma transitiva pelo
Pathauto; agora é dependência direta porque `aculta_portal` exige os módulos
`ctools` e `ctools_views`.

## Uso aplicado

A View `aculta_activities`, display `block_upcoming`, alimenta o bloco
administrativo/editorial "Próximas atividades" em `/atividades`. O display
permite ajustar o tipo de pager e a quantidade de itens por posicionamento do
bloco. O administrador pode, por exemplo, manter a lista completa ou limitar a
quantidade naquela colocação, sem duplicar a View.

Os filtros e ordenação da View continuam sendo a fonte de verdade: somente
atividades publicadas, do tipo `activity`, com início futuro, em ordem
cronológica. CTools não altera acesso, cache, domínio, entidade ou conteúdo. Os
outros blocos Views não ganham opções de configuração até que exista um caso de
uso revisado.

## Limites

- Não usar Page Manager/Panels nem criar páginas paralelas.
- Não migrar formulários multietapas que já pertencem ao Commerce, Webform ou
  Core para a API de wizard do CTools.
- Não habilitar `configure_filters`, `disable_filters`, `hide_fields` ou
  `sort_fields` globalmente: esses controles podem alterar filtros de acesso e a
  apresentação editorial de cada bloco.
- CTools é extensão da experiência de configuração do bloco; a View e o Core
  continuam donos da consulta, access e cache.
