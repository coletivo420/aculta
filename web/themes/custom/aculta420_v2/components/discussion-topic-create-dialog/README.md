# discussion-topic-create-dialog — Compositor de Criação de Tópico (popup)

Popup do botão **Criar tópico**, no mesmo padrão do popup "Responder à discussão" (SDC `dialog` do tema). É o componente do popup; o formulário de página é o "Compositor de Criação de Conteúdo" (`discussion-topic-composer`).

## Propriedades
- `id`: identificador do diálogo (padrão `aculta-topic-create-dialog`).
- `trigger_label`: texto do botão (padrão "Criar tópico").
- `title`: título do popup (padrão "Criar tópico").

## Slots
- `form`: formulário do nó `aculta_forum_topic`, com Assunto antes do Comentário.

## Story
- `discussion-topic-create-dialog.default.story.yml`, classe `Drupal\aculta420_v2\Story\DiscussionTopicCreateDialogStory`.
