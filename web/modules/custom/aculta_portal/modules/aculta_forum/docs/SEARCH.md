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
- [Search API: creating a search View](https://www.drupal.org/docs/8/modules/search-api/getting-started/search-forms-and-results-pages/searching-with-views/creating-a-search-view)
- [Search API: adding an index](https://www.drupal.org/docs/8/modules/search-api/getting-started/adding-an-index)

As referências alertam que mudanças de acesso podem depender de reindexação e
que Views aplica access checks por padrão. As versões instaladas do Search API
e Search API DB devem ser consideradas antes de qualquer alteração de
processadores ou opções.
