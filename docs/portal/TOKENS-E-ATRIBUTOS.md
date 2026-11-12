# Tokens e atributos do Portal para o tema

> Reconciliado com o código em 2026-10-30 e validado pelo gate `scripts/validate-portal-ui-docs.php`. Quando um token ou atributo novo for criado, ele precisa entrar aqui (o gate reprova o contrário).
>
> **Atualização 2026-11-05:** as classes Bootstrap saíram do mapa do V2 (`bi` e `bi-*`, `breadcrumb`, `breadcrumb-item`, `btn`, `btn-link`, `form-control`, `btn-primary`, `btn-outline-primary`, `btn-outline-secondary`, `btn-sm`). Onde as tabelas abaixo ainda as citam, a lista descreve a origem histórica do token; o mapa vigente está em `aculta420_v2/src/Hook/PortalUiHooks.php`. Toda classe mapeada agora tem CSS mínimo em `aculta420_v2/src/styles/portal-ui-map.css` ou exceção em `aculta420_v2/docs/classes-sem-css-excecoes.md`, verificado por `scripts/validate-aculta420-mapped-classes.php`.

## Como o contrato funciona

- O **módulo** (`aculta_portal` e submódulos) marca elementos com `data-portal-ui="token"` e atributos de comportamento com os prefixos data-portal-, data-aculta- e data-dialog-. Nunca escreve classe de design.
- O **tema** (`aculta420`) traduz cada token em classes em `src/Hook/PortalUiHooks.php` (`TOKENS`). Onde o hook não se aplica (formulários e links), o atributo continua no HTML e o CSS pode selecioná-lo diretamente.
- O CSS do tema aceita **classe ou atributo** quando o elemento pode vir de qualquer um dos dois caminhos: `:is(.classe, [data-portal-ui="token"])`.
- Tamanho, posição, cores e espaçamento são sempre do tema. O módulo não informa largura, altura nem posição de diálogo (ver `POPUP-PADRAO.md`).

## Como adicionar um token

1. No módulo, use `data-portal-ui="novo-token"` (`#attributes`, `#element_attributes` ou template).
2. No tema, inclua `'novo-token' => ['classe-do-tema']` em `PortalUiHooks::TOKENS`. A classe precisa de CSS no tema ou de exceção em `aculta420_v2/docs/classes-sem-css-excecoes.md`.
3. Documente o token na tabela abaixo (nome, classes, uso).
4. Rode `php scripts/validate-portal-ui-docs.php`, `php scripts/validate-aculta420-mapped-classes.php` e `php scripts/validate-module-theme-boundary.php`.

## Atributos

| Atributo | Família | Uso (JS) | Arquivos |
| --- | --- | --- | ---: |
| `data-aculta-account-root` | exato | sim | 2 |
| `data-aculta-account-live` | exato | sim | 2 |
| `data-aculta-account-main` | exato | não | 1 |
| `data-aculta-account-link` | exato | sim | 2 |
| `data-aculta-account-retry` | exato | sim | 1 |

Atributos: não há wildcard. Todo nome usado no código aparece na tabela acima (o gate reprova o que não estiver).
| `data-aculta-account-list` | exato | sim | 2 |
| `data-aculta-add-member` | exato | sim | 2 |
| `data-aculta-album-open` | exato | sim | 2 |
| `data-aculta-album-toggle` | exato | sim | 2 |
| `data-aculta-alert-dismiss` | exato | sim | 1 |
| `data-aculta-alerts-count` | exato | sim | 2 |
| `data-aculta-alinhamento` | exato | sim | 1 |
| `data-aculta-bio-clear` | exato | sim | 2 |
| `data-aculta-bio-form` | exato | sim | 2 |
| `data-aculta-cart-count` | exato | não | 2 |
| `data-aculta-cart-trigger` | exato | não | 2 |
| `data-aculta-dialog-trigger` | exato | sim | 2 |
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
| `data-aculta-focus` | exato | sim | 2 |
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
| `data-aculta-register-content` | exato | sim | 1 |
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
| `data-aculta-studio-profile-root` | exato | não | 1 |
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
| `new-conversation-link` | `aculta-messenger-new-conversation` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `new-conversation-submit` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-back` | `aculta-new-conversation-back` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `conversation-action` | `aculta-messenger-options__action` |
| `conversation-notice-action` | `aculta-messenger-notice__action` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/ConversationRelationshipForm.php` |
| `new-conversation-links` | `aculta-messenger-links` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `new-conversation-message` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
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
| `search-utility` | `aculta-search-utility`, `aculta-utility-trigger` | `web/modules/custom/aculta_portal/src/Search/SearchUtility.php` |
| `search-popup` | `aculta-search-popup` | `web/modules/custom/aculta_portal/src/Search/Form/SearchPopupForm.php` |
| `search-preview` | `aculta-search-preview` | `web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php`<br>`web/modules/custom/aculta_portal/src/Search/Form/SearchPopupForm.php` |
| `search-preview-input` | `aculta-search-preview-input` | `web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php`<br>`web/modules/custom/aculta_portal/src/Search/Form/SearchPopupForm.php` |
| `search-preview-list` | `aculta-search-preview__list` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Search/SearchPreviewController.php` |
| `search-preview-footer` | `aculta-search-preview__footer` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Search/SearchPreviewController.php` |
| `search-preview-full` | `aculta-search-preview__full` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Search/SearchPreviewController.php` |
| `hint` | `aculta-hint` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php`<br>`web/modules/custom/aculta_portal/src/Presentation/WikiSearchFormBuilder.php` |
| `hint-muted` | `aculta-hint`, `aculta-hint--muted` | `web/modules/custom/aculta_portal/src/Search/SearchPageController.php` |
| `icon-search` | `bi`, `bi-search` | (montado dinamicamente ou só no tema) |
| `visually-hidden` | `visually-hidden` | (montado dinamicamente ou só no tema) |
| `wiki-home` | `aculta-wiki-home` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `wiki-intro` | `aculta-wiki-intro` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `wiki-search` | `aculta-wiki-search` | (montado dinamicamente ou só no tema) |
| `wiki-search-results` | `aculta-wiki-search-results` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `wiki-recent-change` | `aculta-wiki-recent-change` | `web/modules/custom/aculta_portal/src/Controller/WikiController.php` |
| `we-sativa-workspace` | `aculta-we-sativa__workspace` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiContributorWorkspace.php` |
| `we-sativa-create` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiContributorWorkspace.php` |
| `wiki-workspace-return` | `aculta-wiki-workspace__return` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Hook/WikiEntryImageHooks.php` |
| `courses-home` | `courses-home` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `courses-home-intro` | `courses-home__intro` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `courses-home-collaboration` | `courses-home__collaboration` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `account-courses` | `portal-account-courses` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-courses-summary` | `portal-account-courses-summary` | (montado dinamicamente ou só no tema) |
| `account-course` | `portal-account-course` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-course-meta` | `portal-account-course__meta` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-avatar` | `portal-account__avatar` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/AvatarRerollController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php` |
| `account-photo-preview` | `portal-account-photo__preview` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php`<br>`web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `account-bio-card` | `aculta-panel`, `aculta-panel__body`, `portal-account-bio` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-dialog-body` | `aculta-utility-dialog__body` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-dialog-close` | `aculta-utility-dialog__close` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-dialog-header` | `aculta-utility-dialog__header` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-dialog-title` | `aculta-utility-dialog__title` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-text` | `portal-account-bio__text` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-edit` | `portal-account-bio__edit` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Bio/AccountBioCard.php` |
| `account-bio-actions` | `portal-account-bio__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Form/SocialLinkForm.php` |
| `account-photo-actions` | `portal-account-photo__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php` | Linha de ações do popup de foto (sortear, remover e salvar). Estilo em `portal-account.css`. |
| `account-overview-actions` | `portal-account-overview__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/AccountDashboardHooks.php` |
| `account-view-profile` | `portal-account-overview__profile` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/AccountDashboardHooks.php` |
| `post-meta` | `portal-post__meta` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/PostPresentation.php` |
| `post-meta-author` | `portal-post__meta-item` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/PostPresentation.php` |
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
| `public-profile-link-email` | `portal-profile__link` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
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
| `account-social-edit` | `portal-account-social__edit` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Social/SocialLinksCard.php` |
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
| `social-links-list` | `aculta-social-links__list` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-footer-inner` | `aculta-social-links-footer__inner` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-settings-button` | `aculta-social-links-settings-button` | `web/modules/custom/aculta_portal/modules/aculta_linkweed/src/Form/SocialLinksSettingsForm.php` |
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
| `account-bio-dialog` | `aculta-utility-dialog` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php` |
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
| `button-secondary` | `button`, `button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Form/SocialLinkForm.php`<br>`web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php` |
| `button-primary` | `button`, `button--primary` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php`<br>`web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php` |
| `aculta-button-secondary` | `aculta-button`, `aculta-button--secondary` | `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` |
| `aculta-button-primary` | `aculta-button`, `aculta-button--primary` | `web/modules/custom/aculta_portal/src/Controller/RegistrationController.php` |
| `aculta-button-outline` | `aculta-button`, `aculta-button--outline` | `web/modules/custom/aculta_portal/src/Support/Form/SupportForm.php` |
| `status-attention` | `aculta-portal-requirements__status--attention` | (montado dinamicamente ou só no tema) |
| `breadcrumb-list` | `breadcrumb` | `web/modules/custom/aculta_portal/src/Render/AccountAjaxMainContentRenderer.php` |

| `new-conversation-cancel` | `aculta-new-conversation-cancel` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/NewConversationForm.php` |
| `public-profile-actions` | `portal-profile__actions` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
| `friendship-action-form` | `portal-profile__friendship-form` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/FriendshipActionForm.php` |
| `friendship-action-button` | `portal-account-overview__profile` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/FriendshipActionForm.php` |
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
| `messenger-options-icon` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Support/OptionsDisclosure.php` |
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
| `donation-summaries` | `aculta-donation__summaries` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php` |
| `donation-summary` | `aculta-panel`, `aculta-panel__body` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php`<br>`web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationItemPaneBase.php` |
| `donation-summary-edit` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php` |
| `donation-document-link` | `aculta-donation__document-link` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutFlow/DonationCheckoutFlow.php` |
| `donation-thanks` | `aculta-panel`, `aculta-panel__body`, `aculta-donation__thanks` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-donor-name` | `aculta-donation__donor` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-clause` | `aculta-donation__clause` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-amount-block` | `aculta-donation__amount-block` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-amount` | `aculta-donation__amount` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-monthly` | `aculta-donation__monthly` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-in-honor` | `aculta-donation__honor` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/ThankYouPane.php` |
| `donation-account-invite` | `messages`, `messages--info`, `aculta-donation__invite` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `donation-account-invite-link` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `donation-register-dialog` | `aculta-utility-dialog` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `donation-register-dialog-body` | `aculta-utility-dialog__body` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `donation-register-dialog-close` | `aculta-utility-dialog__close` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `donation-register-dialog-header` | `aculta-utility-dialog__header` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `donation-register-dialog-title` | `aculta-utility-dialog__title` | `web/modules/custom/aculta_portal/modules/aculta_donation/src/Plugin/Commerce/CheckoutPane/DonationCheckoutPane.php` |
| `register-popup` | `aculta-register-popup` | `web/modules/custom/aculta_portal/src/Form/RegisterPopupForm.php` |
| `shop-products` | `aculta-shop-grid` | `web/modules/custom/aculta_portal/src/Controller/ShopController.php` |
| `shop-product-card` | `aculta-shop-card` | `web/modules/custom/aculta_portal/src/Controller/ShopController.php` |
| `requirements-cep` | `aculta-portal-requirements__cep` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` |
| `requirements-cep-table` | `aculta-portal-requirements__cep-table` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` |
| `requirements-php-extensions` | `aculta-portal-requirements__php-extensions` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` (tabela de extensões PHP) |
| `lms-forum-moderation-overview` | `aculta-lms-forum-moderation__overview` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Controller/LmsForumModerationController.php` (resumo da moderação do Fórum no LMS) |
| `lms-forum-moderation-queue` | `aculta-lms-forum-moderation__queue` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Controller/LmsForumModerationController.php` (fila de moderação por grupo) |
| `lms-activity-discussions` | `aculta-panel`, `aculta-panel__body`, `aculta-lms-panel` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsActivityDiscussionResolver.php` |
| `lms-h5p-activity` | `aculta-lms-h5p__activity` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/LmsH5PDiscussionHooks.php` (player e conversas) |
| `lms-h5p-discussions` | `aculta-panel`, `aculta-panel__body`, `aculta-lms-panel` | `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsH5PDiscussionResolver.php` |
| `lms-h5p-course-documents` | `aculta-lms-h5p__documents` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldFormatter/aculta_H5PDefaultFormatter.php` |
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
| `checkout-guest-registration` | `aculta-panel`, `aculta-panel__body`, `aculta-checkout__guest` | `web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane/AcultaCheckoutLogin.php` |
| `checkout-google` | `aculta-checkout__google` | `web/modules/custom/aculta_portal/src/Plugin/Commerce/CheckoutPane/AcultaCheckoutLogin.php` |
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
| `lms-class-roster` | `aculta-lms-class-roster` | `web/modules/custom/aculta_portal/modules/aculta_lms/src/ClassRosterManager.php` |
| `consent-banner` | `aculta-consent` | `web/modules/custom/aculta_portal/modules/aculta_statistics/js/consent.js` |
| `secrets-environment` | `aculta-portal-secrets__environment` | `web/modules/custom/aculta_portal/modules/aculta_secrets_manager/src/Controller/SecretsController.php` |
| `links-page-link` | `aculta-links-page__link` | `web/modules/custom/aculta_portal/modules/aculta_linkweed/src/Controller/LinkPagePublicController.php` |
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
| `new-conversation-fallback-search` | `aculta-messenger-fallback-search`, `aculta-messenger-fallback-search--grid` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/NewConversationController.php` |
| `user-upload-dialog` | `aculta-user-upload-dialog` | `web/modules/custom/aculta_portal/src/Upload/UserUploadDialogBuilder.php` |
| `we-sativa-start` | `aculta-we-sativa__start` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiContributorWorkspace.php` |
| `we-sativa-intent` | `aculta-we-sativa__intent` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Controller/WikiContributionIntentController.php` |
| `wiki-title-suggestions` | `aculta-wiki-title-suggestions` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Hook/WikiEntryTitleSuggestions.php` |
| `wiki-proposal-link` | `aculta-wiki-proposal-link` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Hook/WikiProposalLinkHooks.php` |
| `wiki-proposal-decision` | `aculta-wiki-proposal-decision` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Form/WikiProposalDecisionForm.php` |
| `wiki-proposal-status` | `aculta-wiki-proposal-status` | `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Controller/WikiProposalStatusController.php` |
| `green-stash-entry` | `studio`, `aculta-green-stash` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Experience/GreenStashExperienceProvider.php` |
| `green-stash-product-create` | `aculta-green-stash__product-create` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Form/GreenStashProductCreateForm.php` |
| `green-stash-navigation-link` | `nav-link`, `aculta-green-stash__navigation-link` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Navigation/GreenStashNavigation.php` |
| `green-stash-navigation` | `aculta-green-stash__navigation` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Navigation/GreenStashNavigation.php` |
| `green-stash-products` | `aculta-green-stash__products` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Controller/GreenStashController.php` |
| `green-stash-shipments` | `aculta-green-stash__shipments` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Controller/GreenStashController.php` |
| `green-stash-product-detail` | `aculta-green-stash__product-detail` | `web/modules/custom/aculta_portal/modules/aculta_commerce/src/Controller/GreenStashProductController.php` |
| `event-card` | `event-card` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-supporters` | `event-supporters`, `sponsor-logos` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporters-list` | `sponsor-logos-list` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-item` | `sponsor-item` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-tier` | `sponsor-cota` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-name` | `sponsor-name` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-supporter-link` | `sponsor-link` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-section-title` | `aculta-section-title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
| `event-press-quotes` | `event-press-quotes`, `media-mentions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Editorial/EventEditorialPresenter.php` |
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
| `studio-account-navigation` | `studio-account-section__navigation` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioAccountSectionController.php` |
| `studio-account-identity` | `studio-account-identity` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php` |
| `studio-course-authoring` | `studio-course-authoring` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/CourseAuthoringController.php` |
| `studio-course-authoring-back` | `studio-course-authoring__back` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/CourseAuthoringController.php` |
| `studio-marley-entry` | `studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/MarleyExperienceProvider.php` |
| `studio-account-home` | `studio__panel`, `aculta-panel`, `aculta-panel__body` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php` |
| `studio-account-avatar` | `studio-account-identity__avatar` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `studio-profile-entry` | `studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/ProfileExperienceProvider.php` |
| `studio-profile-nickname-edit` | `studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-photo-edit` | `studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-actions` | `studio__actions` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-public-link` | `studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-profile-editor` | `aculta-profile-editor` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioProfileController.php` |
| `studio-fidelity-scope` | `studio` | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Learning/LearnerDashboardComposer.php` |
| `studio-bio-form`, `studio-social-form` | `studio-profile-form` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/AccountBioForm.php`; `web/modules/custom/aculta_portal/modules/aculta_user/src/Form/SocialLinkForm.php` |
| `studio-saved-content` | `studio`, `studio-saved-page` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-summary` | `studio-saved-summary` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/SavedExperienceProvider.php` |
| `studio-page-title` | `page-title` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-tabs` | `studio-saved-tabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-list-wrapper` | `studio-saved-list-wrapper` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-empty` | `studio-saved-empty` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-card-compact-list` | `studio-card-compact-list` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-card-compact` | `studio-card-compact` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `saved-item` | `saved-item` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-card-title` | `compact-card-title` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-meta` | `compact-card-meta` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-purpose` | `badge`, `badge-purpose` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pager-prev` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pager-info` | `pager-info` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pager-next` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-pagination` | `studio-saved-pagination` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `recommendation-item` | `recommendation-item` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-recommendation-reason` | `recommendation-reason` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-recommendation-badge` | `badge-reason` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-content` | `studio`, `studio-saved-page` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-page-title` | `page-title` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-saved-tabs` | `studio-saved-tabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php` |
| `studio-account-section` | `studio`, `studio-account-section` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioAccountSectionController.php` |
| `studio-account-nav-link` | `nav-link`, `studio__link` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioAccountSectionController.php` |
| `studio-account-contextual-nav` | `studio-account-nav-list` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioAccountSectionController.php`; lista vertical em painel próprio, sem herdar o estilo de abas do Marley |
| `studio-hub` | `studio` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioHubController.php` |
| `studio-experiences` | `studio__actions list-unstyled d-grid gap-2` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/StudioHubController.php` |
| `studio-tabs` | `nav nav-tabs studio__tabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-tab` | `nav-link studio__tab` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-panel` | `studio__panel`, `aculta-panel`, `aculta-panel__body` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-heading` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-actions` | `studio__actions` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-action` | `studio__action` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-heading` | (sem classe; estilizado por `[data-portal-ui]`) | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-empty` | `text-muted` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-layout` | `studio__layout` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-sidebar` | `studio__sidebar` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-main` | `studio__main` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-sections` | `studio__sections list-unstyled` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-subtabs` | `nav nav-tabs studio__subtabs` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-link` | `nav-link studio__link` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-live` | `studio__live`, `aculta-studio-live` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/MarleyController.php` |
| `studio-listing` | `studio__listing` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/MarleyListingBuilder.php` |
| `studio-listing-wrap` | `table-responsive`, `studio__listing-wrap` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/MarleyListingBuilder.php` |

| `account-entry` | `studio`, `aculta-account-entry` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `account-experiences` | `studio__actions`, `studio__actions--grid` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `account-entry-links` | `studio__actions`, `studio__actions--grid` | `web/modules/custom/aculta_portal/modules/aculta_studio/src/Experience/AccountExperienceProvider.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `button-ghost` | `button`, `button--ghost` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-catalogue-actions-bar` | `actions-bar`, `aculta-event-catalogue__actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-actions` | `aculta-event-date-actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Event/EventDateListBuilder.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-add` | `button button--primary` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-cancel` | `button` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventDateDeleteForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-delete` | `button`, `button--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventDateDeleteForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-order` | `aculta-event-date-order` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventDateOrderForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-date-row` | `aculta-event-date-row` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-actions-bar` | `actions-bar`, `aculta-location-actions-bar` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-actions` | `location-actions`, `aculta-location-actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php` |
| `event-location-address` | `aculta-location-address` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-admin-actions` | `location-admin-actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-agenda-empty` | `location-agenda-empty` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-agenda` | `location-agenda` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge-draft` | `badge--warning` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge-free` | `badge badge-free` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge-public` | `badge--success` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-badge` | `aculta-location-badge`, `badge` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-action` | `button`, `button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php` |
| `event-location-button-danger` | `button`, `button--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-primary-action` | `button button--primary button--action` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventCatalogueController.php` |
| `event-location-button-primary-small` | `button`, `button--small`, `button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-primary` | `button`, `button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php` |
| `event-location-button-secondary-small` | `button button--secondary button--small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-button-secondary` | `button`, `button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php` |
| `event-location-date-time` | `event-date-time` | `web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-empty` | `aculta-location-empty` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-fallback` | `description`, `aculta-location-fallback` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-header` | `aculta-location-header` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-page` | `aculta-location-page` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-search-bar` | `aculta-location-search` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-summary-card` | `aculta-location-summary-card`, `aculta-panel` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-target-id` | `aculta-location-target-id` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php` |
| `event-location-title` | `aculta-location-title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventLocationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-type-badge` | `badge`, `badge-location-type` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-location-widget` | `aculta-event-location-widget` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventForm.php`<br>`web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventLocationModalForm.php` |
| `event-locations-list-page` | `aculta-locations-list-page` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/LocationListController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-dashboard` | `aculta-events-organizer-dashboard` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-filter-form` | `aculta-form--inline` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-filters` | `organizer-dashboard-filters` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organizer-guide` | `culturas-guide` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizerDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `notice-status` | `messages`, `messages--status` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-widget` | `aculta-event-org-widget` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-target-id` | `aculta-event-organization-target-id` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-intro` | `aculta-event-organization__intro` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-search` | `search-box` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-location-fields` | `aculta-event-organization-location-fields` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-results-table` | `org-modal-results-table` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-small-button` | `button`, `button--small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-search-action` | `button`, `button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-add-action` | `button`, `button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-back-action` | `button`, `button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventOrganizationModalForm.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-summary-card` | `event-org-card` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-title` | `event-org-card__title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-badge` | `badge`, `event-org-card__badge` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-locality` | `event-org-card__locality` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-button-secondary` | `button`, `button--secondary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-button-danger` | `button`, `button--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-empty` | `event-org-empty` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-org-button-primary` | `button`, `button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizer-card` | `coorganizer-item` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizer-title` | `coorganizer-item__title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-coorg-button-add` | `button`, `button--primary` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-organization-admin-overview` | `aculta-events-organization-admin-overview` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `admin-org-filters` | `aculta-form--inline` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-block` | `aculta-related-content` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizers-list` | `coorganizers-list` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `coorganizer-badge` | `badge`, `coorganizer-item__badge` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-fallback` | `aculta-event-organization__fallback` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Field/FieldWidget/EventOrganizationModalWidget.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-success` | `badge`, `badge--success` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-warning` | `badge`, `badge--warning` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-info` | `badge`, `badge--info` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-status-danger` | `badge`, `badge--danger` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-admin-filter-fields` | `aculta-event-organization-admin-filter-fields` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-profile` | `aculta-organization-profile` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-unclaimed` | `organization-badge`, `organization-badge--unclaimed` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-header` | `organization-header` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-body` | `organization-body` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-links` | `organization-links` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-events` | `organization-events` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-actions` | `organization-footer-actions` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-title` | `organization-title` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-location` | `organization-location` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-description` | `organization-description` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-event-link` | `organization-event-item` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-admin-link` | `organization-admin-link`, `aculta-event-organization__admin-link` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `event-organization-admin-action` | `button button--secondary button--small` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventOrganizationPageController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-item` | `aculta-related-content__item` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-link` | `aculta-related-content__link` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-title` | `aculta-related-content__title` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-badge` | `aculta-related-content__badge` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `related-content-summary` | `aculta-related-content__summary` | `web/modules/custom/aculta_portal/modules/aculta_index/src/Plugin/Block/RelatedContentBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-admin-overview` | `aculta-events-admin-overview` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Controller/EventAdminOverviewController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-upcoming-block` | `aculta-events-upcoming` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Block/UpcomingEventsBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `events-upcoming-link` | `aculta-events-upcoming__link` | `web/modules/custom/aculta_portal/modules/aculta_events/src/Plugin/Block/UpcomingEventsBlock.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `ugc-admin-purpose-filters` | `aculta-admin-purpose-filters`, `action-links` | `web/modules/custom/aculta_portal/modules/aculta_ugc/src/Controller/CguModerationDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `ugc-admin-status-filters` | `aculta-admin-status-filters`, `action-links` | `web/modules/custom/aculta_portal/modules/aculta_ugc/src/Controller/CguModerationDashboardController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
| `user-admin-filters` | `aculta-admin-filters`, `action-links` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/OwnershipClaimAdminController.php`<br>`web/themes/custom/aculta420/src/Hook/PortalUiHooks.php` |
## Painéis de vendas e doações (dev.69 e 0.6.0-dev.67)

| Token | Classes no ACULTA420 | Origem no módulo |
| --- | --- | --- |
| `sales-panel` | `aculta-sales-panel` | `web/modules/custom/aculta_portal/src/Controller/SalesReportController.php` |
| `sales-section` | `aculta-sales-panel__section` | `web/modules/custom/aculta_portal/src/Controller/SalesReportController.php` |
| `sales-heading` | `aculta-sales-panel__heading` | `web/modules/custom/aculta_portal/src/Controller/SalesReportController.php` |
| `sales-table` | `aculta-sales-panel__table` | `web/modules/custom/aculta_portal/src/Sales/SalesOrdersTableBuilder.php` |
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

## Tokens e atributos do V2 registrados em 2026-11-05

Mapa `PortalUiHooks::TOKENS` do `aculta420_v2`, incluindo a migração de `aculta_lms`, `aculta_lms_resources`, `aculta_paths`, `aculta_studio`, `aculta_events` e `aculta_patcher`. Lista gerada a partir do mapa; a captura visual segue DEFERRED.

| Token | Classes no V2 | Estado |
|---|---|---|
| `surface-list-edit-action` | `button`, `button--small` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `surface-dns-block` | `superficies-dns` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-lesson-preview--activity` | `aculta-lms-lesson-preview__activity` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `lms-answer-review-button` | `button` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-class-status-history--period` | `aculta-lms-class-status-history__period` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `lms-dashboard-links-inline` | `links`, `inline` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-sort-form` | `aculta-lms-course-sort-form` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--actions` | `aculta-lms-course-builder__actions` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--activity` | `aculta-lms-course-builder__activity` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--activities` | `aculta-lms-course-builder__activities` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--sort-handle` | `aculta-lms-course-builder__sort-handle` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--lesson` | `aculta-lms-course-builder__lesson` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--lesson-heading` | `aculta-lms-course-builder__lesson-heading` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--state` | `aculta-lms-course-builder__state` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--published` | `is-published` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--draft` | `is-draft` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--empty` | `aculta-lms-course-builder__empty` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--lessons` | `aculta-lms-course-builder__lessons` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--header` | `aculta-lms-course-builder__header` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-builder--summary` | `aculta-lms-course-builder__summary` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-materials--documents` | `aculta-lms-course-materials__documents` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-lms-course-materials--document` | `aculta-lms-course-materials__document` | mapeado no V2; estilo pendente de captura (DEFERRED) |
| `aculta-btn-outline` | (nenhuma) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-btn-outline"]` ou sem estilo próprio |
| `aculta-btn-primary` | (nenhuma) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-btn-primary"]` ou sem estilo próprio |
| `aculta-course-lesson-preview` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-course-lesson-preview"]` ou sem estilo próprio |
| `aculta-learning-home__actions` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__actions"]` ou sem estilo próprio |
| `aculta-learning-home__course-title` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__course-title"]` ou sem estilo próprio |
| `aculta-learning-home__lesson` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__lesson"]` ou sem estilo próprio |
| `aculta-learning-home__meta` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__meta"]` ou sem estilo próprio |
| `aculta-learning-home__pending` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__pending"]` ou sem estilo próprio |
| `aculta-learning-home__progress` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__progress"]` ou sem estilo próprio |
| `aculta-learning-home__section` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__section"]` ou sem estilo próprio |
| `aculta-learning-home__section-title` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-home__section-title"]` ou sem estilo próprio |
| `aculta-learning-journey` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey"]` ou sem estilo próprio |
| `aculta-learning-journey__back` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__back"]` ou sem estilo próprio |
| `aculta-learning-journey__current` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__current"]` ou sem estilo próprio |
| `aculta-learning-journey__eyebrow` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__eyebrow"]` ou sem estilo próprio |
| `aculta-learning-journey__intro` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__intro"]` ou sem estilo próprio |
| `aculta-learning-journey__lesson` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__lesson"]` ou sem estilo próprio |
| `aculta-learning-journey__lesson-label` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__lesson-label"]` ou sem estilo próprio |
| `aculta-learning-journey__lessons` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__lessons"]` ou sem estilo próprio |
| `aculta-learning-journey__meta` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__meta"]` ou sem estilo próprio |
| `aculta-learning-journey__outline` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__outline"]` ou sem estilo próprio |
| `aculta-learning-journey__progress` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__progress"]` ou sem estilo próprio |
| `aculta-learning-journey__state` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__state"]` ou sem estilo próprio |
| `aculta-learning-journey__summary` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__summary"]` ou sem estilo próprio |
| `aculta-learning-journey__title` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-journey__title"]` ou sem estilo próprio |
| `aculta-learning-room` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-room"]` ou sem estilo próprio |
| `aculta-learning-room__back-link` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-room__back-link"]` ou sem estilo próprio |
| `aculta-learning-room__layout` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-room__layout"]` ou sem estilo próprio |
| `aculta-learning-room__lesson` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-room__lesson"]` ou sem estilo próprio |
| `aculta-learning-room__outline` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-room__outline"]` ou sem estilo próprio |
| `aculta-learning-room__outline-title` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="aculta-learning-room__outline-title"]` ou sem estilo próprio |
| `event-contribution-queue` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="event-contribution-queue"]` ou sem estilo próprio |
| `event-contribution-status` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="event-contribution-status"]` ou sem estilo próprio |
| `event-contribution-workspace` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="event-contribution-workspace"]` ou sem estilo próprio |
| `event-location-contribution-queue` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="event-location-contribution-queue"]` ou sem estilo próprio |
| `event-organization-suggestion-queue` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="event-organization-suggestion-queue"]` ou sem estilo próprio |
| `patcher-table-wrapper` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="patcher-table-wrapper"]` ou sem estilo próprio |
| `studio-course-educator` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="studio-course-educator"]` ou sem estilo próprio |
| `studio-mercadopago-plans` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; estilizado por seletor `[data-portal-ui~="studio-mercadopago-plans"]` ou sem estilo próprio |

| Atributo | Usado em | Função |
|---|---|---|
| `data-aculta-focal` | `modules/aculta_wiki/src/Hook/WikiEntryImageHooks.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-dropzone` | `modules/aculta_file_uploader/src/Element/UploadDropZone.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-learning-home` | `modules/aculta_studio/src/Learning/LearnerDashboardComposer.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-lms` | `modules/aculta_lms/src/Controller/CourseLessonPreviewController.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-sort-item` | `modules/aculta_lms/src/Controller/CourseStructureController.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-sort-handle` | `modules/aculta_lms/src/Controller/CourseStructureController.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-sort-list` | `modules/aculta_lms/src/Controller/CourseStructureController.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-sort-form` | `modules/aculta_lms/src/Controller/CourseStructureController.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-lms-lesson` | `modules/aculta_lms/src/Controller/CourseStructureController.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-unsaved-changes` | `modules/aculta_lms/src/Form/ActivityAuthoringForm.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-sort-order` | `modules/aculta_lms/src/Form/CourseStructureOrderForm.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-sort-submit` | `modules/aculta_lms/src/Form/CourseStructureOrderForm.php` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-map-popup-template` | `modules/aculta_maps/templates/aculta-maps-popup-template.html.twig` | marcação de comportamento do módulo; sem contrato de tema |
| `data-aculta-map-marker-icon-template` | `modules/aculta_maps/templates/aculta-maps-marker-icon-template.html.twig` | marcação de comportamento do módulo; sem contrato de tema |

| `studio-section-header` | `studio__section-header` | mapeado só no V1 (referência); sem classe equivalente no V2 |
| `map-popup` | (sem classe; estilizado por `[data-portal-ui]`) | sem classe no V2; marcação do popup de mapa (`aculta_maps`) |

## Tokens copiados do V1 para o V2 em 2026-11-05

Mapa `PortalUiHooks::TOKENS` do `aculta420_v2`. Classes copiadas do mapa do V1 (referência). Estado: mapeado; a aparência depende de as classes terem CSS no V2, o que ainda não foi verificado por captura (DEFERRED).

| Token | Classes no V2 | Estado |
|---|---|---|
| `account-avatar` | `portal-account__avatar` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-bio-actions` | `portal-account-bio__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-bio-card` | `aculta-panel`, `aculta-panel__body`, `portal-account-bio` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-bio-edit` | `portal-account-bio__edit` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-bio-text` | `portal-account-bio__text` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-commerce-orders` | `portal-account__commerce`, `portal-account__commerce--orders` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-course` | `portal-account-course` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-course-meta` | `portal-account-course__meta` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-courses` | `portal-account-courses` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-data-content` | `portal-account-data__content` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-data-link` | `portal-account-data__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-data-nav` | `portal-account-data__nav` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-document-input` | `portal-account-document-input` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-entry` | `studio`, `aculta-account-entry` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-entry-links` | `studio__actions`, `studio__actions--grid` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-experiences` | `studio__actions`, `studio__actions--grid` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-home` | `aculta-account-home` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-identity` | `portal-account__identity` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-overview-actions` | `portal-account-overview__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-photo-actions` | `portal-account-photo__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-photo-preview` | `portal-account-photo__preview` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-photo-trigger` | `portal-account-photo__trigger` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-social-card` | `portal-account-social` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-social-edit` | `portal-account-social__edit` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-social-item` | `portal-account-social__item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-social-list` | `portal-account-social__list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-social-name` | `portal-account-social__name` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-social-text` | `portal-account-social__text` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-terms` | `aculta-account-terms` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `account-view-profile` | `portal-account-overview__profile` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `aculta-button-outline` | `aculta-button`, `aculta-button--outline` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `aculta-button-primary` | `aculta-button`, `aculta-button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `aculta-button-secondary` | `aculta-button`, `aculta-button--secondary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `aculta-image-audit` | `aculta-image-audit` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `admin-org-filters` | `aculta-form--inline` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `alerts-filters` | `aculta-alerts-filters` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `alerts-filters-group` | `aculta-alerts-filters__group` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `article-author` | `portal-article-author` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `article-authors` | `portal-article-authors` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `article-authors-list` | `portal-article-authors__list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `article-authors-title` | `portal-article-authors__title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `auth-google` | `aculta-auth-google` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `auth-google-status` | `aculta-auth-google-status` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `auth-provider` | `aculta-auth-provider` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `avatar-crop-dialog` | `aculta-avatar-crop-dialog` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `breadcrumb-item` | `breadcrumb-item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `breadcrumb-item-active` | `breadcrumb-item`, `active` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `breadcrumb-list` | `breadcrumb` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `button` | `button` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `button-ghost` | `button`, `button--ghost` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `button-primary` | `button`, `button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `button-secondary` | `button`, `button--secondary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `calendar-embed-hosts` | `aculta-calendar-embed-hosts` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `calendar-interactive` | `aculta-calendar-interactive` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `calendar-interactive-agenda` | `aculta-calendar-interactive__agenda` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `calendar-interactive-alternative` | `aculta-calendar-interactive__alternativa` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `calendar-interactive-controls` | `aculta-calendar-interactive__controle` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `calendar-interactive-notice` | `aculta-calendar-interactive__aviso` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `cep-sources` | `aculta-cep-sources` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `checkout-google` | `aculta-checkout__google` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `checkout-guest-registration` | `aculta-panel`, `aculta-panel__body`, `aculta-checkout__guest` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `checkout-help` | `checkout-help` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `consent-banner` | `aculta-consent` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `conversation-action` | `aculta-messenger-options__action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `coorganizer-badge` | `badge`, `coorganizer-item__badge` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `coorganizer-card` | `coorganizer-item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `coorganizer-title` | `coorganizer-item__title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `coorganizers-list` | `coorganizers-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `courses-home` | `courses-home` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `courses-home-collaboration` | `courses-home__collaboration` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `courses-home-intro` | `courses-home__intro` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `document-download-format` | `download-badge-format` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `document-download-item` | `aculta-download-meta-item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `document-download-link` | `download-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `document-download-size` | `download-badge-size` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `domain-brand-image` | `aculta-domain-brand-image` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-account-invite` | `messages`, `messages--info`, `aculta-donation__invite` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-account-invite-link` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-amount` | `aculta-donation__amount` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-amount-block` | `aculta-donation__amount-block` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-clause` | `aculta-donation__clause` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-document-link` | `aculta-donation__document-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-donor-name` | `aculta-donation__donor` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-in-honor` | `aculta-donation__honor` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-monthly` | `aculta-donation__monthly` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-summaries` | `aculta-donation__summaries` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-summary` | `aculta-panel`, `aculta-panel__body` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-summary-edit` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `donation-thanks` | `aculta-panel`, `aculta-panel__body`, `aculta-donation__thanks` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-card-cancel-status` | `event-card__aviso` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-card-meta` | `event-card__meta` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-card-subtitle` | `event-card__subtitulo` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-card-title` | `event-card__titulo` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-card-type` | `event-card__tipo` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-catalogue-actions-bar` | `actions-bar`, `aculta-event-catalogue__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-coorg-button-add` | `button`, `button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-date-actions` | `aculta-event-date-actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-date-cancel` | `button` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-date-delete` | `button`, `button--danger` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-date-order` | `aculta-event-date-order` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-date-row` | `aculta-event-date-row` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-actions` | `location-actions`, `aculta-location-actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-actions-bar` | `actions-bar`, `aculta-location-actions-bar` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-address` | `aculta-location-address` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-admin-actions` | `location-admin-actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-agenda` | `location-agenda` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-badge` | `aculta-location-badge`, `badge` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-button-action` | `button`, `button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-button-danger` | `button`, `button--danger` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-button-primary` | `button`, `button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-button-primary-action` | `button`, `button--primary`, `button--action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-button-primary-small` | `button`, `button--small`, `button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-button-secondary` | `button`, `button--secondary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-button-secondary-small` | `button`, `button--secondary`, `button--small` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-empty` | `aculta-location-empty` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-fallback` | `description`, `aculta-location-fallback` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-header` | `aculta-location-header` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-page` | `aculta-location-page` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-search-bar` | `aculta-location-search` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-summary-card` | `aculta-location-summary-card`, `aculta-panel` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-target-id` | `aculta-location-target-id` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-title` | `aculta-location-title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-type-badge` | `badge`, `badge-location-type` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-location-widget` | `aculta-event-location-widget` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-locations-list-page` | `aculta-locations-list-page` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-badge` | `badge`, `event-org-card__badge` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-button-danger` | `button`, `button--danger` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-button-primary` | `button`, `button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-button-secondary` | `button`, `button--secondary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-empty` | `event-org-empty` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-locality` | `event-org-card__locality` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-summary-card` | `event-org-card` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-org-title` | `event-org-card__title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-actions` | `organization-footer-actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-add-action` | `button`, `button--primary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-admin-action` | `button`, `button--secondary`, `button--small` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-admin-filter-fields` | `aculta-event-organization-admin-filter-fields` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-admin-link` | `organization-admin-link`, `aculta-event-organization__admin-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-back-action` | `button`, `button--secondary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-body` | `organization-body` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-description` | `organization-description` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-event-link` | `organization-event-item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-events` | `organization-events` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-fallback` | `aculta-event-organization__fallback` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-header` | `organization-header` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-intro` | `aculta-event-organization__intro` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-links` | `organization-links` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-location` | `organization-location` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-location-fields` | `aculta-event-organization-location-fields` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-profile` | `aculta-organization-profile` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-results-table` | `org-modal-results-table` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-search` | `search-box` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-search-action` | `button`, `button--secondary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-small-button` | `button`, `button--small` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-status-danger` | `badge`, `badge--danger` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-status-info` | `badge`, `badge--info` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-status-success` | `badge`, `badge--success` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-status-warning` | `badge`, `badge--warning` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-target-id` | `aculta-event-organization-target-id` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-title` | `organization-title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-unclaimed` | `organization-badge`, `organization-badge--unclaimed` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organization-widget` | `aculta-event-org-widget` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organizer-dashboard` | `aculta-events-organizer-dashboard` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organizer-filter-form` | `aculta-form--inline` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organizer-filters` | `organizer-dashboard-filters` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-organizer-guide` | `culturas-guide` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-press-quote` | `press-quote` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-press-quotes` | `event-press-quotes`, `media-mentions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-press-quotes-list` | `press-quotes-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-section-title` | `aculta-section-title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-supporter-item` | `sponsor-item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-supporter-link` | `sponsor-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-supporter-name` | `sponsor-name` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-supporter-tier` | `sponsor-cota` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-supporters` | `event-supporters`, `sponsor-logos` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `event-supporters-list` | `sponsor-logos-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `events-admin-overview` | `aculta-events-admin-overview` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `events-organization-admin-overview` | `aculta-events-organization-admin-overview` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `events-upcoming-block` | `aculta-events-upcoming` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `events-upcoming-link` | `aculta-events-upcoming__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `fiscal-identity-other-data` | `aculta-fiscal-identity-other-data` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `forum-category-count` | `aculta-forum-category__count` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `forum-report-pager` | `aculta-pager` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `forum-report-queue` | `aculta-forum-report-queue` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `friendship-action-button` | `portal-account-overview__profile` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `friendship-action-form` | `portal-profile__friendship-form` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `gallery-dialog` | `aculta-gallery-dialog` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `google-account` | `aculta-google-account` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `green-stash-entry` | `studio`, `aculta-green-stash` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `green-stash-navigation` | `aculta-green-stash__navigation` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `green-stash-navigation-link` | `nav-link`, `aculta-green-stash__navigation-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `green-stash-product-create` | `aculta-green-stash__product-create` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `green-stash-product-detail` | `aculta-green-stash__product-detail` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `green-stash-products` | `aculta-green-stash__products` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `green-stash-shipments` | `aculta-green-stash__shipments` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `h5p-admin-header` | `h5p-admin-header` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `h5p-content-unavailable` | `h5p-content-unavailable` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `h5p-dashboard-course` | `aculta-lms-h5p-course` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `hint` | `aculta-hint` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `hint-muted` | `aculta-hint`, `aculta-hint--muted` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `links-page-link` | `aculta-links-page__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `lms-activity-discussions` | `aculta-panel`, `aculta-panel__body`, `aculta-lms-panel` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `lms-class-roster` | `aculta-lms-class-roster` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `lms-forum-moderation-overview` | `aculta-lms-forum-moderation__overview` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `lms-forum-moderation-queue` | `aculta-lms-forum-moderation__queue` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `lms-h5p-activity` | `aculta-lms-h5p__activity` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `lms-h5p-course-documents` | `aculta-lms-h5p__documents` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `lms-h5p-discussions` | `aculta-panel`, `aculta-panel__body`, `aculta-lms-panel` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `login-divider` | `aculta-login__divider` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `login-popup` | `aculta-login-popup` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `login-popup-divider` | `aculta-login-popup__divider` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `login-popup-links` | `aculta-login-popup__links` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `login-registration` | `aculta-login__registration` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `login-reset-link` | `aculta-login__reset-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `map-geocoding-attribution` | `aculta-map__geocoding-attribution` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `map-location-route` | `aculta-map__location-route` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `map-privacy-notice` | `aculta-map__aviso` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `map-widget` | `aculta-map` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `map-wrapper` | `aculta-map-wrapper` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-back-link` | `aculta-messenger-back-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-conversation-actions` | `aculta-messenger-conversation__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-conversation-toolbar` | `aculta-messenger-conversation-toolbar` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-drawer-conversation` | `aculta-messenger-drawer-conversation` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-notice` | `aculta-messenger-notice` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-options` | `aculta-messenger-options` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-options-icon` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-options-item` | `aculta-messenger-options__item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-options-label` | `aculta-messenger-options__label` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-options-panel` | `aculta-messenger-options__panel` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-options-status` | `aculta-messenger-options__status` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-options-summary` | `aculta-messenger-options__summary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-preview` | `aculta-messenger-preview` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-sender` | `aculta-messenger-sender`, `aculta-messenger-conversation` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-sender-avatar` | `aculta-messenger-sender__avatar` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-sender-avatar-image` | `aculta-messenger-sender__avatar-image` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-sender-date` | `aculta-messenger-sender__date` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-sender-link` | `aculta-messenger-sender__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `messenger-sender-name` | `aculta-messenger-sender__name` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-add` | `aculta-new-conversation-add` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-back` | `aculta-new-conversation-back` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-cancel` | `aculta-new-conversation-cancel` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-chip` | `aculta-chip` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-chip-name` | `aculta-chip__name` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-chip-remove` | `aculta-chip__remove` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-chips` | `aculta-chips` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-fallback-search` | `aculta-messenger-fallback-search`, `aculta-messenger-fallback-search--grid` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-link` | `aculta-messenger-new-conversation` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-links` | `aculta-messenger-links` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-message` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `new-conversation-submit` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-admin-intro` | `aculta-notice-admin-intro` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-admin-item` | `admin-item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-admin-list` | `admin-list`, `aculta-notice-admin-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-admin-table` | `aculta-notice-admin-table` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-detail` | `aculta-notice-detail` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-origin-heading` | `aculta-messenger-notice-origin__heading` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-origin-items` | `aculta-messenger-notice-origin__items` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-origin-section` | `aculta-messenger-notice-origin` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-origin-separator` | `aculta-messenger-notice-origin__separator` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-read-state-action` | `aculta-notice-read-state-action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-read-state-form` | `aculta-notice-read-state-form` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-related-group` | `aculta-messenger-notice-related-group` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-related-group-items` | `aculta-messenger-notice-related-group__items` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-status` | `messages`, `messages--status` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-summary-excerpt` | `aculta-notice-summary__excerpt` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-summary-link` | `aculta-messenger-sender__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-summary-state` | `aculta-notice-summary__state` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-summary-title` | `aculta-notice-summary__title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `notice-warning` | `messages`, `messages--warning` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `photo-dialog` | `aculta-photo-dialog` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `post-meta` | `portal-post__meta` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `post-meta-author` | `portal-post__meta-item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `post-share` | `portal-post__share` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `post-share-label` | `portal-post__share-label` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievement` | `portal-profile__achievement` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievement-content` | `portal-profile__achievement-content` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievement-description` | `portal-profile__achievement-description` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievement-highlight` | `portal-profile__achievement-highlight` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievement-image` | `portal-profile__achievement-image` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievement-name` | `portal-profile__achievement-name` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievements` | `portal-profile__achievements` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievements-list` | `portal-profile__achievements-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `profile-achievements-title` | `portal-profile__achievements-title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile` | `portal-profile` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-actions` | `portal-profile__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-avatar` | `portal-profile__avatar` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-avatar-image` | `portal-profile__avatar-image` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-bio` | `portal-profile__bio` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-header` | `portal-profile__header` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-identity` | `portal-profile__identity` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-link-email` | `portal-profile__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-links` | `portal-profile__links` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `public-profile-name` | `portal-profile__name` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `register-popup` | `aculta-register-popup` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `registration-status` | `aculta-registration-status` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `related-content-badge` | `aculta-related-content__badge` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `related-content-block` | `aculta-related-content` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `related-content-item` | `aculta-related-content__item` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `related-content-link` | `aculta-related-content__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `related-content-summary` | `aculta-related-content__summary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `related-content-title` | `aculta-related-content__title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `requirements` | `aculta-portal-requirements` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `requirements-cep` | `aculta-portal-requirements__cep` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `requirements-cep-table` | `aculta-portal-requirements__cep-table` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `requirements-indexing` | `aculta-portal-requirements__indexing` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `requirements-indexing-table` | `aculta-portal-requirements__indexing-table` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `requirements-legend` | `aculta-portal-requirements__legend` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `requirements-php-extensions` | `aculta-portal-requirements__php-extensions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `sales-heading` | `aculta-sales-panel__heading` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `sales-panel` | `aculta-sales-panel` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `sales-section` | `aculta-sales-panel__section` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `sales-table` | `aculta-sales-panel__table` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search` | `aculta-search` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-popup` | `aculta-search-popup` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-preview` | `aculta-search-preview` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-preview-footer` | `aculta-search-preview__footer` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-preview-full` | `aculta-search-preview__full` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-preview-input` | `aculta-search-preview-input` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-preview-list` | `aculta-search-preview__list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-results` | `aculta-search__results` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `search-utility` | `aculta-search-utility`, `aculta-utility-trigger` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-dialog` | `aculta-secret-dialog` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-popup-hint` | `aculta-secret-popup__hint` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-popup-value` | `aculta-secret-popup__value` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-value` | `aculta-secret-value` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-value-clear` | `aculta-secret-value__clear` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-value-edit` | `aculta-secret-value__edit` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-value-eye` | `aculta-secret-value__eye` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secret-value-text` | `aculta-secret-value__text` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secrets-environment` | `aculta-portal-secrets__environment` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secrets-tab` | `secrets-tabs__tab` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secrets-table-scroll` | `secrets-table-scroll` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secrets-tabs` | `secrets-tabs` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `secrets-tabs-list` | `secrets-tabs__list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `security-password-form` | `aculta-security-password-form` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `security-pending` | `aculta-security-pending` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `shop-product-card` | `aculta-shop-card` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `shop-products` | `aculta-shop-grid` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `social-links-button-list` | `aculta-social-links__button-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `social-links-footer-inner` | `aculta-social-links-footer__inner` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `social-links-list` | `aculta-social-links__list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `social-links-settings-button` | `aculta-social-links-settings-button` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-areas` | `aculta-statistics__areas` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-chart` | `aculta-statistics__chart` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-comparison` | `aculta-statistics__comparison` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-controls` | `aculta-statistics__controls` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-form` | `aculta-statistics__form` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-note` | `aculta-statistics__note` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-panel` | `aculta-statistics` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-summary` | `aculta-statistics__summary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-table` | `aculta-statistics__table` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-wiki-ranking` | `aculta-statistics__wiki-ranking` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `statistics-wiki-revisions` | `aculta-statistics__wiki-revisions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `street-number` | `portal-street-number` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `street-number-sn` | `portal-street-number-sn` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-account-avatar` | `studio-account-identity__avatar` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-account-contextual-nav` | `studio-account-nav-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-account-nav-link` | `nav-link`, `studio__link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-account-navigation` | `studio-account-section__navigation` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-account-section` | `studio`, `studio-account-section` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-action` | `studio__action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-actions` | `studio__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-card-compact-list` | `studio-card-compact-list` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-fidelity-scope` | `studio` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-layout` | `studio__layout` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-listing` | `studio__listing` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-listing-wrap` | `table-responsive`, `studio__listing-wrap` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-live` | `studio__live`, `aculta-studio-live` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-main` | `studio__main` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-marley-entry` | `studio__action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-page-title` | `page-title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-panel` | `studio__panel`, `aculta-panel`, `aculta-panel__body` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-profile-actions` | `studio__actions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-profile-editor` | `aculta-profile-editor` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-profile-entry` | `studio__action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-profile-nickname-edit` | `studio__action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-profile-photo-edit` | `studio__action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-profile-public-link` | `studio__action` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-recommendation-badge` | `badge-reason` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-recommendation-reason` | `recommendation-reason` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-card-title` | `compact-card-title` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-content` | `studio`, `studio-saved-page` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-empty` | `studio-saved-empty` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-list-wrapper` | `studio-saved-list-wrapper` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-meta` | `compact-card-meta` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-pager-info` | `pager-info` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-pager-next` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-pager-prev` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-pagination` | `studio-saved-pagination` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-purpose` | `badge`, `badge-purpose` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-summary` | `studio-saved-summary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-saved-tabs` | `studio-saved-tabs` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-section-header` | `studio__section-header` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-shell` | `studio` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `studio-sidebar` | `studio__sidebar` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `support-form` | `aculta-support` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `support-intro` | `aculta-support-intro` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `support-other` | `aculta-support-other` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `terms-modal-documents` | `portal-terms-modal__documents` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `ugc-admin-purpose-filters` | `aculta-admin-purpose-filters`, `action-links` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `ugc-admin-status-filters` | `aculta-admin-status-filters`, `action-links` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `user-admin-filters` | `aculta-admin-filters`, `action-links` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `we-sativa-create` | (sem classe; estilizado por `[data-portal-ui]`) | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `we-sativa-intent` | `aculta-we-sativa__intent` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `we-sativa-start` | `aculta-we-sativa__start` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `we-sativa-workspace` | `aculta-we-sativa__workspace` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-home` | `aculta-wiki-home` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-intro` | `aculta-wiki-intro` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-proposal-contribute` | `button`, `button--secondary` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-proposal-decision` | `aculta-wiki-proposal-decision` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-proposal-link` | `aculta-wiki-proposal-link` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-proposal-status` | `aculta-wiki-proposal-status` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-recent-change` | `aculta-wiki-recent-change` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-search-results` | `aculta-wiki-search-results` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-title-suggestions` | `aculta-wiki-title-suggestions` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |
| `wiki-workspace-return` | `aculta-wiki-workspace__return` | copiado do V1; estilo no V2 pendente quando a classe não tem CSS (DEFERRED) |

## Prefixos e tokens concatenados (2026-11-05)

| Token | Classes no V2 | Estado |
|---|---|---|
| `email-notice` | `aculta-email-notice` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `messenger-state` | `aculta-messenger-state` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `messenger-state-empty` | `aculta-messenger-state--empty` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `notice-error` | `messages`, `messages--error` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `notice-summary` | `aculta-messenger-sender`, `aculta-notice-summary` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `notice-summary-persistent` | `aculta-notice-summary--persistent` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `recommendation-item` | `recommendation-item` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `saved-item` | `saved-item` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `secrets-section` | `aculta-portal-secrets-section` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `secrets-tabs-panel` | `secrets-tabs__panel` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `social-links-channel` | `aculta-social-links__channel` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `social-links-channel-custom` | `aculta-social-links__channel--custom` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `social-links-channel-website` | `aculta-social-links__channel--website` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `studio-account-home` | `studio__panel`, `aculta-panel`, `aculta-panel__body` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `studio-account-identity` | `studio-account-identity` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `studio-card-compact` | `studio-card-compact` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `studio-course-authoring` | `studio-course-authoring` | copiado do V1; estilo no V2 pendente (DEFERRED) |
| `studio-course-authoring-back` | `studio-course-authoring__back` | copiado do V1; estilo no V2 pendente (DEFERRED) |

## Tokens conferidos em 2026-11-12

| Token | Classes no V2 | Estado |
|---|---|---|
| `button-danger` | `button`, `button--danger` | conferido no mapa do V2 em 2026-11-12 |
| `button-outline` | `button`, `button--outline` | conferido no mapa do V2 em 2026-11-12 |
| `card-cultural-accent-green` | `card-cultural--accent-green` | conferido no mapa do V2 em 2026-11-12 |
| `card-cultural-accent-yellow` | `card-cultural--accent-yellow` | conferido no mapa do V2 em 2026-11-12 |
| `card-cultural-accent-red` | `card-cultural--accent-red` | conferido no mapa do V2 em 2026-11-12 |
| `revista-teaser-accent-green` | `revista-teaser--accent-green` | conferido no mapa do V2 em 2026-11-12 |
| `revista-teaser-accent-yellow` | `revista-teaser--accent-yellow` | conferido no mapa do V2 em 2026-11-12 |
| `revista-teaser-accent-red` | `revista-teaser--accent-red` | conferido no mapa do V2 em 2026-11-12 |
| `ui-library-nav` | `aculta-ui-library-nav` | conferido no mapa do V2 em 2026-11-12 |
| `events-organization-admin-table` | `aculta-events-organization-admin` | conferido no mapa do V2 em 2026-11-12 |
| `friendship-cancel-action` | `portal-account-overview__profile`, `button`, `button--secondary` | conferido no mapa do V2 em 2026-11-12 |
| `friendship-remove-action` | `portal-account-overview__profile`, `button`, `button--danger` | conferido no mapa do V2 em 2026-11-12 |
| `public-profile-reactions` | `portal-profile__reactions` | conferido no mapa do V2 em 2026-11-12 |
| `public-profile-reactions-title` | `portal-profile__reactions-title` | conferido no mapa do V2 em 2026-11-12 |
| `public-profile-reactions-stats` | `portal-profile__reactions-stats` | conferido no mapa do V2 em 2026-11-12 |
| `public-profile-reactions-received` | `portal-profile__reactions-received` | conferido no mapa do V2 em 2026-11-12 |
| `public-profile-reactions-given` | `portal-profile__reactions-given` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-forum` | `aculta-statistics__forum` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-forum-kpis` | `aculta-statistics__forum-kpis` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-metric-card` | `statistics-metric-card` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-metric-card__label` | `statistics-metric-card__label` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-metric-card__value` | `statistics-metric-card__value` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-metric-card__number` | `statistics-metric-card__number` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-metric-card__unit` | `statistics-metric-card__unit` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-metric-card__hint` | `statistics-metric-card__hint` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-trend-chart__title` | `statistics-trend-chart__title` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-trend-chart__summary` | `statistics-trend-chart__summary` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-trend-chart__caption` | `statistics-trend-chart__caption` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-summary-card__title` | `statistics-summary-card__title` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-summary-card__note` | `statistics-summary-card__note` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-category-ranking__empty` | `statistics-category-ranking__empty` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-empty-state__message` | `statistics-empty-state__message` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-empty-state__hint` | `statistics-empty-state__hint` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-summary-card` | `statistics-summary-card` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-trend-chart` | `statistics-trend-chart` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-category-ranking` | `statistics-category-ranking` | conferido no mapa do V2 em 2026-11-12 |
| `statistics-empty-state` | `statistics-empty-state` | conferido no mapa do V2 em 2026-11-12 |

| `studio-calendar` | (sem classe; estilizado por `[data-portal-ui]`) | componente do Studio em `aculta-studio-calendar.html.twig` |
| `studio-connections` | (sem classe; estilizado por `[data-portal-ui]`) | componente do Studio em `aculta-studio-connections.html.twig` |

## Atributos de comportamento conferidos em 2026-11-12

| Atributo | Uso |
|---|---|
| `data-aculta-reply-clear` | limpa o formulário de resposta quando o popup fecha |
| `data-aculta-topic-composer` | identifica o compositor de novo tópico público do Fórum |
| `data-aculta-comment-new` | marca o comentário recém-publicado para foco e destaque |
| `data-aculta-content-composer` | identifica o compositor de conteúdo do Studio |
