# document-card

Cartão de documento oficial para a área de transparência. O título e o destino vêm da entidade Node; categoria, resumo e arquivo permanecem render arrays Field API.

## Props
- `title` (string, obrigatório): título do documento.
- `url` (string, obrigatório): URL canônica do documento.

## Slots
- `category`: categoria do conteúdo.
- `summary`: resumo do documento.
- `attachment`: arquivo renderizado com acesso e cache Drupal.

A story usa conteúdo sintético. A integração real fica em `node--document--teaser.html.twig`.
