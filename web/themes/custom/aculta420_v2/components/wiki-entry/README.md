# Verbete da Wiki

O SDC apresenta os view modes completos e teaser de `wiki_entry`. Os templates
Drupal mantêm a entidade, render arrays de campo e links com os access checks do
owner. O componente só organiza os slots e a hierarquia editorial da V1.

- Props: `variant`, `url`, `has_summary`, `has_image`, `has_categories`,
  `has_related`, `has_references`, `has_discussion`.
- Slots: `title`, `summary`, `image`, `categories`, `body`, `related`,
  `references`, `discussion`, `actions`.
- Stories: `wiki-entry.default.story.yml` e `wiki-entry.teaser.story.yml`.
- JavaScript: nenhum.
- O artigo fornece um H1; o título genérico da página é escondido apenas quando
  o adapter de artigo está presente. O Domain Header não é modificado.
- Estilos: tokens semânticos do tema; artigo em uma coluna na base mobile-first,
  com coluna de informações a partir de `48rem`.

Runtime e visual aguardam validação em Firefox ESR nas páginas Wiki reais.
