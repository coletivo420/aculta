<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Migration;

/**
 * Stable identity of archive rows migrated from the shared Forum tables.
 *
 * The key is derived from the source row id, so re-running the migration updates
 * the same historic entity instead of creating a duplicate.
 */
final class LegacyArchiveOrigin {

  /**
   * Origin key for a legacy topic that lived as a node.
   */
  public static function topicKey(int $nodeId): string {
    return 'node:' . $nodeId;
  }

  /**
   * Origin key for a legacy reply that lived as a comment.
   */
  public static function commentKey(int $commentId): string {
    return 'comment:' . $commentId;
  }

}
