# Busca pública do Growroom420

`/pesquisar` é uma View de Search API exclusiva do purpose `forum`. O índice
`aculta_forum` seleciona somente Nodes `aculta_forum_topic`; não indexa Comments,
Revista, Wiki ou discussões do LMS. A View também fixa o bundle e mantém o
controle de acesso padrão de Search API/Views, sem `skip_access`.

## Segurança e atualização

- `content_access` aplica os grants de Node na indexação e na consulta; o
  processador `entity_status` exclui entidades não publicadas. A View exige
  `access content`.
- `index_directly: true` reduz o intervalo em que mudanças de estado/acesso
  poderiam aguardar o próximo cron. Alterações de access ainda dependem do
  comportamento de tracking/reindex do Search API; não se deve interpretar o
  índice como substituto de Entity Access.
- `ForumSearchQuerySubscriber` usa `search_api.query_pre_execute`. Só modifica o
  índice `aculta_forum`, adicionando o identificador do Domain resolvido para o
  purpose `forum`. Em outro purpose ou sem Domain configurado, adiciona uma
  condição impossível (`nid = 0`). Não há hostname no código.
- O purpose da rota da View é classificado por `DomainRouteSubscriber`. O
  processador e a checagem de acesso da View continuam ativos; filtro de Domain
  não substitui acesso ao Node.
- A busca usa cache Search API desativado na View, para não compartilhar
  resultados entre condições de consulta. Não usar filtros expostos para bundle,
  Domain ou grants.

## Limites atuais

O corpo de Comments não é pesquisado. A interface permite busca textual de
título e corpo do tópico, com pelo menos três caracteres. Filtros de categoria,
ordenadores, sugestões e busca contextual de LMS/Wiki/publicações não fazem
parte desta entrega. A configuração declarada não prova que o índice foi
instalado ou executado no Runtime.

Não se usa CTools para montar o formulário ou a consulta: Search API e Views são
os mecanismos próprios. CTools Views continua disponível para blocos de Views
configuráveis, conforme o contrato do Portal.

## Referências técnicas

- [Search API: processors](https://www.drupal.org/docs/8/modules/search-api/getting-started/processors)
- [Search API: creating a search View](https://www.drupal.org/docs/8/modules/search-api/getting-started/search-forms-and-results-pages/searching-with-views/creating-a-search-view)
- [Search API: adding an index](https://www.drupal.org/docs/8/modules/search-api/getting-started/adding-an-index)

As referências alertam que mudanças de acesso podem depender de reindexação e
que Views aplica access checks por padrão. As versões instaladas do Search API
e Search API DB devem ser consideradas antes de qualquer alteração de
processadores ou opções.
