# forum-opening-post — Postagem de Abertura do Tópico

Primeiro post do tópico na página completa. Usa o mesmo destaque da linha de tópico fixado (`forum-topic-row--pinned`): barra lateral na cor primária e fundo `--color-surface-highlight`, nos modos claro e escuro.

## Propriedades (Props)
- `author_name` (string, obrigatório): nome do autor do tópico.
- `timestamp` (string, opcional): data de publicação já formatada.

## Slots
- `body`: corpo do tópico, renderizado pelo Drupal.

## Consumidores
- Tema `aculta420_v2`: `templates/content/node--aculta-forum-topic.html.twig`, visualização completa. Vale para os purposes Fórum e Fórum LMS, que compartilham o tipo de conteúdo.
