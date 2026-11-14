# Verbete da Wiki

O SDC apresenta os view modes completo e teaser de `wiki_entry`. No view mode
`full`, o próprio SDC também é o layout UI Patterns do Layout Builder: regiões
semânticas recebem os blocos de campo Drupal e mantêm access checks, cache e
render arrays. Título e URL canônica vêm do contexto da entidade. O teaser segue
composição de slot em Views.

- Props: `variant`, `url`, `entity_title`, `title_level`, `has_summary`,
  `has_image`, `has_categories` (flags aplicados ao teaser; o artigo decide pelos
  slots renderizados e acessíveis).
- Slots: `title`, `summary`, `image`, `categories`, `body`, `related`,
  `references`, `discussion`, `actions`.
- Stories: `wiki-entry.default.story.yml` e `wiki-entry.teaser.story.yml`.
- JavaScript: nenhum.
- O artigo usa H2 porque o título da rota fornece o H1. O Domain Header não é
  modificado.
- Estilos: tokens semânticos do tema; artigo em uma coluna na base mobile-first,
  com coluna de informações a partir de `48rem`.

A UI Patterns Library descobre este SDC e suas stories automaticamente.
A validação visual em páginas Wiki reais ainda depende do Runtime.
