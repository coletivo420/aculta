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
    // Relatórios de requisitos e segredos (administração do Portal).
    'requirements' => ['aculta-portal-requirements'],
    'requirements-legend' => ['aculta-portal-requirements__legend'],
    'requirements-status' => ['aculta-portal-requirements__status'],
    'status-ok' => ['aculta-portal-requirements__status--ok'],
    'status-warning' => ['aculta-portal-requirements__status--warning'],
    'status-error' => ['aculta-portal-requirements__status--error'],
    'status-pending' => ['aculta-portal-requirements__status--pending'],
    'requirements-indexing' => ['aculta-portal-requirements__indexing'],
    'requirements-indexing-table' => ['aculta-portal-requirements__indexing-table'],
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
    'search-preview-list' => ['aculta-search-preview__list', 'list-unstyled'],
    'search-preview-footer' => ['aculta-search-preview__footer', 'd-flex', 'justify-content-center', 'mt-2'],
    'search-preview-full' => ['aculta-search-preview__full', 'btn', 'btn-outline-primary', 'btn-sm'],
    'hint' => ['small', 'text-body-secondary'],
    'hint-muted' => ['text-body-secondary'],
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
    'account-bio-card' => ['card', 'card-body', 'portal-account-bio'],
    'account-bio-text' => ['portal-account-bio__text'],
    'account-bio-edit' => ['btn', 'btn-link', 'portal-account-bio__edit', 'bi', 'bi-pencil'],
    'account-bio-actions' => ['portal-account-bio__actions'],
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
  ];

  #[Hook('preprocess_container')]
  #[Hook('preprocess_html_tag')]
  #[Hook('preprocess_item_list')]
  #[Hook('preprocess_input')]
  #[Hook('preprocess_form_element')]
  #[Hook('preprocess_image')]
  #[Hook('preprocess_aculta_portal_element')]
  #[Hook('preprocess_aculta_portal_search_trigger')]
  #[Hook('preprocess_aculta_portal_cart_status')]
  #[Hook('preprocess_aculta_portal_shell')]
  #[Hook('preprocess_aculta_portal_account_terms_modal')]
  #[Hook('preprocess_aculta_portal_photo_editor')]
  public function preprocess(array &$variables): void {
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
