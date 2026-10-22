<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Finds unread public ForumPosts using each topic's History receipt.
 */
final class ForumPostUnreadManager {

  public function __construct(
    private readonly Connection $database,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Returns unread post counts and first unread links for a set of topics.
   *
   * The caller supplies topic IDs already returned by the access-checked
   * public Forum View. Published ForumPosts on those topics inherit public
   * topic visibility from ForumPostAccessHooks.
   *
   * @param array $topicIds
   *   Topic node IDs from the current View result page.
   *
   * @return array<int, array{count: int, post_id: int, page: int}>
   *   Unread data keyed by topic node ID. Topics without unread posts are
   *   omitted.
   */
  public function getUnreadByTopics(array $topicIds): array {
    if ($this->currentUser->isAnonymous()) {
      return [];
    }

    $topicIds = array_values(array_unique(array_filter(array_map('intval', $topicIds), static fn (int $id): bool => $id > 0)));
    if ($topicIds === []) {
      return [];
    }

    $result = $this->database->query(
      'SELECT p.topic_target_id, p.id, p.thread_position, p.created,
        (SELECT COUNT(*) FROM {aculta_forum_post_field_data} unread
          LEFT JOIN {history} unread_history ON unread_history.nid = unread.topic_target_id AND unread_history.uid = :count_uid
          WHERE unread.topic_target_id = p.topic_target_id
            AND unread.status = 1
            AND unread.default_langcode = 1
            AND unread.first_published > GREATEST(COALESCE(unread_history.timestamp, :count_limit_missing), :count_limit_old)
        ) AS unread_count,
        (SELECT COUNT(*) FROM {aculta_forum_post_field_data} previous
          WHERE previous.topic_target_id = p.topic_target_id
            AND previous.status = 1
            AND previous.default_langcode = 1
            AND (previous.thread_position < p.thread_position
              OR (previous.thread_position = p.thread_position AND previous.created < p.created)
              OR (previous.thread_position = p.thread_position AND previous.created = p.created AND previous.id < p.id))
        ) AS preceding_count
      FROM {aculta_forum_post_field_data} p
      LEFT JOIN {history} h ON h.nid = p.topic_target_id AND h.uid = :history_uid
      WHERE p.topic_target_id IN (:topic_ids[])
        AND p.status = 1
        AND p.default_langcode = 1
        AND p.first_published > GREATEST(COALESCE(h.timestamp, :history_limit_missing), :history_limit_old)
        AND NOT EXISTS (SELECT 1 FROM {aculta_forum_post_field_data} earlier
          LEFT JOIN {history} earlier_history ON earlier_history.nid = earlier.topic_target_id AND earlier_history.uid = :earlier_uid
          WHERE earlier.topic_target_id = p.topic_target_id
            AND earlier.status = 1
            AND earlier.default_langcode = 1
            AND earlier.first_published > GREATEST(COALESCE(earlier_history.timestamp, :earlier_limit_missing), :earlier_limit_old)
            AND (earlier.thread_position < p.thread_position
              OR (earlier.thread_position = p.thread_position AND earlier.created < p.created)
              OR (earlier.thread_position = p.thread_position AND earlier.created = p.created AND earlier.id < p.id)))',
      [
        ':count_uid' => $this->currentUser->id(),
        ':count_limit_missing' => HISTORY_READ_LIMIT,
        ':count_limit_old' => HISTORY_READ_LIMIT,
        ':history_uid' => $this->currentUser->id(),
        ':topic_ids[]' => $topicIds,
        ':history_limit_missing' => HISTORY_READ_LIMIT,
        ':history_limit_old' => HISTORY_READ_LIMIT,
        ':earlier_uid' => $this->currentUser->id(),
        ':earlier_limit_missing' => HISTORY_READ_LIMIT,
        ':earlier_limit_old' => HISTORY_READ_LIMIT,
      ],
    );

    $unread = [];
    foreach ($result as $first) {
      $topicId = (int) $first->topic_target_id;
      $unread[$topicId] = [
        'count' => (int) $first->unread_count,
        'post_id' => (int) $first->id,
        'page' => intdiv((int) $first->preceding_count, ForumPostThreadBuilder::POSTS_PER_PAGE),
      ];
    }

    return $unread;
  }

}
