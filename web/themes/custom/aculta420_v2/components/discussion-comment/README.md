# Comentário de discussão

Componente visual base para comentários da discussão.
Autoria, conteúdo, data e ações chegam como render arrays/slots do Drupal e do
Portal; o componente não consulta entidade nem decide permissões.

- Props: `has_author`, `has_submitted`.
- Slots: `author`, `submitted`, `body`, `footer`.
- CSS mobile-first com tokens semânticos.
- Consumers no tema V2: os comentários da discussão compartilham a apresentação de post com `aculta420_v2:forum-post-card` nos templates `comment--field-aculta-discussion.html.twig` e `comment--comments.html.twig`. O SDC `discussion-comment` permanece como padrão atômico disponível para temas e layouts simplificados.
