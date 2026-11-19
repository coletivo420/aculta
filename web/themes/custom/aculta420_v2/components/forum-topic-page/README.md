# Página de tópico do Fórum

Composição SDC da view full do tópico. O adapter PHP fornece os nomes simples
da categoria/autoria, o timestamp e o SDC `forum-topic` como slot. Campos,
reação, ações permitidas e cache continuam vindo dos render arrays Drupal e do
módulo Fórum.

- Props: `category_label`, `author_name`, `created`.
- Slot: `topic`.
- Story: `forum-topic-page.default.story.yml`.
- CSS: layout, painel lateral, controles indisponíveis e diálogo da página ficam encapsulados neste SDC.
- Reutiliza os SDCs `forum-topic`, `forum-opening-post`, `dialog`, `button` e
  `icon`.
- Não recebe entidades nem decide acesso; URLs e ações são autorizadas pelo
  módulo proprietário.
