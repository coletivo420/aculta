<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Routing;

/**
 * Canonicalizes user-facing slugs before they are used in public route URLs.
 *
 * The value returned by this helper is for URL generation only. It must not be
 * written back to user-entered or editorial display values: route identity is
 * lowercase while presentation may preserve the capitalization chosen by the
 * author. Route-specific grammar, reserved words, uniqueness and access remain
 * the responsibility of the feature that owns the route.
 */
final class PublicRouteSlug {

  /**
   * Returns a trimmed, lowercase form suitable for public URL canonicalization.
   *
   * This method deliberately does not transliterate, remove punctuation,
   * truncate or validate a slug. Silently changing those parts could create a
   * URL that identifies a different entity. Callers must validate the result
   * against the owning route's contract and omit/fail invalid links safely.
   */
  public static function normalize(string $value): string {
    return mb_strtolower(trim($value), 'UTF-8');
  }

}
