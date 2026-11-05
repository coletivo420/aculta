<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;

/**
 * Traduz data-portal-ui em classes do ACULTA420 V2.
 *
 * O módulo marca o elemento com data-portal-ui="token" e não escreve classe de
 * apresentação. Este hook troca cada token pelas classes correspondentes e remove
 * o atributo. Token desconhecido não gera classe.
 *
 * Usa hook_preprocess genérico: vale para todo theme hook que recebe um Attribute
 * (attributes, wrapper_attributes, title_attributes, content_attributes etc.), não só
 * para links. Mapa: só os tokens migrados para o V2. Os demais continuam no mapa do
 * V1 (web/themes/custom/aculta420/src/Hook/PortalUiHooks.php) até a migração,
 * registrada em docs/architecture.md, seção 7.
 */
final class PortalUiHooks {

  public const ATTRIBUTE = 'data-portal-ui';

  /**
   * Mapa token → classes do V2. A ordem dos tokens define a ordem das classes.
   */
  public const TOKENS = [
    'surface-list-edit-action' => ['button', 'button--small'],
    'surface-dns-block' => ['superficies-dns'],
    'aculta-lms-lesson-preview--activity' => ['aculta-lms-lesson-preview__activity'],
    'lms-answer-review-button' => ['button'],
    'aculta-lms-class-status-history--period' => ['aculta-lms-class-status-history__period'],
    'lms-dashboard-links-inline' => ['links', 'inline'],
    'aculta-lms-course-sort-form' => ['aculta-lms-course-sort-form'],
    'aculta-lms-course-builder--actions' => ['aculta-lms-course-builder__actions'],
    'aculta-lms-course-builder--activity' => ['aculta-lms-course-builder__activity'],
    'aculta-lms-course-builder--activities' => ['aculta-lms-course-builder__activities'],
    'aculta-lms-course-builder--sort-handle' => ['aculta-lms-course-builder__sort-handle'],
    'aculta-lms-course-builder--lesson' => ['aculta-lms-course-builder__lesson'],
    'aculta-lms-course-builder--lesson-heading' => ['aculta-lms-course-builder__lesson-heading'],
    'aculta-lms-course-builder--state' => ['aculta-lms-course-builder__state'],
    'aculta-lms-course-builder--published' => ['is-published'],
    'aculta-lms-course-builder--draft' => ['is-draft'],
    'aculta-lms-course-builder--empty' => ['aculta-lms-course-builder__empty'],
    'aculta-lms-course-builder--lessons' => ['aculta-lms-course-builder__lessons'],
    'aculta-lms-course-builder--header' => ['aculta-lms-course-builder__header'],
    'aculta-lms-course-builder--summary' => ['aculta-lms-course-builder__summary'],
    'aculta-lms-course-materials--documents' => ['aculta-lms-course-materials__documents'],
    'aculta-lms-course-materials--document' => ['aculta-lms-course-materials__document'],
    // Sem classe no V2: estilizados por seletor de atributo (no V1, studio.css; no V2, lms-lesson-page.css e events.css).
    // Lista vazia torna o token conhecido sem gerar classe. Estilo visual do V2 pendente de migração e captura (DEFERRED).
    'aculta-btn-outline' => [],
    'aculta-btn-primary' => [],
    'aculta-course-lesson-preview' => [],
    'aculta-learning-home__actions' => [],
    'aculta-learning-home__course-title' => [],
    'aculta-learning-home__lesson' => [],
    'aculta-learning-home__meta' => [],
    'aculta-learning-home__pending' => [],
    'aculta-learning-home__progress' => [],
    'aculta-learning-home__section' => [],
    'aculta-learning-home__section-title' => [],
    'aculta-learning-journey' => [],
    'aculta-learning-journey__back' => [],
    'aculta-learning-journey__current' => [],
    'aculta-learning-journey__eyebrow' => [],
    'aculta-learning-journey__intro' => [],
    'aculta-learning-journey__lesson' => [],
    'aculta-learning-journey__lesson-label' => [],
    'aculta-learning-journey__lessons' => [],
    'aculta-learning-journey__meta' => [],
    'aculta-learning-journey__outline' => [],
    'aculta-learning-journey__progress' => [],
    'aculta-learning-journey__state' => [],
    'aculta-learning-journey__summary' => [],
    'aculta-learning-journey__title' => [],
    'aculta-learning-room' => [],
    'aculta-learning-room__back-link' => [],
    'aculta-learning-room__layout' => [],
    'aculta-learning-room__lesson' => [],
    'aculta-learning-room__outline' => [],
    'aculta-learning-room__outline-title' => [],
    'event-contribution-queue' => [],
    'event-contribution-status' => [],
    'event-contribution-workspace' => [],
    'event-location-contribution-queue' => [],
    'event-organization-suggestion-queue' => [],
    'patcher-table-wrapper' => [],
    'studio-course-educator' => [],
    'studio-mercadopago-plans' => [],
    'map-popup' => [],
  ];

  /**
   * Troca data-portal-ui por classes em todos os Attribute do theme hook.
   */
  #[Hook('preprocess')]
  public function preprocess(array &$variables): void {
    foreach ($variables as $key => $value) {
      if (is_string($key) && str_ends_with($key, 'attributes') && $value instanceof Attribute) {
        $this->apply($value);
      }
    }
  }

  private function apply(Attribute $attributes): void {
    if (!$attributes->offsetExists(self::ATTRIBUTE)) {
      return;
    }
    $tokens = preg_split('/\s+/', trim((string) $attributes[self::ATTRIBUTE]->value())) ?: [];
    // O atributo permanece no DOM: o CSS do V2 seleciona por [data-portal-ui~="token"] (events.css, lms-lesson-page.css).
    foreach ($tokens as $token) {
      foreach (self::TOKENS[$token] ?? [] as $class) {
        $attributes->addClass($class);
      }
    }
  }

}
