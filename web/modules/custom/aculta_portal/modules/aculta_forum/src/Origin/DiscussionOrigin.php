<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Origin;

/**
 * Stable source categories for discussion entities and their projections.
 */
final class DiscussionOrigin {

  public const ARCHIVE = 'ARCHIVE';

  public const CURRENT = 'CURRENT';

  public const CONTEXTUAL = 'CONTEXTUAL';

  public const UNKNOWN = 'UNKNOWN';

  /**
   * Returns whether a value is a supported origin identifier.
   */
  public static function isValid(string $origin): bool {
    return in_array($origin, [self::ARCHIVE, self::CURRENT, self::CONTEXTUAL, self::UNKNOWN], TRUE);
  }

  private function __construct() {}

}
