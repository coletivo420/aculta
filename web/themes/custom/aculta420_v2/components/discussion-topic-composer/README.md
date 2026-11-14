# discussion-topic-composer — Compositor de Criação de Tópico

Componente do design system ACULTA para demonstrar a criação de tópico com **Assunto** antes do **Comentário**. Por padrão, consome o SDC `popup`: o botão de abertura e o formulário real de demonstração ficam na composição da Library.

## Exposição e composição
- O componente permanece disponível na UI Patterns Library e tem uma story executável que cria um formulário de demonstração.
- A story usa `TopicPopupLayout`, Form API e o popup SDC; ela demonstra o botão abrindo o modal com os campos e ações dentro dele. A rota real mantém o popup de página e o carregamento do formulário por HTMX.
- O módulo `aculta_forum`, `TopicComposerFormHooks`, define os rótulos e a ordem do formulário (Assunto = `title`, Comentário = `body`).
- O antigo arquivo `node-add-form--aculta-forum-topic.html.twig` foi removido: o Core registra `node_edit_form`, não `node_add_form`, e o projeto não declarava esse hook.

## Propriedades
- `target_id` (obrigatório): ID do container e base do ID único do popup.
- `standalone_popup` (booleano, padrão `true`): compõe o SDC dentro do popup; desligue quando o consumidor já fornece a janela modal.
- `status`: `editando`, `enviando`, `erro`, `enviado`.
- `host_title` (opcional): nome do fórum ou espaço.

## Slots
- `messages`: alertas com `role="status"`.
- `form`: formulário Drupal do tópico.

## Story
- `discussion-topic-composer.default.story.yml`, classe `Drupal\aculta420_v2\Story\DiscussionTopicComposerStory`.
