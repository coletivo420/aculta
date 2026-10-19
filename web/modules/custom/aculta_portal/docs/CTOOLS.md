# CTools no ACULTA Portal

CTools 4.1 é uma implementação suportada do projeto e deve ser usada quando a
necessidade corresponder às capacidades disponíveis, após conferir o código
instalado e as referências upstream. Hoje, `ctools_views` configura paginação e
quantidade de itens nos blocos de próximas atividades e tópicos recentes do
Fórum; a integração H5P do LMS usa a API de condições/plugins do CTools. O
`aculta_portal` declara a
dependência de `ctools` e `ctools_views`; o submódulo H5P declara `ctools`.
Além disso, o Composer já recebe CTools transitivamente por dependências de
terceiros. Remover o pacote exige primeiro migrar esses consumidores e revisar
a árvore Composer.

O `ctools_entity_mask` está habilitado na configuração versionada, mas nenhum
tipo de entidade do projeto declara atualmente uma máscara. Ele não é necessário
para os dois usos acima; sua desativação pode ser considerada separadamente,
depois de confirmar o estado de configuração do Runtime e avaliar o histórico
da decisão que o habilitou.

## Uso aplicado

A View `aculta_activities`, display `block_upcoming`, alimenta o bloco
administrativo/editorial "Próximas atividades" em `/atividades`. O display
permite ajustar o tipo de pager e a quantidade de itens por posicionamento do
bloco. O administrador pode, por exemplo, manter a lista completa ou limitar a
quantidade naquela colocação, sem duplicar a View.

Os filtros e ordenação da View continuam sendo a fonte de verdade: somente
atividades publicadas, do tipo `activity`, com início futuro, em ordem
cronológica. CTools não altera acesso, cache, domínio, entidade ou conteúdo.

A View `aculta_forum_topics`, base `node_field_data`, apresenta tópicos do tipo
`forum` publicados e associados ao domínio corrente. Ela usa o teaser do Node,
ordena fixos primeiro e depois por criação, e fica posicionada apenas na página
inicial do purpose `forum`. O bloco permite ao administrador ajustar quantidade
de itens e tipo de pager. A consulta mantém o query tag `node_access` do Views e
o contexto de cache `user.node_grants:view`; filtros de tipo, domínio e
publicação não podem ser alterados por configuração do bloco. A listagem nativa
do Forum continua sendo a fonte das categorias e dos tópicos dentro de cada
categoria.

A View `aculta_forum_public_topics` é a contraparte do motor novo: filtra apenas
`aculta_forum_topic` publicado e `field_domain_source` do domínio ativo, mantém
SQL rewrite do Core/`node_access` e usa o teaser do Node. O Page display serve a
raiz do purpose `forum` e oferece o filtro exposto de categoria do vocabulário
`aculta_forum_categories`, com hierarquia e variação de cache por query string.
O display de bloco permite somente ajustar quantidade e pager via CTools Views;
filtros de tipo, domínio, publicação e vocabulário continuam fixos. Esse bloco
novo ainda não foi colocado; a rota antiga e o bloco da View contrib permanecem
em serviço até o corte seguro, sem misturar os dois acervos.

A View `aculta_shop_catalog` apresenta produtos publicados do tipo `produto_loja`.
Sua página `/produtos` e os detalhes de produto pertencem ao purpose SHOP. O
bloco `block_catalog` deve ser colocado somente em páginas desse purpose. CTools
permite ajustar por posicionamento a quantidade de itens e o pager; os filtros
internos de tipo/publicação permanecem fixos, sem controles para filtros,
campos ou ordenação. O catálogo usa o view mode Commerce `catalog`, que mostra
título, resumo e o formulário de compra das variações.

## Limites

- Ao criar um bloco Views que precisa de ajustes por colocação, use CTools Views
  para quantidade de itens e pager em vez de implementar controles paralelos no
  Portal. A regra vale para qualquer feature adequada, mantendo a configuração
  e a documentação específicas da View.
- Não usar Page Manager/Panels nem criar páginas paralelas.
- Não migrar formulários multietapas que já pertencem ao Commerce, Webform ou
  Core para a API de wizard do CTools.
- Não habilitar `configure_filters`, `disable_filters`, `hide_fields` ou
  `sort_fields` globalmente: esses controles podem alterar filtros de acesso e a
  apresentação editorial de cada bloco.
- CTools é extensão da experiência de configuração do bloco; a View e o Core
  continuam donos da consulta, access e cache.
- No LMS, a condição CTools de grupo é uma integração de contexto para
  atividades H5P; ela não substitui permissões do Drupal nem controla acesso a
  submissões Webform.
- A View do Fórum não usa AJAX; o pager e os overrides de bloco permanecem
  configurados no Views/CTools e não alteram filtros de acesso.

## Entity Mask

`ctools_entity_mask` permite que um **tipo de entidade customizado** declare,
na definição PHP do tipo, outro tipo do qual copiará campos e configurações de
formulário/exibição. Não é uma configuração que se ativa para os módulos
Webform ou Commerce, nem uma forma de copiar a configuração de formulários
Webform ou panes de checkout.

Webform usa `WebformEntityStorage` e `WebformSubmissionStorage`; Commerce usa
armazenamentos próprios para pedidos e itens. Entity Mask só troca o storage
quando a entidade mascarada usa exatamente o storage SQL padrão do Core. Não
marcar esses tipos transacionais como máscaras: isso não é compatível com seus
handlers e poderia comprometer persistência e comportamento de domínio.

O LMS customizado não apresenta hoje um tipo de entidade com campos e displays
equivalentes a outro tipo que justifique a máscara. O tipo H5P `h5p_content`
encontrado no código de desenvolvimento tem campos próprios e storage/schema
próprios; ele não é candidato a mascarar Webform ou Commerce. Reavaliar somente
se surgir um novo tipo de entidade customizado que precise compartilhar
intencionalmente o conjunto de campos e displays de outro tipo.

## Referências upstream

- [CTools 4.1, compatibilidade com Drupal 11](https://www.drupal.org/project/ctools/releases/4.1.0)
- [CTools Views: opções do formulário de bloco](https://api.drupal.org/api/ctools/modules%21ctools_views%21src%21Plugin%21Display%21Block.php/function/Block%3A%3AblockForm/4.0.x)
- [Node Views data: tag de consulta `node_access`](https://api.drupal.org/api/drupal/core%21modules%21node%21src%21NodeViewsData.php/class/NodeViewsData/11.x)
