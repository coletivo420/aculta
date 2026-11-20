<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Author;

/**
 * Formats historical Growroom authors when attached to Forum content.
 */
final class LegacyForumUserDisplayName {

  /**
   * Adds the source suffix used to distinguish archived Forum authors.
   */
  public static function forForum(string $name): string {
    return $name . '-gr';
  }

}
