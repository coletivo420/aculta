# Comentário de discussão

Componente visual usado pelo template Drupal de comentários da discussão.
Autoria, conteúdo, data e ações chegam como render arrays/slots do Drupal e do
Portal; o componente não consulta entidade nem decide permissões.

- Props: `has_author`, `has_submitted`.
- Slots: `author`, `submitted`, `body`, `footer`.
- CSS mobile-first com tokens semânticos.
- Consumer: `comment--aculta-discussion-post.html.twig`.
