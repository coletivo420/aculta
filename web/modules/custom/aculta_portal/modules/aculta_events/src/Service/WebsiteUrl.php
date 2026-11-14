<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Service;

/**
 * Validates public website links used by event records.
 */
final class WebsiteUrl {

  /**
   * Checks whether a URL is absolute and uses a browser-safe web scheme.
   */
  public static function isValid(string $value): bool {
    if (filter_var($value, FILTER_VALIDATE_URL) === FALSE) {
      return FALSE;
    }

    $scheme = parse_url($value, PHP_URL_SCHEME);
    return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], TRUE);
  }

}
