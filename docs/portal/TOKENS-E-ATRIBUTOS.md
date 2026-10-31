# Tokens e atributos do Portal para o tema

> Reconciliado com o código em 2026-10-30 e validado pelo gate `scripts/validate-portal-ui-docs.php`. Quando um token ou atributo novo for criado, ele precisa entrar aqui (o gate reprova o contrário).

## Como o contrato funciona

- O **módulo** (`aculta_portal` e submódulos) marca elementos com `data-portal-ui="token"` e atributos de comportamento com os prefixos data-portal-, data-aculta- e data-dialog-. Nunca escreve classe de design.
- O **tema** (`aculta420`) traduz cada token em classes em `src/Hook/PortalUiHooks.php` (`TOKENS`). Onde o hook não se aplica (formulários e links), o atributo continua no HTML e o CSS pode selecioná-lo diretamente.
- O CSS do tema aceita **classe ou atributo** quando o elemento pode vir de qualquer um dos dois caminhos: `:is(.classe, [data-portal-ui="token"])`.
- Tamanho, posição, cores e espaçamento são sempre do tema. O módulo não informa largura, altura nem posição de diálogo (ver `POPUP-PADRAO.md`).

## Como adicionar um token

1. No módulo, use `data-portal-ui="novo-token"` (`#attributes`, `#element_attributes` ou template).
2. No tema, inclua `'novo-token' => ['classe-do-tema']` em `PortalUiHooks::TOKENS`.
3. Documente o token na tabela abaixo (nome, classes, uso).
4. Rode `php scripts/validate-portal-ui-docs.php` e `php scripts/validate-module-theme-boundary.php`.

## Atributos

| Atributo | Família | Uso (JS) | Arquivos |
| --- | --- | --- | ---: |

Atributos: não há wildcard. Todo nome usado no código aparece na tabela acima (o gate reprova o que não estiver).
| `data-aculta-account-list` | exato | sim | 2 |
| `data-aculta-add-member` | exato | sim | 2 |
| `data-aculta-album-open` | exato | sim | 2 |
| `data-aculta-album-stage` | exato | sim | 3 |
| `data-aculta-album-thumb` | exato | sim | 3 |
| `data-aculta-album-thumbs` | exato | sim | 2 |
| `data-aculta-album-toggle` | exato | sim | 2 |
| `data-aculta-alert-dismiss` | exato | sim | 1 |
| `data-aculta-alerts-count` | exato | sim | 2 |
| `data-aculta-alinhamento` | exato | sim | 1 |
| `data-aculta-bio-clear` | exato | sim | 2 |
| `data-aculta-bio-form` | exato | sim | 2 |
| `data-aculta-cart-count` | exato | não | 2 |
| `data-aculta-cart-trigger` | exato | não | 2 |
| `data-card-variation` | exato | não | 1 |
| `data-aculta-cep-status` | exato | sim | 1 |
| `data-aculta-chip` | exato | sim | 2 |
| `data-aculta-chip-name` | exato | sim | 1 |
| `data-aculta-chip-remove` | exato | sim | 2 |
| `data-aculta-chips` | exato | sim | 2 |
| `data-aculta-clear` | exato | sim | 2 |
| `data-aculta-confirming` | exato | sim | 1 |
| `data-aculta-conversation-url` | exato | não | 1 |
| `data-aculta-conversation-view` | exato | sim | 2 |
| `data-aculta-count-kind` | exato | não | 1 |
| `data-aculta-dialog` | exato | sim | 3 |
| `data-aculta-dialog-close` | exato | sim | 2 |
| `data-aculta-dialog-open` | exato | sim | 2 |
| `data-aculta-discussion-announcement` | exato | sim | 1 |
| `data-aculta-discussion-first` | exato | sim | 1 |
| `data-aculta-discussion-last` | exato | sim | 1 |
| `data-aculta-discussion-next` | exato | sim | 1 |
| `data-aculta-discussion-previous` | exato | sim | 1 |
| `data-aculta-discussion-progress` | exato | não | 2 |
| `data-aculta-discussion-range` | exato | sim | 1 |
| `data-aculta-discussion-status` | exato | sim | 1 |
| `data-aculta-discussion-thread` | exato | sim | 1 |
| `data-aculta-discussion-unread` | exato | sim | 1 |
| `data-aculta-document-control` | exato | sim | 4 |
| `data-aculta-document-part` | exato | sim | 5 |
| `data-aculta-document-viewer` | exato | sim | 3 |
| `data-aculta-docx` | exato | sim | 2 |
| `data-aculta-docx-frame-url` | exato | sim | 1 |
| `data-aculta-docx-libs` | exato | sim | 1 |
| `data-aculta-docx-status` | exato | sim | 0 |
| `data-aculta-docx-title` | exato | sim | 1 |
| `data-aculta-docx-url` | exato | sim | 1 |
| `data-aculta-docx-render-options` | exato | sim | 2 |
| `data-aculta-drawer-action` | exato | sim | 3 |
| `data-aculta-drawer-back` | exato | sim | 4 |
| `data-aculta-drawer-content` | exato | sim | 2 |
| `data-aculta-drawer-count` | exato | sim | 2 |
| `data-aculta-drawer-filter` | exato | sim | 2 |
| `data-aculta-drawer-filter-url` | exato | sim | 1 |
| `data-aculta-drawer-label` | exato | não | 2 |
| `data-aculta-drawer-url` | exato | sim | 2 |
| `data-aculta-forum-post-timestamp` | exato | sim | 3 |
| `data-aculta-game` | exato | não | 1 |
| `data-aculta-group-max` | exato | sim | 1 |
| `data-aculta-imagem` | exato | não | 1 |
| `data-aculta-imagem-alinhamento` | exato | não | 1 |
| `data-aculta-imagem-legenda` | exato | não | 1 |
| `data-aculta-imagem-modelo` | exato | não | 1 |
| `data-aculta-js-only` | exato | sim | 2 |
| `data-aculta-json-form` | exato | sim | 2 |
| `data-aculta-lazy-src` | exato | sim | 2 |
| `data-aculta-legenda` | exato | sim | 2 |
| `data-aculta-lock-mirror` | exato | sim | 1 |
| `data-aculta-mailer-preview` | exato | não | 1 |
| `data-aculta-mailer-table` | exato | não | 2 |
| `data-aculta-mailer-tendencia` | exato | não | 1 |
| `data-aculta-member-name` | exato | sim | 1 |
| `data-aculta-map` | exato | sim | 2 |
| `data-aculta-messenger-count` | exato | sim | 2 |
| `data-aculta-modelo` | exato | sim | 3 |
| `data-aculta-new-conversation` | exato | sim | 2 |
| `data-aculta-new-conversation-form` | exato | sim | 2 |
| `data-aculta-nojs-search` | exato | sim | 2 |
| `data-aculta-notice-filters` | exato | não | 2 |
| `data-aculta-notice-group-key` | exato | não | 1 |
| `data-aculta-notice-message-id` | exato | não | 6 |
| `data-aculta-notice-origin` | exato | não | 7 |
| `data-aculta-notice-origin-section` | exato | não | 1 |
| `data-aculta-notice-read` | exato | não | 9 |
| `data-aculta-notice-recency` | exato | não | 9 |
| `data-aculta-number` | exato | sim | 2 |
| `data-aculta-number-sn` | exato | não | 1 |
| `data-aculta-open-conversation` | exato | sim | 2 |
| `data-aculta-options-url` | exato | sim | 2 |
| `data-aculta-pdf-control` | exato | sim | 4 |
| `data-aculta-pdf-default-scroll` | exato | não | 1 |
| `data-aculta-pdf-default-zoom` | exato | não | 1 |
| `data-aculta-pdf-double-page` | exato | não | 1 |
| `data-aculta-pdf-double-page-mobile` | exato | não | 1 |
| `data-aculta-pdf-show-fullscreen` | exato | não | 1 |
| `data-aculta-pdf-show-page-navigation` | exato | não | 1 |
| `data-aculta-pdf-show-search` | exato | não | 1 |
| `data-aculta-pdf-show-zoom` | exato | não | 1 |
| `data-aculta-pdf-title` | exato | sim | 1 |
| `data-aculta-pdf-url` | exato | sim | 1 |
| `data-aculta-pdf-viewer` | exato | sim | 3 |
| `data-aculta-photo-editor` | exato | não | 2 |
| `data-aculta-photo-open` | exato | não | 1 |
| `data-aculta-popup` | exato | sim | 2 |
| `data-aculta-portal-link` | exato | não | 2 |
| `data-aculta-reply-form` | exato | sim | 3 |
| `data-aculta-revista` | exato | não | 1 |
| `data-aculta-sent-date` | exato | sim | 1 |
| `data-aculta-sheet` | exato | sim | 2 |
| `data-aculta-sheet-default-mode` | exato | sim | 2 |
| `data-aculta-sheet-count` | exato | sim | 0 |
| `data-aculta-sheet-format` | exato | sim | 1 |
| `data-aculta-sheet-page-size` | exato | sim | 2 |
| `data-aculta-sheet-frame-url` | exato | sim | 1 |
| `data-aculta-sheet-libs` | exato | sim | 1 |
| `data-aculta-sheet-status` | exato | sim | 0 |
| `data-aculta-sheet-tabulator` | exato | sim | 1 |
| `data-aculta-sheet-title` | exato | sim | 1 |
| `data-aculta-sheet-url` | exato | sim | 1 |
| `data-aculta-shipping-calculator` | exato | sim | 4 |
| `data-aculta-shipping-resultado` | exato | sim | 4 |
| `data-aculta-studio` | exato | sim | 3 |
| `data-aculta-studio-tab` | exato | não | 1 |
| `data-aculta-thread-id` | exato | não | 1 |
| `data-aculta-thread-unread` | exato | não | 1 |
| `data-aculta-user` | exato | não | 1 |
| `data-aculta-user-avatar` | exato | não | 3 |
| `data-aculta-user-picture` | exato | não | 1 |
| `data-aculta-utility-drawer` | exato | sim | 4 |
| `data-dialog-options` | exato | sim | 6 |
| `data-dialog-title` | exato | não | 2 |
| `data-dialog-type` | exato | sim | 7 |
| `data-portal-account` | exato | não | 2 |
| `data-portal-account-accordion` | exato | sim | 3 |
| `data-portal-account-accordion-panel` | exato | sim | 3 |
| `data-portal-account-content` | exato | sim | 3 |
| `data-portal-account-data-content` | exato | não | 2 |
| `data-portal-account-data-link` | exato | não | 3 |
| `data-portal-account-data-nav` | exato | sim | 4 |
| `data-portal-account-error` | exato | sim | 1 |
| `data-portal-account-item` | exato | sim | 3 |
| `data-portal-account-layout` | exato | sim | 3 |
| `data-portal-account-loader` | exato | sim | 1 |
| `data-portal-account-loading` | exato | não | 2 |
| `data-portal-account-menu` | exato | sim | 3 |
| `data-portal-account-retry` | exato | sim | 1 |
| `data-portal-account-status` | exato | não | 2 |
| `data-portal-account-summary` | exato | sim | 3 |
| `data-portal-breadcrumb` | exato | sim | 2 |
| `data-portal-cart-status` | exato | sim | 3 |
| `data-portal-cart-status-close` | exato | sim | 3 |
| `data-portal-chrome` | exato | sim | 2 |
| `data-portal-page-title` | exato | sim | 2 |
| `data-portal-payment-notice` | exato | não | 2 |
| `data-portal-trigger` | exato | sim | 7 |
| `data-portal-ui` | exato | sim | 50 |
## Famílias dinâmicas de tokens

Montadas por concatenação no módulo (o gate não enxerga cada valor; a família é o contrato):

- `post-share-*`: Botões de compartilhamento da publicação (uma família por canal).
- `public-profile-link-*`: Links sociais do perfil público (uma família por rede).
- `social-links-channel-*`: Canais da página de redes sociais (uma família por canal).
- `social-links-icon-*`: Ícone do canal na lista de redes sociais (uma família por canal; ícone Bootstrap).

## Tokens

| Token | Classes do tema | Uso (arquivos, até 2) |
| --- | --- | --- |
| `notice-warning` | `messages`, `messages--warning` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php`<br>`web/modules/custom/aculta_portal/src/Deployer/DeployerStatusController.php` |
| `notice-error` | `messages`, `messages--error` | (montado dinamicamente ou só no tema) |
| `email-notice` | `aculta-email-notice` | (montado dinamicamente ou só no tema) |
| `requirements` | `aculta-portal-requirements` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php`<br>`web/modules/custom/aculta_portal/src/Deployer/DeployerStatusController.php` |
| `requirements-legend` | `aculta-portal-requirements__legend` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php`<br>`web/modules/custom/aculta_portal/src/Deployer/DeployerStatusController.php` |
| `requirements-status` | `aculta-portal-requirements__status` | (montado dinamicamente ou só no tema) |
| `status-ok` | `aculta-portal-requirements__status--ok` | (montado dinamicamente ou só no tema) |
| `status-warning` | `aculta-portal-requirements__status--warning` | (montado dinamicamente ou só no tema) |
| `status-error` | `aculta-portal-requirements__status--error` | (montado dinamicamente ou só no tema) |
| `status-pending` | `aculta-portal-requirements__status--pending` | (montado dinamicamente ou só no tema) |
| `requirements-indexing` | `aculta-portal-requirements__indexing` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` |
| `messenger-preview` | `aculta-messenger-preview` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-state` | `aculta-messenger-state` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-state-empty` | `aculta-messenger-state--empty` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `alerts-filters` | `aculta-alerts-filters` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `alerts-filters-group` | `aculta-alerts-filters__group` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-preview-inbox-link` | `btn`, `btn-primary`, `aculta-messenger-preview__inbox-link` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `new-conversation-link` | `btn`, `btn-primary`, `aculta-messenger-new-conversation` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `new-conversation-submit` | `btn`, `btn-primary` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-back` | `btn`, `btn-outline-primary` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `conversation-action` | `aculta-messenger-options__action` |
| `conversation-notice-action` | `aculta-messenger-notice__action` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/ConversationRelationshipForm.php` |
| `new-conversation-links` | `d-flex`, `flex-wrap`, `gap-2`, `mt-2` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-message` | `form-control` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-results` | `list-group` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NewConversationController.php` |
| `new-conversation-result` | `list-group-item`, `list-group-item-action` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NewConversationController.php` |
| `challenge-frame` | sem classe; âncora de container query para reflow de formulários estreitos | `web/modules/custom/aculta_portal/src/Hook/CaptchaPresentationHooks.php` |
| `account-home` | `aculta-account-home` | `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` (link Minha conta do menu da conta; o tema aplica a classe ao link) |
| `messenger-read-status` | `aculta-messenger-read-status` | `web/themes/custom/aculta420/src/Hook/ThemeHooks.php` |
| `notice-read-status` | `aculta-messenger-read-status` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-unread-status` | `aculta-messenger-read-status`, `aculta-messenger-read-status--unread` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-read-state-form` | `aculta-notice-read-state-form` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NoticeReadStateForm.php` |
| `notice-read-state-action` | `aculta-notice-read-state-action` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NoticeReadStateForm.php` |
| `requirements-indexing-table` | `aculta-portal-requirements__indexing-table` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` |
| `secrets-section` | `aculta-portal-secrets-section` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `secret-value` | `aculta-secret-value` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `secret-value-text` | `aculta-secret-value__text` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `secret-value-eye` | `aculta-secret-value__eye` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `secret-value-edit` | `aculta-secret-value__edit` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `secret-value-clear` | `aculta-secret-value__clear` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `secret-popup-value` | `aculta-secret-popup__value` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `secret-popup-hint` | `aculta-secret-popup__hint` | `web/modules/custom/aculta_portal/src/Secrets/SecretsController.php` |
| `search` | `aculta-search` | `web/modules/custom/aculta_portal/src/Search/SearchPageController.php` |
| `search-results` | `aculta-search__results` | `web/modules/custom/aculta_portal/src/Search/SearchPageController.php` |
| `search-utility` | `aculta-search-utility`, `btn`, `btn-link`, `p-0` | `web/modules/custom/aculta_portal/src/Search/SearchUtility.php` |
| `search-popup` | `aculta-search-popup` | `web/modules/custom/aculta_portal/src/Search/Form/SearchPopupForm.php` |
| `search-preview` | `aculta-search-preview` | `web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php`<br>`web/modules/custom/aculta_portal/src/Search/Form/SearchPopupForm.php` |
| `search-preview-input` | `aculta-search-preview-input` | `web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php`<br>`web/modules/custom/aculta_portal/src/Search/Form/SearchPopupForm.php` |
| `search-preview-list` | `aculta-search-preview__list`, `list-unstyled` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Search/SearchPreviewController.php` |
| `search-preview-footer` | `aculta-search-preview__footer`, `d-flex`, `justify-content-center`, `mt-2` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Search/SearchPreviewController.php` |
| `search-preview-full` | `aculta-search-preview__full`, `btn`, `btn-outline-primary`, `btn-sm` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Search/SearchPreviewController.php` |
| `hint` | `small`, `text-body-secondary` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php` |
| `hint-muted` | `text-body-secondary` | `web/modules/custom/aculta_portal/src/Search/SearchPageController.php` |
| `icon-search` | `bi`, `bi-search` | (montado dinamicamente ou só no tema) |
| `visually-hidden` | `visually-hidden` | (montado dinamicamente ou só no tema) |
| `wiki-home` | `aculta-wiki-home` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `wiki-intro` | `aculta-wiki-intro` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `wiki-search` | `aculta-wiki-search` | (montado dinamicamente ou só no tema) |
| `wiki-search-results` | `aculta-wiki-search-results` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `wiki-recent-change` | `aculta-wiki-recent-change` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `we-sativa-workspace` | `d-grid gap-4 we-sativa__workspace` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiContributorWorkspace.php` |
| `we-sativa-create` | `btn btn-primary` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiContributorWorkspace.php` |
| `wiki-workspace-return` | `btn btn-outline-primary mb-3` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Hook/WikiEntryImageHooks.php` |
| `courses-home` | `courses-home` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `courses-home-intro` | `courses-home__intro` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `courses-home-collaboration` | `courses-home__collaboration` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `account-courses` | `portal-account-courses` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-courses-summary` | `portal-account-courses-summary` | (montado dinamicamente ou só no tema) |
| `account-course` | `portal-account-course` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-course-meta` | `portal-account-course__meta` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-avatar` | `portal-account__avatar` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/AvatarRerollController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php` |
| `account-photo-preview` | `portal-account-photo__preview` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php`<br>`web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `account-bio-card` | `card`, `card-body`, `portal-account-bio` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-text` | `portal-account-bio__text` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-edit` | `btn`, `btn-link`, `portal-account-bio__edit`, `bi`, `bi-pencil` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-actions` | `portal-account-bio__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Form/SocialLinkForm.php` |
| `account-photo-actions` | `portal-account-photo__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php` | Linha de ações do popup de foto (sortear, remover e salvar). Estilo em `portal-account.css`. |
| `account-overview-actions` | `portal-account-overview__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/AccountDashboardHooks.php` |
| `account-view-profile` | `btn`, `btn-outline-secondary`, `portal-account-overview__profile` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/AccountDashboardHooks.php` |
| `post-meta` | `portal-post__meta` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/PostPresentation.php` |
| `post-meta-author` | `portal-post__meta-item`, `bi`, `bi-person-circle` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/PostPresentation.php` |
| `post-meta-date` | `portal-post__meta-item`, `bi`, `bi-calendar3` | (montado dinamicamente ou só no tema) |
| `post-meta-time` | `portal-post__meta-item`, `bi`, `bi-clock` | (montado dinamicamente ou só no tema) |
| `post-share` | `portal-post__share` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/PostPresentation.php` |
| `post-share-label` | `portal-post__share-label` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/PostPresentation.php` |
| `post-share-whatsapp` | `portal-post__share-link`, `portal-post__share-link--whatsapp`, `bi`, `bi-whatsapp` | (montado dinamicamente ou só no tema) |
| `post-share-telegram` | `portal-post__share-link`, `portal-post__share-link--telegram`, `bi`, `bi-telegram` | (montado dinamicamente ou só no tema) |
| `post-share-facebook` | `portal-post__share-link`, `portal-post__share-link--facebook`, `bi`, `bi-facebook` | (montado dinamicamente ou só no tema) |
| `post-share-reddit` | `portal-post__share-link`, `portal-post__share-link--reddit`, `bi`, `bi-reddit` | (montado dinamicamente ou só no tema) |
| `post-share-email` | `portal-post__share-link`, `portal-post__share-link--email`, `bi`, `bi-envelope` | (montado dinamicamente ou só no tema) |
| `article-authors` | `portal-article-authors` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-authors-title` | `portal-article-authors__title` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-authors-list` | `portal-article-authors__list` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-author` | `portal-article-author` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `forum-opening-post` | `portal-forum-opening` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-title` | `portal-forum-opening__title` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-image` | `portal-forum-opening__image` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-body` | `portal-forum-opening__body` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-link` | `portal-forum-opening__link` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `public-profile` | `portal-profile` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `public-profile-header` | `portal-profile__header` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `public-profile-avatar` | `portal-profile__avatar` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `public-profile-identity` | `portal-profile__identity` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `public-profile-name` | `portal-profile__name` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `public-profile-bio` | `portal-profile__bio` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `public-profile-links` | `portal-profile__links` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `public-profile-link-instagram` | `portal-profile__link`, `bi`, `bi-instagram` | (montado dinamicamente ou só no tema) |
| `public-profile-link-facebook` | `portal-profile__link`, `bi`, `bi-facebook` | (montado dinamicamente ou só no tema) |
| `public-profile-link-reddit` | `portal-profile__link`, `bi`, `bi-reddit` | (montado dinamicamente ou só no tema) |
| `public-profile-link-email` | `portal-profile__link`, `bi`, `bi-envelope` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `account-social-card` | `portal-account-social` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Social/SocialLinksCard.php` |
| `account-social-list` | `portal-account-social__list` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Social/SocialLinksCard.php` |
| `account-social-item` | `portal-account-social__item` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Social/SocialLinksCard.php` |
| `account-social-icon-instagram` | `portal-account-social__icon`, `bi`, `bi-instagram` | (montado dinamicamente ou só no tema) |
| `account-social-icon-facebook` | `portal-account-social__icon`, `bi`, `bi-facebook` | (montado dinamicamente ou só no tema) |
| `account-social-icon-reddit` | `portal-account-social__icon`, `bi`, `bi-reddit` | (montado dinamicamente ou só no tema) |
| `account-social-text` | `portal-account-social__text` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Social/SocialLinksCard.php` |
| `account-social-name` | `portal-account-social__name` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Social/SocialLinksCard.php` |
| `account-social-status` | `portal-account-social__status` | (montado dinamicamente ou só no tema) |
| `account-social-value` | `portal-account-social__value` | (montado dinamicamente ou só no tema) |
| `account-social-edit` | `btn`, `btn-link`, `btn-sm`, `portal-account-social__edit`, `bi`, `bi-pencil` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Social/SocialLinksCard.php` |
| `account-identity` | `portal-account__identity` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `account-commerce-orders` | `portal-account__commerce`, `portal-account__commerce--orders` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `account-commerce-payments` | `portal-account__commerce`, `portal-account__commerce--payments` | (montado dinamicamente ou só no tema) |
| `account-data-link` | `portal-account-data__link` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/ActivityController.php`<br>`web/modules/custom/aculta_portal/src/Appearance/SettingsTabs.php` |
| `account-data-nav` | `portal-account-data__nav` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/ActivityController.php`<br>`web/modules/custom/aculta_portal/src/Appearance/SettingsTabs.php` |
| `account-data-content` | `portal-account-data__content` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/ActivityController.php`<br>`web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `account-document-input` | `portal-account-document-input` | `web/modules/custom/aculta_portal/src/Form/AccountOtherDataForm.php` |
| `social-links-button-list` | `aculta-social-links__button-list` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-channel` | `aculta-social-links__channel` | (montado dinamicamente ou só no tema) |
| `social-links-channel-website` | `aculta-social-links__channel--website` | (montado dinamicamente ou só no tema) |
| `social-links-channel-custom` | `aculta-social-links__channel--custom` | (montado dinamicamente ou só no tema) |
| `social-links-channel-instagram` | `aculta-social-links__channel--instagram` | (montado dinamicamente ou só no tema) |
| `social-links-channel-youtube` | `aculta-social-links__channel--youtube` | (montado dinamicamente ou só no tema) |
| `social-links-channel-facebook` | `aculta-social-links__channel--facebook` | (montado dinamicamente ou só no tema) |
| `social-links-list` | `aculta-social-links__list`, `list-unstyled` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-footer-inner` | `aculta-social-links-footer__inner` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-settings-button` | `aculta-social-links-settings-button` | `web/modules/custom/aculta_portal/modules/aculta_links/src/Form/SocialLinksSettingsForm.php` |
| `security-card` | `aculta-security-card` | (montado dinamicamente ou só no tema) |
| `security-pending` | `aculta-security-pending` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `auth-provider` | `aculta-auth-provider` | `web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php`<br>`web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` |
| `terms-modal-documents` | `portal-terms-modal__documents` | `web/modules/custom/aculta_portal/src/Form/AcultaUserAgreementAcceptForm.php` |
| `street-number` | `portal-street-number` | `web/modules/custom/aculta_portal/src/Account/StreetNumberField.php` |
| `street-number-sn` | `portal-street-number-sn` | `web/modules/custom/aculta_portal/src/Account/StreetNumberField.php` |
| `breadcrumb-item` | `breadcrumb-item` | `web/modules/custom/aculta_portal/src/Render/AccountAjaxMainContentRenderer.php` |
| `breadcrumb-item-active` | `breadcrumb-item`, `active` | `web/modules/custom/aculta_portal/src/Render/AccountAjaxMainContentRenderer.php` |
| `login-reset-link` | `aculta-login__reset-link` | `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` |
| `login-registration` | `aculta-login__registration` | `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` |
| `login-divider` | `aculta-login__divider` | `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` |
| `login-popup` | `aculta-login-popup` | `web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php` |
| `login-popup-links` | `aculta-login-popup__links` | `web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php` |
| `login-popup-divider` | `aculta-login-popup__divider` | `web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php` |
| `auth-google` | `aculta-auth-google` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php`<br>`web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php` |
| `auth-google-status` | `aculta-auth-google-status` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `google-account` | `aculta-google-account` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `account-terms` | `aculta-account-terms` | `web/modules/custom/aculta_portal/js/account-terms-modal.js`<br>`web/modules/custom/aculta_portal/templates/aculta-portal-account-terms-modal.html.twig` |
| `photo-dialog` | `aculta-photo-dialog` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php` |
| `avatar-crop-dialog` | `aculta-avatar-crop-dialog` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AvatarCropForm.php` |
| `account-bio-dialog` | `aculta-account-bio-dialog` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php` |
| `social-link-dialog` | `aculta-social-link-dialog` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/SocialLinkForm.php` |
| `secret-dialog` | `aculta-secret-dialog` | `web/modules/custom/aculta_portal/src/Secrets/Form/SecretClearForm.php`<br>`web/modules/custom/aculta_portal/src/Secrets/Form/SecretEditForm.php` |
| `messenger-preview` | `aculta-messenger-preview` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-state` | `aculta-messenger-state` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-state-empty` | `aculta-messenger-state--empty` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `alerts-filters` | `aculta-alerts-filters` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `alerts-filters-group` | `aculta-alerts-filters__group` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-preview-inbox-link` | `btn`, `btn-primary`, `aculta-messenger-preview__inbox-link` | (montado pelo tema para link da inbox) |
| `account-home` | `aculta-account-home` | (montado pelo tema no link Minha conta, a partir do token do módulo) |
| `messenger-read-status` | `aculta-messenger-read-status` | `web/themes/custom/aculta420/src/Hook/ThemeHooks.php` |
| `notice-read-status` | `aculta-messenger-read-status` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-unread-status` | `aculta-messenger-read-status`, `aculta-messenger-read-status--unread` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-read-state-form` | `aculta-notice-read-state-form` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NoticeReadStateForm.php` |
| `notice-read-state-action` | `aculta-notice-read-state-action` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NoticeReadStateForm.php` |
| `account-photo-trigger` | `portal-account-photo__trigger` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/PhotoEditorHooks.php` |
| `support-form` | `aculta-support` | `web/modules/custom/aculta_portal/src/Support/Form/SupportForm.php` |
| `security-password-form` | `aculta-security-password-form` | `web/modules/custom/aculta_portal/src/Hook/FormHooks.php` |
| `registration-status` | `aculta-registration-status` | `web/modules/custom/aculta_portal/src/Controller/RegistrationController.php` |
| `support-intro` | `aculta-support-intro` | `web/modules/custom/aculta_portal/src/Support/Form/SupportForm.php` |
| `support-other` | `aculta-support-other` | `web/modules/custom/aculta_portal/src/Support/Form/SupportForm.php` |
| `domain-brand-image` | `aculta-domain-brand-image` | `web/themes/custom/aculta420/src/Hook/ThemeHooks.php` |
| `button` | `button` | (montado dinamicamente ou só no tema) |
| `button-secondary` | `button`, `button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Form/SocialLinkForm.php` |
| `button-primary` | `btn`, `btn-primary` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `aculta-button-secondary` | `aculta-button`, `aculta-button--secondary` | `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` |
| `aculta-button-primary` | `aculta-button`, `aculta-button--primary` | `web/modules/custom/aculta_portal/src/Controller/RegistrationController.php` |
| `aculta-button-outline` | `aculta-button`, `aculta-button--outline` | `web/modules/custom/aculta_portal/src/Support/Form/SupportForm.php` |
| `status-attention` | `aculta-portal-requirements__status--attention` | (montado dinamicamente ou só no tema) |
| `breadcrumb-list` | `breadcrumb` | `web/modules/custom/aculta_portal/src/Render/AccountAjaxMainContentRenderer.php` |

| `new-conversation-cancel` | `btn`, `aculta-new-conversation-cancel` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `public-profile-actions` | `portal-profile__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `friendship-action-form` | `portal-profile__friendship-form` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/FriendshipActionForm.php` |
| `friendship-action-button` | `btn`, `btn-outline-secondary`, `portal-account-overview__profile` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/FriendshipActionForm.php` |
| `forum-topic-unread` | `aculta-unread-count` | Reserva de apresentação para futura integração da View ACULTA; sem produtor ativo desde a remoção de `preprocess_forums` em dev.56. |
| `profile-achievements` | `portal-profile__achievements` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievements-title` | `portal-profile__achievements-title` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievements-list` | `portal-profile__achievements-list` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievement` | `portal-profile__achievement` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievement-image` | `portal-profile__achievement-image` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievement-content` | `portal-profile__achievement-content` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievement-name` | `portal-profile__achievement-name` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievement-description` | `portal-profile__achievement-description` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `profile-achievement-highlight` | `portal-profile__achievement-highlight` | `web/modules/custom/aculta_portal/modules/aculta_game/src/AchievementPresentation.php` |
| `messenger-back-link` | `aculta-messenger-back-link` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NewConversationController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-drawer-conversation` | `aculta-messenger-drawer-conversation` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-conversation-toolbar` | `aculta-messenger-conversation-toolbar` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-conversation-actions` | `aculta-messenger-conversation__actions` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-options` | `aculta-messenger-options` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Support/OptionsDisclosure.php` |
| `messenger-options-summary` | `aculta-messenger-options__summary` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Support/OptionsDisclosure.php` |
| `messenger-options-icon` | `bi`, `bi-list` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Support/OptionsDisclosure.php` |
| `messenger-options-label` | `aculta-messenger-options__label` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Support/OptionsDisclosure.php` |
| `messenger-options-panel` | `aculta-messenger-options__panel` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Support/OptionsDisclosure.php` |
| `messenger-options-item` | `aculta-messenger-options__item` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Hook/ProfileOptionsHooks.php` |
| `messenger-options-status` | `aculta-messenger-options__status` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/ConversationRelationshipForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-notice` | `aculta-messenger-notice` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Hook/PrivateMessageReplyHooks.php` |
| `messenger-sender` | `aculta-messenger-sender`, `aculta-messenger-conversation` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `forum-reply-total` | `aculta-forum-topic__replies-count` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/ForumTopicPresentationHooks.php` |
| `forum-topic-visitor` | `aculta-forum-topic__visitor` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/ForumTopicPresentationHooks.php` |
| `read-label` | `aculta-read-label` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `read-label-unread` | `aculta-read-label--unread` | (montado dinamicamente ou só no tema) |
| `messenger-sender-link` | `aculta-messenger-sender__link` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-sender-date` | `aculta-messenger-sender__date` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-sender-name` | `aculta-messenger-sender__name` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-sender-avatar` | `aculta-messenger-sender__avatar` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `messenger-sender-avatar-image` | `aculta-messenger-sender__avatar-image` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-summary` | `aculta-messenger-sender`, `aculta-notice-summary` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-summary-link` | `aculta-messenger-sender__link` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-summary-persistent` | `aculta-notice-summary--persistent` | (montado dinamicamente ou só no tema) |
| `notice-type-info` | `aculta-notice-type--info` | (montado dinamicamente ou só no tema) |
| `notice-type-warning` | `aculta-notice-type--warning` | (montado dinamicamente ou só no tema) |
| `notice-type-danger` | `aculta-notice-type--danger` | (montado dinamicamente ou só no tema) |
| `notice-summary-title` | `aculta-notice-summary__title` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-summary-excerpt` | `aculta-notice-summary__excerpt` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-summary-state` | `aculta-notice-summary__state` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-detail` | `aculta-notice-detail` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `new-conversation-add` | `aculta-new-conversation-add` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NewConversationController.php` |
| `new-conversation-chips` | `aculta-chips` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-chip` | `aculta-chip` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-chip-name` | `aculta-chip__name` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-chip-remove` | `aculta-chip__remove` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `notice-admin-item` | `admin-item` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NoticeAdminController.php` |
| `notice-admin-intro` | `aculta-notice-admin-intro` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NoticeAdminController.php` |
| `notice-admin-list` | `admin-list`, `aculta-notice-admin-list` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NoticeAdminController.php` |
| `notice-admin-table` | `aculta-notice-admin-table` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NoticeAdminController.php` |
| `public-profile-avatar-image` | `portal-profile__avatar-image` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/LegacyMemorialController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `achievement-notices` | `aculta-achievement-notices` | `web/modules/custom/aculta_portal/modules/aculta_game/src/Hook/MessengerNotificationHooks.php` |
| `aculta-image-audit` | `aculta-image-audit` | `web/modules/custom/aculta_portal/modules/aculta_image/src/Controller/ImageAuditController.php` |
| `donation-summaries` | `d-grid`, `gap-3` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php` |
| `donation-summary` | `card`, `card-body` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php`<br>`web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationItemPaneBase.php` |
| `donation-summary-edit` | `btn`, `btn-sm`, `btn-outline-primary` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php` |
| `donation-document-link` | `link-primary` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php` |
| `donation-thanks` | `card`, `card-body`, `text-center` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-donor-name` | `fw-semibold` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-clause` | `text-muted` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-amount-block` | `my-3` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-amount` | `display-6`, `fw-bold` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-monthly` | `badge`, `text-bg-secondary` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-in-honor` | `fst-italic` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-account-invite` | `alert`, `alert-info`, `mt-3` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `donation-account-invite-link` | `btn`, `btn-outline-primary` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `register-popup` | `d-grid`, `gap-3` | `web/modules/custom/aculta_portal/src/Form/RegisterPopupForm.php` |
| `shop-products` | `d-grid`, `gap-3`, `mt-4` | `web/modules/custom/aculta_portal/src/Controller/ShopController.php` |
| `shop-product-card` | `d-block`, `border`, `border-2`, `rounded-3`, `text-start`, `p-3`, `text-decoration-none` | `web/modules/custom/aculta_portal/src/Controller/ShopController.php` |
| `requirements-cep` | `aculta-portal-requirements__cep` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` |
| `requirements-cep-table` | `aculta-portal-requirements__cep-table` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` |
| `requirements-php-extensions` | `aculta-portal-requirements__php-extensions` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` (tabela de extensões PHP) |
| `lms-forum-moderation-overview` | `aculta-lms-forum-moderation__overview` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Controller/LmsForumModerationController.php` (resumo da moderação do Fórum no LMS) |
| `lms-forum-moderation-queue` | `aculta-lms-forum-moderation__queue` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Controller/LmsForumModerationController.php` (fila de moderação por grupo) |
| `lms-activity-discussions` | `card`, `card-body`, `mt-3` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsActivityDiscussionResolver.php` |
| `lms-h5p-activity` | `d-grid`, `gap-3` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/LmsH5PDiscussionHooks.php` (player e conversas) |
| `lms-h5p-discussions` | `card`, `card-body`, `mt-3` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsH5PDiscussionResolver.php` |
| `lms-h5p-course-documents` | `mt-3` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldFormatter/aculta_H5PDefaultFormatter.php` |
| `status-attention` | `aculta-portal-requirements__status--attention` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` (`status-` + `ok`, `attention` ou `error`) |
| `cep-sources` | `aculta-cep-sources` | `web/modules/custom/aculta_portal/modules/aculta_portal_cep/src/Form/CepSettingsForm.php` |
| `feedback-matched` | `feedback-section`, `matched-phrases` | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `feedback-unmatched` | `feedback-section`, `unmatched-phrases` | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `gallery-dialog` | `aculta-gallery-dialog` | `web/modules/custom/aculta_portal/modules/aculta_gallery/src/Form/GalleryEmbedDialogForm.php` |
| `forum-attachment-dialog` | `aculta-forum-attachment-dialog` | `web/modules/custom/aculta_portal/modules/aculta_forum/src/Form/ForumAttachmentUploadDialogForm.php` |
| `forum-gallery-dialog` | `aculta-forum-gallery-dialog` | `web/modules/custom/aculta_portal/modules/aculta_forum/src/Form/ForumGalleryUploadDialogForm.php` |
| `phrase-match` | `phrase-match` (com `correct-answer`, classe de estado do LMS) | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `phrase-mismatch` | `phrase-mismatch` (com `wrong-answer`, classe de estado do LMS) | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `social-links-icon-instagram` | `bi`, `bi-instagram` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-icon-youtube` | `bi`, `bi-youtube` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-icon-facebook` | `bi`, `bi-facebook` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `h5p-admin-header` | `h5p-admin-header` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Controller/H5PLibraryAdmin.php` |
| `h5p-content-unavailable` | `h5p-content-unavailable` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldFormatter/H5PDefaultFormatter.php` |
| `h5p-dashboard-course` | `aculta-lms-h5p-course` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Controller/H5PDashboardController.php` |
| `checkout-help` | `checkout-help` | `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/PluginForm/Checkout/RedirectCheckoutForm.php` |
| `checkout-guest-registration` | `card`, `card-body`, `mb-3` | `web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane/AcultaCheckoutLogin.php` |
| `checkout-google` | `d-flex`, `justify-content-center`, `mb-3` | `web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane/AcultaCheckoutLogin.php` |
| `forum-category-count` | `aculta-forum-category__count` | `web/modules/custom/aculta_portal/modules/aculta_forum/src/Plugin/views/field/ForumCategoryTopicCount.php` |


## Famílias sem mapeamento de tema

- `patcher-*`: invólucros de tabela administrativa do Patcher, com estilo local
  no Claro.
- `studio-mercadopago-*`: conteúdo contextual do Studio sem estilo próprio no
  ACULTA420.

## Tokens com mapeamento de tema

| Token | Classes no ACULTA420 | Origem no módulo/tema |
| --- | --- | --- |
| `editorial-card` | `aculta-card card` | `web/modules/custom/aculta_portal/src/Editorial/CardVariationManager.php` |
| `forum-report-queue` | `aculta-forum-report-queue` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/ReportQueueController.php` |
| `forum-report-pager` | `aculta-pager` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/ReportQueueController.php` |
| `fiscal-identity-other-data` | `aculta-fiscal-identity-other-data` | `web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane/FiscalIdentityPane.php` |
| `wiki-proposal-contribute` | `button button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Hook/WikiProposalLinkHooks.php` |
| `statistics-panel` | `aculta-statistics` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-areas` | `aculta-statistics__areas` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-controls` | `aculta-statistics__controls` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-form` | `aculta-statistics__form` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-summary` | `aculta-statistics__summary` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-comparison` | `aculta-statistics__comparison` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-chart` | `aculta-statistics__chart` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-table` | `aculta-statistics__table` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-note` | `aculta-statistics__note` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-wiki-revisions` | `aculta-statistics__wiki-revisions` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `statistics-wiki-ranking` | `aculta-statistics__wiki-ranking` | `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Controller/StatisticsDashboardController.php` |
| `lms-class-roster` | `aculta-lms-class-roster d-grid gap-3` | `web/modules/custom/aculta_portal/modules/aculta_lms/src/ClassRosterManager.php` |
| `consent-banner` | `aculta-consent` | `web/modules/custom/aculta_portal/modules/aculta_statistics/js/consent.js` |
| `secrets-environment` | `aculta-portal-secrets__environment` | `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/src/Controller/SecretsController.php` |
| `links-page-link` | `aculta-links-page__link` | `web/modules/custom/aculta_portal/modules/aculta_links/src/Controller/LinkPagePublicController.php` |
| `secrets-tabs` | `secrets-tabs` | `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/js/secrets-tabs.js` |
| `secrets-tab` | `secrets-tabs__tab` | `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/js/secrets-tabs.js` |
| `secrets-tabs-list` | `secrets-tabs__list` | `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/src/Controller/SecretsController.php` |
| `secrets-tabs-panel` | `secrets-tabs__panel` | `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/src/Controller/SecretsController.php` |
| `secrets-table-scroll` | `secrets-table-scroll` | `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/src/Controller/SecretsController.php` |
| `notice-related-group` | `aculta-messenger-notice-related-group` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Service/NoticeRelatedGrouper.php` |
| `notice-related-group-items` | `aculta-messenger-notice-related-group__items` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Service/NoticeRelatedGrouper.php` |
| `notice-origin-section` | `aculta-messenger-notice-origin` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Service/NoticeOriginGrouper.php` |
| `notice-origin-separator` | `aculta-messenger-notice-origin__separator` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Service/NoticeOriginGrouper.php` |
| `notice-origin-heading` | `aculta-messenger-notice-origin__heading` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Service/NoticeOriginGrouper.php` |
| `notice-origin-items` | `aculta-messenger-notice-origin__items` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Service/NoticeOriginGrouper.php` |
| `new-conversation-fallback-search` | `aculta-messenger-fallback-search d-grid gap-2` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NewConversationController.php` |
| `user-upload-dialog` | `aculta-user-upload-dialog` | `web/modules/custom/aculta_portal/src/Upload/UserUploadDialogBuilder.php` |
| `we-sativa-start` | `d-grid gap-4 we-sativa__workspace` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiContributorWorkspace.php` |
| `we-sativa-intent` | `d-grid gap-4 we-sativa__workspace` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Controller/WikiContributionIntentController.php` |
| `wiki-title-suggestions` | `aculta-wiki-title-suggestions list-group` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Hook/WikiEntryTitleSuggestions.php` |
| `wiki-proposal-link` | `aculta-wiki-proposal-link btn btn-outline-primary` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Hook/WikiProposalLinkHooks.php` |
| `wiki-proposal-decision` | `aculta-wiki-proposal-decision` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Form/WikiProposalDecisionForm.php` |
| `wiki-proposal-status` | `aculta-wiki-proposal-status` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Controller/WikiProposalStatusController.php` |
| `green-stash-entry` | `aculta-green-stash` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Experience/GreenStashExperienceProvider.php` |
| `green-stash-product-create` | `aculta-green-stash__product-create` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Form/GreenStashProductCreateForm.php` |
| `green-stash-navigation-link` | `nav-link aculta-green-stash__navigation-link` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Navigation/GreenStashNavigation.php` |
| `green-stash-navigation` | `nav aculta-green-stash__navigation` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Navigation/GreenStashNavigation.php` |
| `green-stash-products` | `aculta-green-stash__products` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Controller/GreenStashController.php` |
| `green-stash-shipments` | `aculta-green-stash__shipments` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Controller/GreenStashController.php` |
| `green-stash-product-detail` | `aculta-green-stash__product-detail` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Controller/GreenStashProductController.php` |
| `event-card` | `event-card` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-supporters` | `event-supporters sponsor-logos` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporters-list` | `sponsor-logos-list` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-item` | `sponsor-item` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-tier` | `sponsor-cota` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-name` | `sponsor-name` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-link` | `sponsor-link` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-section-title` | `section-title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-press-quotes` | `event-press-quotes media-mentions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-press-quotes-list` | `press-quotes-list` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-press-quote` | `press-quote` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `document-download-item` | `aculta-download-meta-item` | `web/modules/custom/aculta_portal/modules/aculta_document_viewer/src/Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php` |
| `document-download-link` | `download-link` | `web/modules/custom/aculta_portal/modules/aculta_document_viewer/src/Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php` |
| `document-download-format` | `download-badge-format` | `web/modules/custom/aculta_portal/modules/aculta_document_viewer/src/Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php` |
| `document-download-size` | `download-badge-size` | `web/modules/custom/aculta_portal/modules/aculta_document_viewer/src/Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php` |
| `event-card-cancelled` | `event-card--cancelado` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-card-type` | `event-card__tipo` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-card-title` | `event-card__titulo` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-card-subtitle` | `event-card__subtitulo` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-card-meta` | `event-card__meta` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-card-cancel-status` | `event-card__aviso` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `calendar-interactive-notice` | `aculta-calendar-interactive__aviso` | `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Controller/InteractiveController.php` |
| `calendar-interactive-controls` | `aculta-calendar-interactive__controle` | `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Controller/InteractiveController.php` |
| `calendar-interactive` | `aculta-calendar-interactive` | `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Controller/InteractiveController.php` |
| `calendar-interactive-alternative` | `aculta-calendar-interactive__alternativa` | `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Controller/InteractiveController.php` |
| `calendar-interactive-agenda` | `aculta-calendar-interactive__agenda` | `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Controller/InteractiveController.php` |
| `calendar-embed-hosts` | `aculta-calendar-embed-hosts` | `web/modules/custom/aculta_portal/modules/aculta_calendar/src/Controller/EmbedHostsController.php` |
| `map-widget` | `aculta-map` | `web/modules/custom/aculta_portal/modules/aculta_maps/src/MapRenderer.php` |
| `map-location-route` | `aculta-map__location-route` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/MapController.php` |
| `map-privacy-notice` | `aculta-map__aviso` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/MapController.php` |
| `map-wrapper` | `aculta-map-wrapper` | `web/modules/custom/aculta_portal/modules/aculta_maps/src/MapRenderer.php` |
| `map-geocoding-attribution` | `aculta-map__geocoding-attribution` | `web/modules/custom/aculta_portal/modules/aculta_maps/src/MapRenderer.php` |
| `document-download-item` | `aculta-download-meta-item` | `web/modules/custom/aculta_portal/modules/aculta_document_viewer/src/Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php` |
| `document-download-link` | `download-link` | `web/modules/custom/aculta_portal/modules/aculta_document_viewer/src/Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php` |
| `studio-shell` | `studio` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-marley-entry` | `btn btn-outline-primary studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/MarleyExperienceProvider.php` |
| `studio-account-home` | `studio__panel card card-body` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php` |
| `studio-profile-entry` | `btn btn-outline-primary studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/ProfileExperienceProvider.php` |
| `studio-profile-nickname-edit` | `btn btn-outline-primary studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-photo-edit` | `btn btn-outline-primary studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-actions` | `studio__actions list-unstyled d-flex flex-wrap gap-2` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-public-link` | `btn btn-outline-primary studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-editor` | `aculta-profile-editor` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-fidelity-scope` | `studio` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Learning/LearnerDashboardComposer.php` |
| `studio-bio-form`, `studio-social-form` | `studio-profile-form` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php`; `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/SocialLinkForm.php` |
| `studio-saved-content` | `studio studio-saved-page` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-summary` | `studio-saved-summary` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/SavedExperienceProvider.php` |
| `studio-page-title` | `page-title` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-tabs` | `nav nav-tabs studio-saved-tabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-list-wrapper` | `studio-saved-list-wrapper` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-empty` | `studio-saved-empty` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-card-compact-list` | `studio-card-compact-list` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-card-compact` | `studio-card-compact` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `saved-item` | `saved-item` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-card-title` | `compact-card-title` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-meta` | `compact-card-meta` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-purpose` | `badge badge-purpose` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pager-prev` | `pager-prev` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pager-info` | `pager-info` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pager-next` | `pager-next` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pagination` | `studio-saved-pagination` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `recommendation-item` | `recommendation-item` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-recommendation-reason` | `recommendation-reason` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-recommendation-badge` | `badge-reason` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-content` | `studio studio-saved-page` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-page-title` | `page-title` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-tabs` | `nav nav-tabs studio-saved-tabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-account-section` | `studio studio__layout` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioAccountSectionController.php` |
| `studio-account-nav-link` | `nav-link studio__link` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioAccountSectionController.php` |
| `studio-account-contextual-nav` | `nav nav-tabs studio__subtabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioAccountSectionController.php` |
| `studio-hub` | `studio` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioHubController.php` |
| `studio-experiences` | `studio__actions list-unstyled d-grid gap-2` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioHubController.php` |
| `studio-tabs` | `nav nav-tabs studio__tabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-tab` | `nav-link studio__tab` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-panel` | `studio__panel card card-body` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-heading` | `studio__heading h4` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-actions` | `studio__actions list-unstyled d-flex flex-wrap gap-2` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-action` | `btn btn-outline-primary studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-empty` | `text-muted` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-layout` | `studio__layout` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-sidebar` | `studio__sidebar` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-main` | `studio__main` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-sections` | `studio__sections list-unstyled` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-subtabs` | `nav nav-tabs studio__subtabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-link` | `nav-link studio__link` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-live` | `studio__live small text-muted` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-listing` | `table table-hover align-middle studio__listing` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/MarleyListingBuilder.php` |
| `studio-listing-wrap` | `table-responsive studio__listing-wrap` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/MarleyListingBuilder.php` |

| `account-entry` | `studio aculta-account-entry` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `account-experiences` | `studio__actions list-unstyled d-grid gap-2` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `button-ghost` | `button button--ghost` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-catalogue-actions-bar` | `actions-bar my-3` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-actions` | `aculta-event-date-actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Event/EventDateListBuilder.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-add` | `button button--primary` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-cancel` | `button` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventDateDeleteForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-delete` | `button button--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventDateDeleteForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-order` | `aculta-event-date-order` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventDateOrderForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-row` | `aculta-event-date-row` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-actions-bar` | `actions-bar mb-3` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-actions` | `location-actions mt-2 mb-3` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php` |
| `event-location-address` | `aculta-location-address text-muted mb-2` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-admin-actions` | `location-admin-actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-agenda-empty` | `location-agenda-empty` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-agenda` | `location-agenda` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge-draft` | `badge--warning` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge-free` | `badge badge-free` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge-public` | `badge--success` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge` | `aculta-location-badge badge` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-action` | `button button--action` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php` |
| `event-location-button-danger` | `button button--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-primary-action` | `button button--primary button--action` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-location-button-primary-small` | `button button--small button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-primary` | `button button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php` |
| `event-location-button-secondary-small` | `button button--secondary button--small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-secondary` | `button button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php` |
| `event-location-date-time` | `event-date-time` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-empty` | `aculta-location-empty my-2` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-fallback` | `description mt-1` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-header` | `aculta-location-header` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-page` | `aculta-location-page` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-search-bar` | `container-inline mb-3` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-summary-card` | `aculta-location-summary-card card p-3 my-2` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-target-id` | `aculta-location-target-id` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php` |
| `event-location-title` | `aculta-location-title h5 mb-1` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-type-badge` | `badge badge-location-type` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-widget` | `aculta-event-location-widget` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php` |
| `event-locations-list-page` | `aculta-locations-list-page` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-dashboard` | `aculta-events-organizer-dashboard` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-filter-form` | `form--inline clearfix` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-filters` | `organizer-dashboard-filters` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-guide` | `culturas-guide` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `notice-status` | `messages messages--status` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-widget` | `aculta-event-org-widget` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-target-id` | `aculta-event-organization-target-id` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-intro` | `lead` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-search` | `search-box` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-location-fields` | `aculta-event-organization-location-fields` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-results-table` | `org-modal-results-table` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-small-button` | `button button--small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-search-action` | `button button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-add-action` | `button button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-back-action` | `button` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-summary-card` | `event-org-card` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-title` | `event-org-card__title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-badge` | `event-org-card__badge` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-locality` | `event-org-card__locality` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-button-secondary` | `button button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-button-danger` | `button button--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-empty` | `event-org-empty` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-button-primary` | `button button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizer-card` | `coorganizer-item clearfix` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizer-title` | `coorganizer-item__title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-coorg-button-add` | `button button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-organization-admin-overview` | `aculta-events-organization-admin-overview` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `admin-org-filters` | `form--inline clearfix` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-block` | `aculta-related-content` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizers-list` | `coorganizers-list` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizer-badge` | `badge coorganizer-item__badge` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-fallback` | `text-muted small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-success` | `badge badge--success` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-warning` | `badge badge--warning` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-info` | `badge badge--info` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-danger` | `badge badge--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-admin-filter-fields` | `aculta-event-organization-admin-filter-fields` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-profile` | `aculta-organization-profile` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-unclaimed` | `organization-badge organization-badge--unclaimed` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-header` | `organization-header` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-body` | `organization-body` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-links` | `organization-links` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-events` | `organization-events` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-actions` | `organization-footer-actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-title` | `organization-title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-location` | `organization-location` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-description` | `organization-description` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-event-link` | `organization-event-item` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-admin-link` | `organization-admin-link text-muted small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-admin-action` | `button button--secondary button--small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-item` | `aculta-related-content__item` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-link` | `aculta-related-content__link` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-title` | `aculta-related-content__title` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-badge` | `aculta-related-content__badge` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-summary` | `aculta-related-content__summary` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-admin-overview` | `aculta-events-admin-overview` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventAdminOverviewController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-upcoming-block` | `aculta-events-upcoming` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Block/UpcomingEventsBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-upcoming-link` | `aculta-events-upcoming__link` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Block/UpcomingEventsBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `ugc-admin-purpose-filters` | `aculta-admin-purpose-filters action-links` | `web/modules/custom/aculta_portal/modules/aculta_ugc/src/Controller/UgcModerationDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `ugc-admin-status-filters` | `aculta-admin-status-filters action-links` | `web/modules/custom/aculta_portal/modules/aculta_ugc/src/Controller/UgcModerationDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `user-admin-filters` | `aculta-admin-filters action-links` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/OwnershipClaimAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
## Painéis de vendas e doações (dev.69 e 0.6.0-dev.67)

| Token | Classes no ACULTA420 | Origem no módulo |
| --- | --- | --- |
| `sales-panel` | `aculta-sales-panel` | `web/modules/custom/aculta_portal/src/Controller/SalesReportController.php` |
| `sales-section` | `aculta-sales-panel__section` | `web/modules/custom/aculta_portal/src/Controller/SalesReportController.php` |
| `sales-heading` | `aculta-sales-panel__heading` | `web/modules/custom/aculta_portal/src/Controller/SalesReportController.php` |
| `sales-table` | `table table-hover align-middle aculta-sales-panel__table` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-pagination` | `aculta-sales-panel__pagination` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-payment` | `aculta-sales-panel__payment` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-action` | `btn btn-sm btn-outline-primary aculta-sales-panel__action` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-action-danger` | `btn btn-sm btn-outline-danger aculta-sales-panel__action` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-state-ok` | `badge aculta-sales-panel__state aculta-sales-panel__state--ok` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-state-pending` | `badge aculta-sales-panel__state aculta-sales-panel__state--pending` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-state-refunded` | `badge aculta-sales-panel__state aculta-sales-panel__state--refunded` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-state-voided` | `badge aculta-sales-panel__state aculta-sales-panel__state--voided` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `sales-state-neutral` | `badge aculta-sales-panel__state aculta-sales-panel__state--neutral` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
| `support-amounts` | `aculta-support-amounts` | `web/modules/custom/aculta_portal/src/Support/Form/SupportForm.php` |
