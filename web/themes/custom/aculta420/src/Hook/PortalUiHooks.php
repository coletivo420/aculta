<?php

declare(strict_types=1);

namespace Drupal\aculta420\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;

/**
 * Design do Aculta Portal: traduz tokens semânticos em classes do ACULTA420.
 *
 * "Código no Aculta Portal, Design no ACULTA420." O módulo aculta_portal não escreve classes de apresentação: marca o
 * elemento com data-portal-ui="token token2" (nome semântico, sem classe de design). Este hook troca cada token pelas
 * classes correspondentes aqui e remove o atributo. Token desconhecido não gera classe. Ver docs/portal/FRONTEIRA-MODULO-TEMA.md.
 */
final class PortalUiHooks {

  public const ATTRIBUTE = 'data-portal-ui';

  /** Mapa token → classes de apresentação. A ordem dos tokens define a ordem das classes. */
  public const TOKENS = [
    // Avisos e mensagens (classes de mensagens do Drupal, estilizadas aqui).
    'notice-warning' => ['messages', 'messages--warning'],
    'notice-error' => ['messages', 'messages--error'],
    'email-notice' => ['aculta-email-notice'],
    // Doação (aculta_donation): resumo da etapa de revisão e links de edição.
    'donation-summaries' => ['d-grid', 'gap-3'],
    'donation-summary' => ['card', 'card-body'],
    'donation-summary-edit' => ['btn', 'btn-sm', 'btn-outline-primary'],
    'donation-document-link' => ['link-primary'],
    'donation-thanks' => ['card', 'card-body', 'text-center'],
    'donation-donor-name' => ['fw-semibold'],
    'donation-clause' => ['text-muted'],
    'donation-amount-block' => ['my-3'],
    'donation-amount' => ['display-6', 'fw-bold'],
    'donation-monthly' => ['badge', 'text-bg-secondary'],
    'donation-in-honor' => ['fst-italic'],
    'donation-account-invite' => ['alert', 'alert-info', 'mt-3'],
    'donation-account-invite-link' => ['btn', 'btn-outline-primary'],
    'register-popup' => ['d-grid', 'gap-3'],
    // Loja: vitrine da página inicial (produtos publicados).
    'shop-products' => ['d-grid', 'gap-3', 'mt-4'],
    // Cartão do produto: caixa com borda neutra (sem cor de botão), texto alinhado à esquerda.
    'shop-product-card' => ['d-block', 'border', 'border-2', 'rounded-3', 'text-start', 'p-3', 'text-decoration-none'],
    // Relatórios de requisitos e segredos (administração do Portal).
    // Estatísticas (aculta_statistics): painel central e por área. O módulo envia só tokens; o tema define o visual.
    'statistics-panel' => ['aculta-statistics'],
    'statistics-areas' => ['aculta-statistics__areas'],
    'statistics-controls' => ['aculta-statistics__controls'],
    'statistics-form' => ['aculta-statistics__form'],
    'statistics-summary' => ['aculta-statistics__summary'],
    'statistics-comparison' => ['aculta-statistics__comparison'],
    'statistics-chart' => ['aculta-statistics__chart'],
    'statistics-table' => ['aculta-statistics__table'],
    'statistics-note' => ['aculta-statistics__note'],
    'requirements' => ['aculta-portal-requirements'],
    'requirements-legend' => ['aculta-portal-requirements__legend'],
    'requirements-status' => ['aculta-portal-requirements__status'],
    'status-ok' => ['aculta-portal-requirements__status--ok'],
    'status-warning' => ['aculta-portal-requirements__status--warning'],
    'status-attention' => ['aculta-portal-requirements__status--attention'],
    'status-error' => ['aculta-portal-requirements__status--error'],
    'status-pending' => ['aculta-portal-requirements__status--pending'],
    'requirements-indexing' => ['aculta-portal-requirements__indexing'],
    'requirements-indexing-table' => ['aculta-portal-requirements__indexing-table'],
    'requirements-cep' => ['aculta-portal-requirements__cep'],
    'requirements-cep-table' => ['aculta-portal-requirements__cep-table'],
    'requirements-php-extensions' => ['aculta-portal-requirements__php-extensions'],
    // Painel de moderação do LMS no Fórum (aculta_forum_lms): estrutura por grupo, sem CSS próprio.
    'lms-forum-moderation-overview' => ['aculta-lms-forum-moderation__overview'],
    'lms-forum-moderation-queue' => ['aculta-lms-forum-moderation__queue'],
    // Discussões LMS no player H5P e sob atividades gerais usam primitives Bootstrap.
    'lms-activity-discussions' => ['card', 'card-body', 'mt-3'],
    'lms-h5p-activity' => ['d-grid', 'gap-3'],
    'lms-h5p-discussions' => ['card', 'card-body', 'mt-3'],
    'lms-h5p-course-documents' => ['mt-3'],
    'cep-sources' => ['aculta-cep-sources'],
    'secrets-section' => ['aculta-portal-secrets-section'],
    'secret-value' => ['aculta-secret-value'],
    'secret-value-text' => ['aculta-secret-value__text'],
    'secret-value-eye' => ['aculta-secret-value__eye'],
    'secret-value-edit' => ['aculta-secret-value__edit'],
    'secret-value-clear' => ['aculta-secret-value__clear'],
    'secret-popup-value' => ['aculta-secret-popup__value'],
    'secret-popup-hint' => ['aculta-secret-popup__hint'],
    // Busca.
    'search' => ['aculta-search'],
    'search-results' => ['aculta-search__results'],
    'search-utility' => ['aculta-search-utility', 'btn', 'btn-link', 'p-0'],
    'search-popup' => ['aculta-search-popup'],
    'search-preview' => ['aculta-search-preview'],
    'search-preview-input' => ['aculta-search-preview-input'],
    'new-conversation-link' => ['btn', 'btn-primary', 'aculta-messenger-new-conversation'],
    'new-conversation-submit' => ['btn', 'btn-primary'],
    'new-conversation-back' => ['btn', 'aculta-new-conversation-back'],
    'new-conversation-cancel' => ['btn', 'aculta-new-conversation-cancel'],
    'conversation-action' => ['aculta-messenger-options__action'],
    'conversation-notice-action' => ['aculta-messenger-notice__action'],
    'new-conversation-links' => ['d-flex', 'flex-wrap', 'gap-2', 'mt-2'],
    'new-conversation-message' => ['form-control'],
    'new-conversation-results' => ['list-group'],
    'new-conversation-result' => ['list-group-item', 'list-group-item-action'],
    'search-preview-list' => ['aculta-search-preview__list', 'list-unstyled'],
    'search-preview-footer' => ['aculta-search-preview__footer', 'd-flex', 'justify-content-center', 'mt-2'],
    'search-preview-full' => ['aculta-search-preview__full', 'btn', 'btn-outline-primary', 'btn-sm'],
    'hint' => ['small', 'text-body-secondary'],
    'hint-muted' => ['text-body-secondary'],
    // Modal compartilhado de upload do Portal e tokens de contexto do Fórum.
    'user-upload-dialog' => ['aculta-user-upload-dialog'],
    'forum-attachment-dialog' => ['aculta-forum-attachment-dialog'],
    'forum-gallery-dialog' => ['aculta-forum-gallery-dialog'],
    // Review de texto livre (aculta_lms): seções de retorno e cada frase verificada.
    'feedback-matched' => ['feedback-section', 'matched-phrases'],
    'feedback-unmatched' => ['feedback-section', 'unmatched-phrases'],
    'phrase-match' => ['phrase-match'],
    'phrase-mismatch' => ['phrase-mismatch'],
    // Administração do H5P (aculta_lms_h5p) e aviso do checkout Mercado Pago.
    'h5p-admin-header' => ['h5p-admin-header'],
    'h5p-content-unavailable' => ['h5p-content-unavailable'],
    'h5p-dashboard-course' => ['aculta-lms-h5p-course'],
    'checkout-help' => ['checkout-help'],
    'gallery-dialog' => ['aculta-gallery-dialog'],
    'mercado-pago-credentials-test' =>['mercado-pago-credentials', 'mercado-pago-credentials-test'],
    'mercado-pago-credentials-stage' => ['mercado-pago-credentials', 'mercado-pago-credentials-stage'],
    'mercado-pago-credentials-prod' => ['mercado-pago-credentials', 'mercado-pago-credentials-prod'],
    'icon-search' => ['bi', 'bi-search'],
    'visually-hidden' => ['visually-hidden'],
    // Wiki e cursos.
    'wiki-home' => ['aculta-wiki-home'],
    'wiki-intro' => ['aculta-wiki-intro'],
    'wiki-search' => ['aculta-wiki-search'],
    'wiki-search-results' => ['aculta-wiki-search-results'],
    'wiki-recent-change' => ['aculta-wiki-recent-change'],
    'courses-home' => ['courses-home'],
    'courses-home-intro' => ['courses-home__intro'],
    'courses-home-collaboration' => ['courses-home__collaboration'],
    'account-courses' => ['portal-account-courses'],
    'account-courses-summary' => ['portal-account-courses-summary'],
    'account-course' => ['portal-account-course'],
    'account-course-meta' => ['portal-account-course__meta'],
    // Minha conta.
    'account-avatar' => ['portal-account__avatar'],
    'account-avatar-empty' => ['portal-account__avatar', 'portal-account__avatar--empty'],
    'account-photo-preview' => ['portal-account-photo__preview'],
    'account-photo-actions' => ['portal-account-photo__actions'],
    'account-bio-card' => ['card', 'card-body', 'portal-account-bio'],
    'account-bio-text' => ['portal-account-bio__text'],
    'account-bio-edit' => ['btn', 'btn-link', 'portal-account-bio__edit', 'bi', 'bi-pencil'],
    'account-bio-actions' => ['portal-account-bio__actions'],
    // Visão geral da Minha conta: ação "Ver Perfil" no topo (botão secundário, borda visível, foco por teclado).
    'account-overview-actions' => ['portal-account-overview__actions'],
    'account-view-profile' => ['btn', 'btn-outline-secondary', 'portal-account-overview__profile'],
    // Cabeçalho da postagem da Revista (metainformação e compartilhamento).
    'post-meta' => ['portal-post__meta'],
    'post-meta-author' => ['portal-post__meta-item', 'bi', 'bi-person-circle'],
    'post-meta-date' => ['portal-post__meta-item', 'bi', 'bi-calendar3'],
    'post-meta-time' => ['portal-post__meta-item', 'bi', 'bi-clock'],
    'post-share' => ['portal-post__share'],
    'post-share-label' => ['portal-post__share-label'],
    'post-share-whatsapp' => ['portal-post__share-link', 'portal-post__share-link--whatsapp', 'bi', 'bi-whatsapp'],
    'post-share-telegram' => ['portal-post__share-link', 'portal-post__share-link--telegram', 'bi', 'bi-telegram'],
    'post-share-facebook' => ['portal-post__share-link', 'portal-post__share-link--facebook', 'bi', 'bi-facebook'],
    'post-share-reddit' => ['portal-post__share-link', 'portal-post__share-link--reddit', 'bi', 'bi-reddit'],
    'post-share-email' => ['portal-post__share-link', 'portal-post__share-link--email', 'bi', 'bi-envelope'],
    // Caixa de autores da publicação da Revista.
    'article-authors' => ['portal-article-authors'],
    'article-authors-title' => ['portal-article-authors__title'],
    'article-authors-list' => ['portal-article-authors__list'],
    'article-author' => ['portal-article-author'],
    // Abertura do tópico do Fórum (resumo, imagem e link para a publicação da Revista).
    'forum-opening-post' => ['portal-forum-opening'],
    'forum-opening-post-title' => ['portal-forum-opening__title'],
    'forum-opening-post-image' => ['portal-forum-opening__image'],
    'forum-opening-post-body' => ['portal-forum-opening__body'],
    'forum-opening-post-link' => ['portal-forum-opening__link'],
    // Perfil público (/u/<apelido>).
    'public-profile' => ['portal-profile'],
    'public-profile-header' => ['portal-profile__header'],
    'public-profile-actions' => ['portal-profile__actions'],
    'friendship-action-form' => ['portal-profile__friendship-form'],
    'friendship-action-button' => ['btn', 'btn-outline-secondary', 'portal-account-overview__profile'],
    'forum-topic-unread' => ['aculta-unread-count'],
    'public-profile-avatar' => ['portal-profile__avatar'],
    'public-profile-avatar-image' => ['portal-profile__avatar-image'],
    'public-profile-initial' => ['portal-profile__initial'],
    'public-profile-identity' => ['portal-profile__identity'],
    'public-profile-name' => ['portal-profile__name'],
    'public-profile-bio' => ['portal-profile__bio'],
    'public-profile-links' => ['portal-profile__links'],
    'public-profile-link-instagram' => ['portal-profile__link', 'bi', 'bi-instagram'],
    'public-profile-link-facebook' => ['portal-profile__link', 'bi', 'bi-facebook'],
    'public-profile-link-reddit' => ['portal-profile__link', 'bi', 'bi-reddit'],
    'public-profile-link-email' => ['portal-profile__link', 'bi', 'bi-envelope'],
    'profile-achievements' => ['portal-profile__achievements'],
    'profile-achievements-title' => ['portal-profile__achievements-title'],
    'profile-achievements-list' => ['portal-profile__achievements-list'],
    'profile-achievement' => ['portal-profile__achievement'],
    'profile-achievement-image' => ['portal-profile__achievement-image'],
    'profile-achievement-content' => ['portal-profile__achievement-content'],
    'profile-achievement-name' => ['portal-profile__achievement-name'],
    'profile-achievement-description' => ['portal-profile__achievement-description'],
    'profile-achievement-highlight' => ['portal-profile__achievement-highlight'],
    // Redes sociais (caixa embaixo da bio, Minha conta).
    'account-social-card' => ['portal-account-social'],
    'account-social-list' => ['portal-account-social__list'],
    'account-social-item' => ['portal-account-social__item'],
    'account-social-icon-instagram' => ['portal-account-social__icon', 'bi', 'bi-instagram'],
    'account-social-icon-facebook' => ['portal-account-social__icon', 'bi', 'bi-facebook'],
    'account-social-icon-reddit' => ['portal-account-social__icon', 'bi', 'bi-reddit'],
    'account-social-text' => ['portal-account-social__text'],
    'account-social-name' => ['portal-account-social__name'],
    'account-social-status' => ['portal-account-social__status'],
    'account-social-value' => ['portal-account-social__value'],
    'account-social-edit' => ['btn', 'btn-link', 'btn-sm', 'portal-account-social__edit', 'bi', 'bi-pencil'],
    'account-identity' => ['portal-account__identity'],
    'account-commerce-orders' => ['portal-account__commerce', 'portal-account__commerce--orders'],
    'account-commerce-payments' => ['portal-account__commerce', 'portal-account__commerce--payments'],
    'account-data-link' => ['portal-account-data__link'],
    'account-data-nav' => ['portal-account-data__nav'],
    'account-data-content' => ['portal-account-data__content'],
    'account-document-input' => ['portal-account-document-input'],
    // Redes sociais e botões (módulo emite só o token; as classes ficam no tema).
    'social-links-button-list' => ['aculta-social-links__button-list'],
    'social-links-channel' => ['aculta-social-links__channel'],
    'social-links-channel-website' => ['aculta-social-links__channel--website'],
    'social-links-channel-custom' => ['aculta-social-links__channel--custom'],
    'social-links-channel-instagram' => ['aculta-social-links__channel--instagram'],
    'social-links-channel-youtube' => ['aculta-social-links__channel--youtube'],
    'social-links-channel-facebook' => ['aculta-social-links__channel--facebook'],
    // Ícone do canal na lista de redes sociais (Bootstrap Icons).
    'social-links-icon-instagram' => ['bi', 'bi-instagram'],
    'social-links-icon-youtube' => ['bi', 'bi-youtube'],
    'social-links-icon-facebook' => ['bi', 'bi-facebook'],
    'social-links-list' => ['aculta-social-links__list', 'list-unstyled'],
    'social-links-footer-inner' => ['aculta-social-links-footer__inner'],
    'social-links-settings-button' => ['aculta-social-links-settings-button'],
    'security-card' => ['aculta-security-card'],
    'security-pending' => ['aculta-security-pending'],
    'auth-provider' => ['aculta-auth-provider'],
    'terms-modal-documents' => ['portal-terms-modal__documents'],
    'street-number' => ['portal-street-number'],
    'street-number-sn' => ['portal-street-number-sn'],
    'breadcrumb-item' => ['breadcrumb-item'],
    'breadcrumb-item-active' => ['breadcrumb-item', 'active'],
    // Entrada, cadastro, apoio e marca.
    'login-reset-link' => ['aculta-login__reset-link'],
    'login-registration' => ['aculta-login__registration'],
    'login-divider' => ['aculta-login__divider'],
    // Popup "Entrar" da barra (aculta_portal, LoginPopupForm).
    'login-popup' => ['aculta-login-popup'],
    'login-popup-links' => ['aculta-login-popup__links'],
    'login-popup-divider' => ['aculta-login-popup__divider'],
    'auth-google' => ['aculta-auth-google'],
    'auth-google-status' => ['aculta-auth-google-status'],
    'google-account' => ['aculta-google-account'],
    // Popup de aceite dos termos (diálogo modal do Drupal).
    'account-terms' => ['aculta-account-terms'],
    // Diálogos abertos pelo módulo: o tema define tamanho e posição (dialog.css).
    'photo-dialog' => ['aculta-photo-dialog'],
    'avatar-crop-dialog' => ['aculta-avatar-crop-dialog'],
    'account-bio-dialog' => ['aculta-account-bio-dialog'],
    'social-link-dialog' => ['aculta-social-link-dialog'],
    'secret-dialog' => ['aculta-secret-dialog'],
    'messenger-preview' => ['aculta-messenger-preview'],
    'account-home' => ['aculta-account-home'],
    'notice-read-status' => ['aculta-messenger-read-status'],
    'notice-unread-status' => ['aculta-messenger-read-status', 'aculta-messenger-read-status--unread'],
    // Gaveta e lista do Messenger: o módulo marca elementos com estes tokens; as classes são as do CSS do tema
    // (messenger-drawer.css e messenger-item.css). Ver docs/portal/MESSENGER.md.
    'messenger-back-link' => ['aculta-messenger-back-link'],
    'messenger-drawer-conversation' => ['aculta-messenger-drawer-conversation'],
    'messenger-conversation-toolbar' => ['aculta-messenger-conversation-toolbar'],
    'messenger-conversation-actions' => ['aculta-messenger-conversation__actions'],
    'messenger-options' => ['aculta-messenger-options'],
    'messenger-options-summary' => ['aculta-messenger-options__summary'],
    'messenger-options-icon' => ['bi', 'bi-list'],
    'messenger-options-label' => ['aculta-messenger-options__label'],
    'messenger-options-panel' => ['aculta-messenger-options__panel'],
    'messenger-options-item' => ['aculta-messenger-options__item'],
    'messenger-options-status' => ['aculta-messenger-options__status'],
    'messenger-notice' => ['aculta-messenger-notice'],
    'messenger-sender' => ['aculta-messenger-sender', 'aculta-messenger-conversation'],
    // Rótulo Lido / Não lido no topo da caixa (sem cor de marcação): ver css/components/messenger-drawer.css.
    // Tópico do Fórum: contagem de respostas no título da seção e chamada para visitante.
    'forum-reply-total' => ['aculta-forum-topic__replies-count'],
    'forum-topic-visitor' => ['aculta-forum-topic__visitor'],
    'read-label' => ['aculta-read-label'],
    'read-label-unread' => ['aculta-read-label--unread'],
    'messenger-sender-link' => ['aculta-messenger-sender__link'],
    'messenger-sender-date' => ['aculta-messenger-sender__date'],
    'messenger-sender-name' => ['aculta-messenger-sender__name'],
    'messenger-sender-avatar' => ['aculta-messenger-sender__avatar'],
    // Avisos na gaveta: resumo em caixa de botão (como uma conversa); o aviso completo abre na mesma gaveta.
    'notice-summary' => ['aculta-messenger-sender', 'aculta-notice-summary'],
    'notice-summary-link' => ['aculta-messenger-sender__link'],
    'notice-summary-persistent' => ['aculta-notice-summary--persistent'],
    // Cor de cada tipo de aviso (ver Support\NoticeType): informação, atenção e urgente.
    'notice-type-info' => ['aculta-notice-type--info'],
    'notice-type-warning' => ['aculta-notice-type--warning'],
    'notice-type-danger' => ['aculta-notice-type--danger'],
    'notice-summary-title' => ['aculta-notice-summary__title'],
    'notice-summary-excerpt' => ['aculta-notice-summary__excerpt'],
    'notice-summary-state' => ['aculta-notice-summary__state'],
    'notice-detail' => ['aculta-notice-detail'],
    'achievement-notices' => ['aculta-achievement-notices'],
    'aculta-image-audit' => ['aculta-image-audit'],
    // Seleção de membros da nova conversa (estilo Gmail): resultados e o campo com os chips.
    'new-conversation-add' => ['aculta-new-conversation-add'],
    'new-conversation-chips' => ['aculta-chips'],
    'new-conversation-chip' => ['aculta-chip'],
    'new-conversation-chip-name' => ['aculta-chip__name'],
    'new-conversation-chip-remove' => ['aculta-chip__remove'],
    // Administração de avisos (ACULTA Avisos).
    'notice-admin-item' => ['admin-item'],
    'notice-admin-intro' => ['aculta-notice-admin-intro'],
    'notice-admin-list' => ['admin-list', 'aculta-notice-admin-list'],
    'notice-admin-table' => ['aculta-notice-admin-table'],
    'account-photo-trigger' => ['portal-account-photo__trigger'],
    'support-form' => ['aculta-support'],
    'security-password-form' => ['aculta-security-password-form'],
    'registration-status' => ['aculta-registration-status'],
    'support-intro' => ['aculta-support-intro'],
    'support-other' => ['aculta-support-other'],
    'domain-brand-image' => ['aculta-domain-brand-image'],
    'button' => ['button'],
    'button-secondary' => ['button', 'button--secondary'],
    'button-primary' => ['btn', 'btn-primary'],
    'aculta-button-secondary' => ['aculta-button', 'aculta-button--secondary'],
    'aculta-button-primary' => ['aculta-button', 'aculta-button--primary'],
    'aculta-button-outline' => ['aculta-button', 'aculta-button--outline'],
    'status-attention' => ['aculta-portal-requirements__status--attention'],
    'breadcrumb-list' => ['breadcrumb'],
    // Formulário de apoio: valores sugeridos (módulo aculta_portal, SupportForm).
    'support-amounts' => ['aculta-support-amounts'],
    // Painéis de vendas e doações (módulo aculta_portal, SalesOrdersTableBuilder e controllers de relatório).
    'sales-panel' => ['aculta-sales-panel'],
    'sales-section' => ['aculta-sales-panel__section'],
    'sales-heading' => ['aculta-sales-panel__heading'],
    'sales-table' => ['table', 'table-hover', 'align-middle', 'aculta-sales-panel__table'],
    'sales-pagination' => ['aculta-sales-panel__pagination'],
    'sales-payment' => ['aculta-sales-panel__payment'],
    'sales-action' => ['btn', 'btn-sm', 'btn-outline-primary', 'aculta-sales-panel__action'],
    'sales-action-danger' => ['btn', 'btn-sm', 'btn-outline-danger', 'aculta-sales-panel__action'],
    'sales-state-ok' => ['badge', 'aculta-sales-panel__state', 'aculta-sales-panel__state--ok'],
    'sales-state-pending' => ['badge', 'aculta-sales-panel__state', 'aculta-sales-panel__state--pending'],
    'sales-state-refunded' => ['badge', 'aculta-sales-panel__state', 'aculta-sales-panel__state--refunded'],
    'sales-state-voided' => ['badge', 'aculta-sales-panel__state', 'aculta-sales-panel__state--voided'],
    'sales-state-neutral' => ['badge', 'aculta-sales-panel__state', 'aculta-sales-panel__state--neutral'],
    // ACULTA Studio (aculta_studio): abas, painel e ações da conta.
    'studio-shell' => ['studio'],
    'studio-tabs' => ['nav', 'nav-tabs', 'studio__tabs'],
    'studio-tab' => ['nav-link', 'studio__tab'],
    'studio-panel' => ['studio__panel', 'card', 'card-body'],
    'studio-heading' => ['studio__heading', 'h4'],
    'studio-actions' => ['studio__actions', 'list-unstyled', 'd-flex', 'flex-wrap', 'gap-2'],
    'studio-action' => ['btn', 'btn-outline-primary', 'studio__action'],
    'studio-empty' => ['text-muted'],
    'studio-layout' => ['studio__layout'],
    'studio-sidebar' => ['studio__sidebar'],
    'studio-main' => ['studio__main'],
    'studio-sections' => ['studio__sections', 'list-unstyled'],
    'studio-subtabs' => ['nav', 'nav-tabs', 'studio__subtabs'],
    'studio-link' => ['nav-link', 'studio__link'],
  ];

  /** Maps tokens on menu link attributes (set by the Portal) to presentation classes, as for other elements. */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    if (!empty($variables['items']) && is_array($variables['items'])) {
      $this->mapMenuLinkTokens($variables['items']);
    }
  }

  /** @param array<int|string, mixed> $items */
  private function mapMenuLinkTokens(array &$items): void {
    foreach ($items as &$item) {
      $url = $item['url'] ?? NULL;
      if ($url instanceof \Drupal\Core\Url) {
        $attributes = $url->getOption('attributes') ?? [];
        $token = $attributes[self::ATTRIBUTE] ?? NULL;
        if (is_string($token) && isset(self::TOKENS[$token])) {
          unset($attributes[self::ATTRIBUTE]);
          $existing = $attributes['class'] ?? [];
          $attributes['class'] = array_values(array_unique(array_merge(is_array($existing) ? $existing : [$existing], self::TOKENS[$token])));
          $url->setOption('attributes', $attributes);
        }
      }
      if (!empty($item['below']) && is_array($item['below'])) {
        $this->mapMenuLinkTokens($item['below']);
      }
    }
    unset($item);
  }

  #[Hook('preprocess_container')]
  #[Hook('preprocess_html_tag')]
  #[Hook('preprocess_item_list')]
  #[Hook('preprocess_fieldset')]
  #[Hook('preprocess_table')]
  #[Hook('preprocess_form_element')]
  #[Hook('preprocess_image')]
  #[Hook('preprocess_aculta_portal_element')]
  #[Hook('preprocess_aculta_portal_search_trigger')]
  #[Hook('preprocess_aculta_portal_cart_status')]
  #[Hook('preprocess_aculta_portal_shell')]
  #[Hook('preprocess_aculta_portal_account_terms_modal')]
  #[Hook('preprocess_aculta_portal_photo_editor')]
  public function preprocess(array &$variables): void {
    $this->addAccountUnreadBadges($variables);
    // #attributes do elemento de render são mesclados em "attributes" depois do preprocess: trata o elemento.
    if (isset($variables['element']) && is_array($variables['element'])) {
      $this->applyArray($variables['element']['#attributes'] ?? [], $variables['element'], '#attributes');
      $this->applyArray($variables['element']['#wrapper_attributes'] ?? [], $variables['element'], '#wrapper_attributes');
      $this->applyArray($variables['element']['#title_attributes'] ?? [], $variables['element'], '#title_attributes');
    }
    // Rótulo de campo: o núcleo monta o rótulo como render array (label) com #title_attributes.
    if (isset($variables['label']) && is_array($variables['label']) && isset($variables['label']['#title_attributes']) && is_array($variables['label']['#title_attributes'])) {
      $this->applyArray($variables['label']['#title_attributes'], $variables['label'], '#title_attributes');
    }
    foreach (['attributes', 'title_attributes', 'content_attributes'] as $key) {
      // Antes da conversão para Attribute o valor ainda é array (preprocess inicial do núcleo).
      if (isset($variables[$key]) && is_array($variables[$key])) {
        $this->applyArray($variables[$key], $variables, $key);
      }
    }
    foreach ($variables as $value) {
      if ($value instanceof Attribute) {
        $this->apply($value);
      }
    }
    if (isset($variables['options']['attributes']) && $variables['options']['attributes'] instanceof Attribute) {
      $this->apply($variables['options']['attributes']);
    }
    foreach (($variables['items'] ?? []) as $key => $item) {
      if (is_array($item) && isset($item['attributes']) && $item['attributes'] instanceof Attribute) {
        $this->apply($item['attributes']);
      }
      if (is_array($item) && isset($item['#wrapper_attributes']) && is_array($item['#wrapper_attributes'])) {
        $this->applyArray($item['#wrapper_attributes'], $variables['items'][$key], '#wrapper_attributes');
      }
    }
  }

  /** The details of the Opções box: mapped after the Bootstrap accordion has set its attributes. */
  #[Hook('preprocess_details')]
  public function preprocessDetails(array &$variables): void {
    $this->preprocess($variables);
  }

  /** Maps the neutral unread count from the account contract to the theme SDC. */
  private function addAccountUnreadBadges(array &$variables): void {
    foreach (['menu_items', 'accordion_items'] as $listName) {
      if (!is_array($variables[$listName] ?? NULL)) {
        continue;
      }
      foreach ($variables[$listName] as &$item) {
        $count = filter_var($item['badge_count'] ?? 0, FILTER_VALIDATE_INT);
        unset($item['badge_count']);
        if ($count === FALSE || $count < 1) {
          continue;
        }
        $item['unread_badge'] = [
          '#type' => 'component',
          '#component' => 'aculta420:unread-badge',
          '#props' => ['count' => $count],
        ];
      }
      unset($item);
    }
  }

  /**
   * Versão para arrays de atributos de render elements: troca o token pelas classes e grava no próprio elemento.
   *
   * @param array<string, mixed> $attributes
   *   Os atributos do elemento (lidos).
   * @param array<string, mixed> $container
   *   O array que contém os atributos (elemento ou item), alterado no lugar.
   */
  private function applyArray(array $attributes, array &$container, string $key): void {
    if (!isset($attributes[self::ATTRIBUTE])) {
      return;
    }
    $tokens = preg_split('/\s+/', trim((string) $attributes[self::ATTRIBUTE])) ?: [];
    unset($attributes[self::ATTRIBUTE]);
    $classes = [];
    foreach ($tokens as $token) {
      array_push($classes, ...(self::TOKENS[$token] ?? []));
    }
    $existing = $attributes['class'] ?? [];
    $attributes['class'] = array_values(array_unique(array_merge(is_array($existing) ? $existing : [$existing], $classes)));
    // Variáveis de template (attributes, title_attributes, content_attributes) são Attribute; elementos guardam array.
    $container[$key] = in_array($key, ['attributes', 'title_attributes', 'content_attributes'], TRUE) ? new Attribute($attributes) : $attributes;
  }

  private function apply(Attribute $attributes): void {
    if (!$attributes->offsetExists(self::ATTRIBUTE)) {
      return;
    }
    $tokens = preg_split('/\s+/', trim((string) $attributes[self::ATTRIBUTE]->value())) ?: [];
    $attributes->removeAttribute(self::ATTRIBUTE);
    foreach ($tokens as $token) {
      foreach (self::TOKENS[$token] ?? [] as $class) {
        $attributes->addClass($class);
      }
    }
  }

}
