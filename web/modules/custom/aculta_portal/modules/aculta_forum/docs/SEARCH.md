# Busca pública do Growroom420

`/pesquisar` é uma View de Search API exclusiva do purpose `forum`. O índice
`aculta_forum` seleciona Nodes `aculta_forum_topic` e posts revisionáveis
`aculta_forum_post`; não indexa Comments, Revista, Wiki ou discussões do LMS. A View mantém o
controle de acesso padrão de Search API/Views, sem `skip_access`.

## Segurança e atualização

- `content_access` aplica os grants de Node na indexação e na consulta; o
  processador `entity_status` exclui entidades não publicadas. A View exige
  `access content`.
- `index_directly: true` reduz o intervalo em que mudanças de estado/acesso
  poderiam aguardar o próximo cron. Alterações de access ainda dependem do
  comportamento de tracking/reindex do Search API; não se deve interpretar o
  índice como substituto de Entity Access.
- Sem ordenação declarada pela View, Search API 1.41 ordena por relevância
  decrescente. Esse padrão está definido na `QueryInterface` upstream e no
  código instalado; não se adiciona uma ordenação SQL paralela. Esta entrega não
  oferece escolha entre relevância e atividade.
- A suíte Kernel do Search API DB valida em conjunto busca textual e filtro de
  categoria, além de confirmar que o boost configurado no título pontua acima
  de uma correspondência somente no corpo. O teste cobre o contrato de query e
  indexação, sem criar conteúdo no Runtime.
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

### Posts revisionáveis

Desde a dev.202, o índice inclui o datasource `entity:aculta_forum_post` junto
com `entity:node`. O processor `forum_post_search_access` deriva categoria,
Domain, estado publicado e grants de visualização do Node hospedeiro; a View
usa campos unificados para filtros fixos de tipo/categoria/Domain. O branch de
posts da query Search API exige publicação do post e do tópico e interseção
com os grants da conta antes do pager e dos totais. O branch de Nodes mantém o
processor Core `content_access`. O escopo do purpose continua fail-closed no
`ForumSearchQuerySubscriber`, e a checagem de acesso padrão da View permanece
habilitada.

O campo oculto `post_topic_changed` indexa `topic:entity:changed` para que o
`track_changes_in_references` do Search API marque posts relacionados quando
seu Node hospedeiro é salvo. O código instalado constrói o mapa de referências
a partir dos campos configurados no índice; apenas derivar grants/categoria no
processor não cria essa relação para o tracker. Rebuilds de `node_access` que
não salvam os Nodes ainda exigem reindexação explícita do índice, conforme o
comportamento do processor Core `content_access`.

Os resultados de post usam o view mode `forum_search_result`, com assunto,
resumo limitado e link ao fragmento `forum-post-{id}` na página canônica do
tópico. O formatter revalida acesso do post e do Node hospedeiro durante a
renderização; `ForumPost` não declara uma rota canônica própria.

Essa integração ainda exige validação automatizada integrada antes de reindexar
ou ativar a configuração no Runtime. A matriz pendente deve cobrir contas
anônimas/autenticadas, grants do tópico, tópico e post não publicados,
`bypass node access`, purpose/Domain incorreto, filtro de categoria e link de
resultado, além da reindexação de posts quando um tópico hospedeiro é salvo.
A checagem de acesso final da View é defesa adicional: a documentação
upstream alerta que checagens feitas só depois da busca não atualizam facetas,
totais ou paginação.

Não habilitar apenas o processador `role-based access` como substituto dos
grants do tópico: ele indexa acesso por papel, enquanto Node Access pode variar
por realm e GID para a conta atual. A extensão precisa indexar os grants de
visualização do tópico hospedeiro junto ao post e aplicar os grants da conta na
consulta do Search API, antes de total e paginação. Isso deve cobrir também a
publicação do tópico hospedeiro; o status publicado do post, isoladamente, não
torna o resultado público. O processador `content_access` continua habilitado
para os Nodes da busca, mas o código instalado 1.41 não aplica esse processor a
datasources de entidades fora de Node e Comment.

O corpo de Comments não é pesquisado. A interface permite busca textual de
assunto/corpo dos posts e título/corpo do tópico, com pelo menos três caracteres, além de filtro por
categoria. O índice armazena o ID inteiro do termo; um hook de formulário
substitui o controle numérico da View por um select com rótulos hierárquicos
atuais, traduzidos e filtrados por Entity Access, incluindo os metadados de
cache correspondentes. Isso evita depender de um tipo de campo de entidade que
não existe na versão instalada do Search API. Sugestões e busca contextual de
LMS/Wiki/publicações continuam fora desta entrega. A configuração declarada não
prova que o índice está atualizado com conteúdo publicado.

Quando a consulta não encontra tópicos, a View oferece uma mensagem concisa
com link para `/categorias`. O texto usa configuração neutra de Views; o
ACULTA420 apresenta o estado vazio junto aos demais resultados do Fórum.

A busca `/pesquisar` é somente leitura e usa GET. Como as listagens públicas do
Growroom420, não renderiza CAPTCHA; essa exceção não se estende a formulários
que criam ou alteram conteúdo. A política está detalhada em
[`docs/integrations/CAPTCHA.md`](/docs/integrations/CAPTCHA.md).

Não se usa CTools para montar o formulário ou a consulta: Search API e Views são
os mecanismos próprios. CTools Views continua disponível para blocos de Views
configuráveis, conforme o contrato do Portal.

## Referências técnicas

- [Search API: processors](https://www.drupal.org/docs/8/modules/search-api/getting-started/processors)
- [Search API: common pitfalls](https://www.drupal.org/docs/8/modules/search-api/getting-started/common-pitfalls)
- [Search API: creating a search View](https://www.drupal.org/docs/8/modules/search-api/getting-started/search-forms-and-results-pages/searching-with-views/creating-a-search-view)
- [Search API: adding an index](https://www.drupal.org/docs/8/modules/search-api/getting-started/adding-an-index)
- [Search API: tracking changes in referenced entities](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/elasticsearch-connector/using-elasticsearch-connector-80x/add-a-search-api-index/basic-index-settings)
- [Search API: custom fields via processor](https://www.drupal.org/docs/8/modules/search-api/developer-documentation/create-custom-fields-using-a-custom-processor)

As referências alertam que mudanças de acesso podem depender de reindexação e
que Views aplica access checks por padrão. As versões instaladas do Search API
e Search API DB devem ser consideradas antes de qualquer alteração de
processadores ou opções.
