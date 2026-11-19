# discussion-reply-composer — Compositor de Resposta da Discussão

Componente visual do design system ACULTA para o compositor de respostas nas discussões compartilhadas (Revista, Wiki, LMS e Growroom420). Por padrão, consome o SDC `popup`; a story mostra o botão que abre o formulário dentro do modal.

## Estrutura
- `discussion-reply-composer.component.yml`: Definição de esquema estrito com props mínimas e slots.
- `discussion-reply-composer.twig`: compõe o container e seu contexto dentro do SDC `popup`; permite saída inline quando a página já possui um popup compartilhado.
- `discussion-reply-composer.css`: Estilização semântica responsiva baseada em tokens.
- `discussion-reply-composer.default.story.yml`: Story padrão para catálogo e validação visual.

## Propriedades (Props)
- `target_id` (string, obrigatório): ID único do elemento DOM para swap HTMX.
- `standalone_popup` (boolean, padrão `true`): compõe um popup próprio. O consumidor de produção usa `false` porque a página já fornece um único SDC `popup` para responder, citar, editar e excluir.
- `status` (string, padrão: `editando`): Estado operacional (`fechado`, `carregando`, `editando`, `previa`, `invalido`, `enviando`, `enviado`, `moderacao`, `acesso_revogado`, `sessao_expirada`, `erro_rede`).
- `parent_id` (string/int, opcional): ID da publicação ou comentário pai.
- `parent_author` (string, opcional): Nome do autor da mensagem respondida.
- `parent_subject` (string, opcional): Assunto ou resumo do comentário pai.
- `host_title` (string, opcional): Título da entidade hospedeira da discussão.

## Slots
- `messages`: Mensagens e alertas acessíveis com `role="status"` e `aria-live="polite"`.
- `form`: Formulário Form API do Drupal com campos e botões nativos.
- `context`: Slot para dados contextuais ou prévias adicionais.

## Consumidores
- Módulo `aculta_forum`: `discussion-reply-composer.html.twig`, `DiscussionReplyController`, `DiscussionReplyFormBuilder`.
