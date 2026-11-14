# forum-opening-post — Postagem de Abertura do Tópico

Primeiro post do tópico na página completa. Usa o mesmo destaque da linha de tópico fixado (`forum-topic-row--pinned`): barra lateral na cor primária e fundo `--color-surface-highlight`, nos modos claro e escuro.

## Propriedades (Props)
- `author_name` (string, obrigatório): nome do autor do tópico.
- `author_posts` (string, inteiro ou null): contagem opcional de posts do autor.
- `timestamp` (string ou null): data de publicação já formatada.
- `permalink_url`, `delete_url`, `edit_url`, `quote_url` e `reply_url` (string ou null): destinos das ações opcionais. O chamador é responsável por decidir quais URLs pode fornecer conforme as regras de acesso e moderação.
- `density` (string): espaçamento do cartão (`default` ou `compact`).

## Slots
- `body`: corpo do tópico, renderizado pelo Drupal.

## Consumidores
- Tema `aculta420_v2`: `templates/content/node--aculta-forum-topic.html.twig`, visualização completa. Vale para os purposes Fórum e Fórum LMS, que compartilham o tipo de conteúdo.

Os ícones são resolvidos pelos SDCs das ações; o componente não recebe markup SVG por props.
