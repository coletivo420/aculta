<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Identity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;

/**
 * Maps a discussion host to its author-card context without granting access.
 */
final class AuthorCardVariantResolver {

  /**
   * Returns the author-card variant for a supported discussion host.
   *
   * The mapping names presentation and data policy boundaries. It does not
   * imply that the host or comment is visible to the current account.
   */
  public function forHost(EntityInterface $host): ?string {
    if (!$host instanceof NodeInterface) {
      return NULL;
    }

    return match ($host->bundle()) {
      'aculta_forum_topic' => 'author_forum',
      'article', 'blog' => 'author_comment',
      'aculta_lms_topic' => 'author_lms',
      'wiki_entry' => 'author_wiki',
      default => NULL,
    };
  }

}
