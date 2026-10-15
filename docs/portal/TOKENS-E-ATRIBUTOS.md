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
| `data-aculta-bio-clear` | exato | sim | 2 |
| `data-aculta-bio-form` | exato | sim | 2 |
| `data-aculta-cart-count` | exato | não | 2 |
| `data-aculta-cart-trigger` | exato | não | 2 |
| `data-aculta-alert-dismiss` | exato | sim | 1 |
| `data-aculta-alerts-count` | exato | sim | 2 |
| `data-aculta-cep-status` | exato | sim | 1 |
| `data-aculta-clear` | exato | sim | 2 |
| `data-aculta-conversation-url` | exato | não | 1 |
| `data-aculta-conversation-view` | exato | sim | 2 |
| `data-aculta-drawer-content` | exato | sim | 2 |
| `data-aculta-drawer-count` | exato | sim | 2 |
| `data-aculta-drawer-label` | exato | não | 2 |
| `data-aculta-drawer-url` | exato | sim | 2 |
| `data-aculta-json-form` | exato | sim | 2 |
| `data-aculta-messenger-count` | exato | sim | 2 |
| `data-aculta-number` | exato | sim | 2 |
| `data-aculta-number-sn` | exato | não | 1 |
| `data-aculta-photo-editor` | exato | não | 2 |
| `data-aculta-photo-open` | exato | não | 1 |
| `data-aculta-portal-link` | exato | não | 2 |
| `data-aculta-user` | exato | não | 1 |
| `data-aculta-user-avatar` | exato | não | 3 |
| `data-aculta-user-picture` | exato | não | 1 |
| `data-aculta-thread-id` | exato | não | 1 |
| `data-aculta-thread-unread` | exato | não | 1 |
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

- `article-author-social-link-*`: Links sociais do autor de artigo (uma família por rede).
- `post-share-*`: Botões de compartilhamento da publicação (uma família por canal).
- `public-profile-link-*`: Links sociais do perfil público (uma família por rede).
- `social-links-channel-*`: Canais da página de redes sociais (uma família por canal).

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
| `conversation-action` | `aculta-messenger-options__action` | `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Form/ConversationRelationshipForm.php` |
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
| `article-author-photo` | `portal-article-author__photo` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-author-initial` | `portal-article-author__initial` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-author-text` | `portal-article-author__text` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-author-name` | `portal-article-author__name` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `discussion` | `portal-discussion` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/DiscussionHooks.php` |
| `discussion-title` | `portal-discussion__title` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/DiscussionHooks.php` |
| `discussion-list` | `portal-discussion__list` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/DiscussionHooks.php` |
| `discussion-item` | `portal-discussion__item` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/DiscussionHooks.php` |
| `discussion-link` | `portal-discussion__link` | `web/modules/custom/aculta_portal/modules/aculta_user/src/Hook/DiscussionHooks.php` |
| `forum-opening-post` | `portal-forum-opening` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-title` | `portal-forum-opening__title` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-image` | `portal-forum-opening__image` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-body` | `portal-forum-opening__body` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `forum-opening-post-link` | `portal-forum-opening__link` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Presentation/ForumOpeningPost.php` |
| `article-author-role` | `portal-article-author__role` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-author-bio` | `portal-article-author__bio` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-author-social` | `portal-article-author__social` | `web/modules/custom/aculta_portal/modules/aculta_magazine/src/Hook/ArticleAuthorsHooks.php` |
| `article-author-social-link-instagram` | `portal-article-author__social-link`, `bi`, `bi-instagram` | (montado dinamicamente ou só no tema) |
| `article-author-social-link-facebook` | `portal-article-author__social-link`, `bi`, `bi-facebook` | (montado dinamicamente ou só no tema) |
| `article-author-social-link-reddit` | `portal-article-author__social-link`, `bi`, `bi-reddit` | (montado dinamicamente ou só no tema) |
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
| `social-links-settings-button` | `aculta-social-links-settings-button` | `web/modules/custom/aculta_portal/src/Presentation/SocialLinksSettingsForm.php` |
| `security-card` | `aculta-security-card` | (montado dinamicamente ou só no tema) |
| `security-pending` | `aculta-security-pending` | `web/modules/custom/aculta_portal/src/Controller/PortalController.php` |
| `auth-provider` | `aculta-auth-provider` | `web/modules/custom/aculta_portal/src/Form/LoginPopupForm.php`<br>`web/modules/custom/aculta_portal/src/Hook/PortalHooks.php` |
| `terms-modal-documents` | `portal-terms-modal__documents` | `web/modules/custom/aculta_portal/src/Form/AccountTermsAcceptForm.php` |
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
