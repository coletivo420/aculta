# discussion-topic-composer — Compositor de Criação de Conteúdo

Componente do design system ACULTA para criar um tópico de discussão (Growroom420, Revista, Wiki e LMS). É o único compositor com **Assunto**, que fica antes do **Comentário**.

## Onde é usado
- `/criar-topico` e `node.add` do tipo `aculta_forum_topic`: o template `templates/content/node-add-form--aculta-forum-topic.html.twig` envolve o formulário neste componente.
- Rótulos e ordem do formulário: módulo `aculta_forum`, `TopicComposerFormHooks` (Assunto = `title`, Comentário = `body`).

## Propriedades
- `target_id` (obrigatório): ID do container.
- `status`: `editando`, `enviando`, `erro`, `enviado`.
- `host_title` (opcional): nome do fórum ou espaço.

## Slots
- `messages`: alertas com `role="status"`.
- `form`: formulário Drupal do tópico.

## Story
- `discussion-topic-composer.default.story.yml`, classe `Drupal\aculta420_v2\Story\DiscussionTopicComposerStory`.
