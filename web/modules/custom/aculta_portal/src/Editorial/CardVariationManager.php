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

  private const VARIATION_ATTRIBUTE = 'data-card-variation';

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
   * Returns the closed list of allowed variation identifiers.
   *
   * @return string[]
   *   Allowed variation identifiers.
   */
  public function getAllowedVariations(): array {
    return self::VALID_VARIATIONS;
  }

  /**
   * Decorates a render array with the standardized card variation attributes.
   *
   * @param array<string, mixed> $build
   *   The render array to decorate.
   * @param string $variation
   *   Desired variation.
   */
  public function applyVariation(array &$build, string $variation): void {
    $valid = $this->normalize($variation);

    $existingTokens = $build['#attributes']['data-portal-ui'] ?? '';
    $tokens = is_string($existingTokens)
      ? (preg_split('/\s+/', trim($existingTokens), -1, PREG_SPLIT_NO_EMPTY) ?: [])
      : [];
    $tokens[] = 'editorial-card';
    $build['#attributes']['data-portal-ui'] = implode(' ', array_unique($tokens));
    $build['#attributes'][self::VARIATION_ATTRIBUTE] = $valid;
  }

}
