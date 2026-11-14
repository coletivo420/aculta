# Tópico do Fórum

Apresenta teasers e tópicos completos preservando os campos e ações nativos.
O template Drupal encaminha conteúdo, data e flag ao SDC; autoria continua no
render array público do `aculta_user`.

- Props: `variant`, `has_title`, `has_meta`, `has_bookmark`.
- Slots: `title`, `meta`, `bookmark`, `content`.
- Story: `forum-topic.default.story.yml`.
- JavaScript: nenhum.
- Nunca consulta usuário, inventa avatar/inicial ou decide acesso.
- CSS mobile-first com tokens; o consumer mantém a estrutura de cinco posições
  da barra administrativa local do módulo fora do SDC.

O hook `PagePresentationHooks::composeForumTopic()` consome o componente em
todos os view modes: `full` é envolvido pelo SDC `forum-topic-page`; os demais
usam a variante teaser.
A View pública preserva seu render array pelo adapter em
`templates/views/` e reaproveita o layout de atividade.

As Views de lista, categoria e busca carregam seus estilos nos SDCs
`forum-list-page`, `forum-category-page` e `forum-search-page`. Os comentários
compartilhados carregam layout e controles de discussão por `forum-post-card`.
Inspeção das rotas e matriz Firefox no Runtime ainda estão pendentes.
