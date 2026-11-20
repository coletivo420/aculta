<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

/**
 * Computes the community approval score from existing reaction rows.
 *
 * All existing positive emoji reactions count as +1, including retired emoji
 * IDs. A single separate dislike reaction counts as -1. The public total is
 * clamped at zero; the underlying votes are never overwritten or discarded.
 */
final class ReactionScore {

  public const DISLIKE = 'dislike';

  /**
   * Builds a public score from reaction counts, without entity access.
   *
   * Callers must authorize the target and apply Origin/Domain isolation first.
   *
   * @param array<string, int|string> $counts
   *   Reaction machine ID => row count from storage.
   *
   * @return array{positive_count: int, dislike_count: int, total_count: int}
   *   Positive votes, dislikes and the nonnegative net total.
   */
  public static function summarize(array $counts): array {
    $positive = 0;
    $dislikes = 0;
    foreach ($counts as $reaction => $count) {
      if (!is_int($count) && (!is_string($count) || !ctype_digit($count))) {
        continue;
      }
      $quantity = max(0, (int) $count);
      if ($reaction === self::DISLIKE) {
        $dislikes += $quantity;
      }
      else {
        $positive += $quantity;
      }
    }

    return [
      'positive_count' => $positive,
      'dislike_count' => $dislikes,
      'total_count' => max(0, $positive - $dislikes),
    ];
  }

}
