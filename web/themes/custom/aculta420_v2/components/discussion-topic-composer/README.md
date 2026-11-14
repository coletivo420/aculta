# discussion-topic-composer — Compositor de Criação de Tópico

Componente do design system ACULTA para demonstrar a criação de tópico com **Assunto** antes do **Comentário**. Seu contrato visual é reutilizável; consumidor de produção fora da story não está confirmado.

## Exposição e composição
- O componente permanece disponível na UI Patterns Library e tem uma story executável que cria um formulário de demonstração.
- A story usa `TopicPopupLayout` e Form API; ela não comprova que a saída da rota de produção esteja envolvida por este SDC.
- O módulo `aculta_forum`, `TopicComposerFormHooks`, define os rótulos e a ordem do formulário (Assunto = `title`, Comentário = `body`).
- O antigo arquivo `node-add-form--aculta-forum-topic.html.twig` foi removido: o Core registra `node_edit_form`, não `node_add_form`, e o projeto não declarava esse hook.

## Propriedades
- `target_id` (obrigatório): ID do container.
- `status`: `editando`, `enviando`, `erro`, `enviado`.
- `host_title` (opcional): nome do fórum ou espaço.

## Slots
- `messages`: alertas com `role="status"`.
- `form`: formulário Drupal do tópico.

## Story
- `discussion-topic-composer.default.story.yml`, classe `Drupal\aculta420_v2\Story\DiscussionTopicComposerStory`.
