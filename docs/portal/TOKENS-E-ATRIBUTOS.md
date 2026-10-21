# Tokens e atributos do Portal para o tema

> Gerado a partir do código em 2026-10-14 e validado pelo gate `scripts/validate-portal-ui-docs.php`. Quando um token ou atributo novo for criado, ele precisa entrar aqui (o gate reprova o contrário).

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
| `data-aculta-cep-status` | exato | sim | 1 |
| `data-aculta-chip` | exato | sim | 2 |
| `data-aculta-chip-name` | exato | sim | 1 |
| `data-aculta-chip-remove` | exato | sim | 2 |
| `data-aculta-chips` | exato | sim | 2 |
| `data-aculta-clear` | exato | sim | 2 |
| `data-aculta-conversation-url` | exato | não | 1 |
| `data-aculta-conversation-view` | exato | sim | 2 |
| `data-aculta-dialog` | exato | sim | 3 |
| `data-aculta-dialog-close` | exato | sim | 2 |
| `data-aculta-dialog-open` | exato | sim | 2 |
| `data-aculta-drawer-action` | exato | sim | 3 |
| `data-aculta-drawer-back` | exato | sim | 4 |
| `data-aculta-drawer-content` | exato | sim | 2 |
| `data-aculta-drawer-count` | exato | sim | 2 |
| `data-aculta-drawer-label` | exato | não | 2 |
| `data-aculta-drawer-url` | exato | sim | 2 |
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
| `data-aculta-group-max` | exato | sim | 1 |
| `data-aculta-json-form` | exato | sim | 2 |
| `data-aculta-legenda` | exato | sim | 2 |
| `data-aculta-member-name` | exato | sim | 1 |
| `data-aculta-messenger-count` | exato | sim | 2 |
| `data-aculta-mailer-table` | exato | não | 2 |
| `data-aculta-imagem` | exato | não | 1 |
| `data-aculta-imagem-alinhamento` | exato | não | 1 |
| `data-aculta-imagem-legenda` | exato | não | 1 |
| `data-aculta-imagem-modelo` | exato | não | 1 |
| `data-aculta-confirming` | exato | sim | 1 |
| `data-aculta-lazy-src` | exato | sim | 2 |
| `data-aculta-lock-mirror` | exato | sim | 1 |
| `data-aculta-modelo` | exato | sim | 3 |
| `data-aculta-new-conversation` | exato | sim | 2 |
| `data-aculta-new-conversation-form` | exato | sim | 2 |
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
| `data-aculta-portal-link` | exato | não | 2 |
| `data-aculta-reply-form` | exato | sim | 3 |
| `data-aculta-sent-date` | exato | sim | 1 |
| `data-aculta-thread-id` | exato | não | 1 |
| `data-aculta-thread-unread` | exato | não | 1 |
| `data-aculta-user` | exato | não | 1 |
| `data-aculta-user-avatar` | exato | não | 3 |
| `data-aculta-user-picture` | exato | não | 1 |
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
| `data-portal-ui` | exato | sim | 50 |

Atributos: não há wildcard. Todo nome usado no código aparece na tabela acima (o gate reprova o que não estiver).

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
| `account-home` | `aculta-account-home` | `web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` (link Minha conta do menu da conta; o tema aplica a classe ao link) |
| `messenger-read-status` | `aculta-messenger-read-status` | `web/themes/custom/aculta420/src/Hook/ThemeHooks.php` |
| `notice-read-status` | `aculta-messenger-read-status` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-unread-status` | `aculta-messenger-read-status`, `aculta-messenger-read-status--unread` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
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
| `courses-home` | `courses-home` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `courses-home-intro` | `courses-home__intro` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `courses-home-collaboration` | `courses-home__collaboration` | `web/modules/custom/aculta_portal/src/Controller/CoursesController.php` |
| `account-courses` | `portal-account-courses` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-courses-summary` | `portal-account-courses-summary` | (montado dinamicamente ou só no tema) |
| `account-course` | `portal-account-course` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-course-meta` | `portal-account-course__meta` | `web/modules/custom/aculta_portal/src/Controller/AccountCoursesController.php` |
| `account-avatar` | `portal-account__avatar` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/AvatarRerollController.php`<br>`web/modules/custom/aculta_portal/modules/aculta_user/src/Form/UserPictureForm.php` |
| `account-avatar-empty` | `portal-account__avatar`, `portal-account__avatar--empty` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
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
| `public-profile-initial` | `portal-profile__initial` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Controller/PublicProfileController.php` |
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
| `messenger-preview-inbox-link` | `btn`, `btn-primary`, `aculta-messenger-preview__inbox-link` | (montado pelo tema para link da inbox) |
| `account-home` | `aculta-account-home` | (montado pelo tema no link Minha conta, a partir do token do módulo) |
| `messenger-read-status` | `aculta-messenger-read-status` | `web/themes/custom/aculta420/src/Hook/ThemeHooks.php` |
| `notice-read-status` | `aculta-messenger-read-status` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
| `notice-unread-status` | `aculta-messenger-read-status`, `aculta-messenger-read-status--unread` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Controller/MessagesController.php` |
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
| `status-attention` | `aculta-portal-requirements__status--attention` | `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php` (`status-` + `ok`, `attention` ou `error`) |
| `cep-sources` | `aculta-cep-sources` | `web/modules/custom/aculta_portal/modules/aculta_portal_cep/src/Form/CepSettingsForm.php` |
| `feedback-matched` | `feedback-section`, `matched-phrases` | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `feedback-unmatched` | `feedback-section`, `unmatched-phrases` | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `gallery-dialog` | `aculta-gallery-dialog` | `web/modules/custom/aculta_portal/modules/aculta_gallery/src/Form/GalleryEmbedDialogForm.php` |
| `phrase-match` | `phrase-match` (com `correct-answer`, classe de estado do LMS) | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `phrase-mismatch` | `phrase-mismatch` (com `wrong-answer`, classe de estado do LMS) | `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` |
| `social-links-icon-instagram` | `bi`, `bi-instagram` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-icon-youtube` | `bi`, `bi-youtube` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `social-links-icon-facebook` | `bi`, `bi-facebook` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksPagePresenter.php` |
| `h5p-admin-header` | `h5p-admin-header` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Controller/H5PLibraryAdmin.php` |
| `h5p-content-unavailable` | `h5p-content-unavailable` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldFormatter/H5PDefaultFormatter.php` |
| `h5p-dashboard-course` | `aculta-lms-h5p-course` | `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Controller/H5PDashboardController.php` |
| `checkout-help` | `checkout-help` | `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/PluginForm/Checkout/RedirectCheckoutForm.php` |
| `mercado-pago-credentials-test` | `mercado-pago-credentials`, `mercado-pago-credentials-test` | `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` |
| `mercado-pago-credentials-stage` | `mercado-pago-credentials`, `mercado-pago-credentials-stage` | `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` |
| `mercado-pago-credentials-prod` | `mercado-pago-credentials`, `mercado-pago-credentials-prod` | `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/src/Plugin/Commerce/PaymentGateway/CheckoutPro.php` |

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
