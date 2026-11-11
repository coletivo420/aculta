<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Manages intention keys and duplicate submissions for discussion replies.
 */
class DiscussionReplyIdempotencyManager {

  public function __construct(
    protected readonly Connection $database,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Generates an intention hash for a proposed submission.
   */
  public function generateIntentionKey(AccountInterface $account, string $entityTypeId, string|int $entityId, ?int $parentId, string $body): string {
    $normalizedBody = trim(preg_replace('/\s+/', ' ', $body) ?? '');
    return hash('sha256', implode(':', [
      $account->id(),
      $entityTypeId,
      $entityId,
      $parentId ?? 0,
      $normalizedBody,
    ]));
  }

  /**
   * Checks if an identical submission was completed in the recent window.
   *
   * @param AccountInterface $account
   *   The account.
   * @param string $entityTypeId
   *   Host entity type.
   * @param string|int $entityId
   *   Host entity ID.
   * @param int|null $parentId
   *   Parent ID if any.
   * @param string $body
   *   The submission body.
   * @param int $windowSeconds
   *   Idempotency window in seconds (default 30).
   *
   * @return int|null
   *   The comment ID if duplicate detected in window, or NULL.
   */
  public function findRecentDuplicate(AccountInterface $account, string $entityTypeId, string|int $entityId, ?int $parentId, string $body, int $windowSeconds = 30): ?int {
    $now = $this->time->getRequestTime();
    $cutoff = $now - $windowSeconds;

    $query = $this->database->select('comment_field_data', 'c');
    $query->fields('c', ['cid']);
    $query->join('comment__comment_body', 'b', 'b.entity_id = c.cid AND b.deleted = 0');
    $query->condition('c.uid', $account->id());
    $query->condition('c.entity_type', $entityTypeId);
    $query->condition('c.entity_id', $entityId);
    if ($parentId !== NULL && $parentId > 0) {
      $query->condition('c.pid', $parentId);
    }
    else {
      $query->isNull('c.pid');
    }
    $query->condition('c.created', $cutoff, '>=');
    $query->condition('b.comment_body_value', trim($body));
    $query->orderBy('c.cid', 'DESC');
    $query->range(0, 1);

    $result = $query->execute()->fetchField();
    return $result ? (int) $result : NULL;
  }

}
