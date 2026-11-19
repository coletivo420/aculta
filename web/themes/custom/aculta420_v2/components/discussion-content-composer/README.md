# discussion-content-composer — Compositor de Criação de Conteúdo

Compositor da ação "Nova publicação" do Aculta Studio (Marley). Por padrão, compõe o formulário de revista dentro do SDC `popup`, com botão de abertura e todos os campos no diálogo. O Runtime de Marley mantém um popup compartilhado de página.

- Props: `target_id`, `standalone_popup`, `status`, `trigger_label`, `dialog_title`.
- Slots: `messages`, `form`.
- Story: `discussion-content-composer.default.story.yml` (`DiscussionContentComposerStory`), com `StudioContentPopupLayout` para reproduzir o formulário real.
- Consumer: `aculta_magazine` (`EditorialWorkspaceController::createPopup`) passa `standalone_popup: false` porque `MarleyController` já fornece o SDC `popup` e o formulário chega por HTMX.
