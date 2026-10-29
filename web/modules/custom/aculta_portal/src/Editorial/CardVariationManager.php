<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Editorial;

/**
 * Manages official, closed card variations across ACULTA presentation layers.
 *
 * Enforces the approved canonical list of 4 variations:
 * - compact: list layout (title, date/meta, link).
 * - teaser: standard editorial card (media, title, summary, meta).
 * - destaque: prominent hero card (large cover, bold header, summary).
 * - busca: search result card (snippet with highlighted term, domain purpose).
 */
class CardVariationManager {

  public const VARIATION_COMPACT = 'compact';
  public const VARIATION_TEASER = 'teaser';
  public const VARIATION_DESTAQUE = 'destaque';
  public const VARIATION_BUSCA = 'busca';

  /**
   * Official variations list.
   */
  public const VALID_VARIATIONS = [
    self::VARIATION_COMPACT,
    self::VARIATION_TEASER,
    self::VARIATION_DESTAQUE,
    self::VARIATION_BUSCA,
  ];

  /**
   * Normalizes and validates a variation string, falling back to teaser safely.
   */
  public function normalize(string $variation): string {
    $normalized = strtolower(trim($variation));
    return in_array($normalized, self::VALID_VARIATIONS, TRUE) ? $normalized : self::VARIATION_TEASER;
  }

  /**
   * Returns list of allowed variation identifiers.
   *
   * @return string[]
   */
  public function getAllowedVariations(): array {
    return self::VALID_VARIATIONS;
  }

  /**
   * Decorates a Drupal render array with the standardized card variation attributes.
   *
   * @param array<string, mixed> $build
   *   The render array to decorate.
   * @param string $variation
   *   Desired variation.
   */
  public function applyVariation(array &$build, string $variation): void {
    $valid = $this->normalize($variation);

    $build['#attributes']['class'][] = 'aculta-card';
    $build['#attributes']['class'][] = 'aculta-card--' . $valid;
    $build['#attributes']['data-card-variation'] = $valid;
  }

}
