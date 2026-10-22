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

O índice continua limitado a Nodes `aculta_forum_topic`; as respostas em
`aculta_forum_post` ainda não aparecem nos resultados. Não basta adicionar a
fonte de dados do post ao índice atual: o processador `content_access` do
Search API 1.41 dá suporte aos datasources Node e Comment. O código instalado
trata outros tipos como não afetados pelo processador, enquanto o acesso do post
depende também do tópico hospedeiro, do purpose `forum`, do Domain, dos grants
de Node e do estado publicado do tópico e do post.

Antes de habilitar posts, a integração precisa:

- indexar assunto e corpo do post, junto com valores coerentes de tipo,
  categoria e Domain para filtros fixos da View;
- aplicar os grants e a política do tópico hospedeiro durante a consulta, antes
  da paginação e dos totais. A checagem de acesso padrão da View Search API é
  defesa adicional, mas a documentação upstream alerta que checagens após a
  busca não refletem corretamente facetas e paginação;
- manter a checagem de acesso da View habilitada e preservar o filtro fixo de
  bundle e o escopo de Domain, sem filtros expostos que permitam removê-los;
- apresentar resultados com link para a âncora canônica
  `forum-post-{id}` e um resumo seguro, usando o display de busca do post;
- cobrir acesso anônimo/autenticado, tópico ou post não publicado, grants de
  Node, purpose/Domain incorreto e filtro de categoria antes de reindexar e
  liberar a integração.

O `entity_status` reconhece entidades publicáveis, mas não substitui as regras
de acesso do tópico hospedeiro. A implementação deverá reutilizar Search API e
Views, sem storage paralelo, e validar no código instalado a semântica do
processador customizado ou de outra estratégia compatível.

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
título e corpo do tópico, com pelo menos três caracteres, além de filtro por
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
- [Search API: custom fields via processor](https://www.drupal.org/docs/8/modules/search-api/developer-documentation/create-custom-fields-using-a-custom-processor)

As referências alertam que mudanças de acesso podem depender de reindexação e
que Views aplica access checks por padrão. As versões instaladas do Search API
e Search API DB devem ser consideradas antes de qualquer alteração de
processadores ou opções.
