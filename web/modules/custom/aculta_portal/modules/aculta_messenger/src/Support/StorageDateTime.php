<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Support;

/**
 * Reads datetime field values that Drupal stores in UTC.
 *
 * strtotime() uses the default timezone, which Drupal sets to the current user's timezone. The same
 * stored value would therefore mean a different instant for each viewer. Parsing in UTC keeps the
 * publication window and validity labels identical for every member.
 */
final class StorageDateTime {

  /** Returns the Unix timestamp of a stored Y-m-d\TH:i:s value, or NULL when empty or invalid. */
  public static function toTimestamp(?string $value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    try {
      return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
    }
    catch (\Exception) {
      return NULL;
    }
  }

}
