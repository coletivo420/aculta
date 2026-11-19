<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\Database\Connection;

/**
 * Aggregates published revisionable replies for public topic View rows.
 */
final class ForumPublicPostStatistics {

  /**
   * Request-local aggregate results keyed by topic ID.
   *
   * @var array<int, array{count: int, last_activity: int, last_post_id: int}>
   */
  private array $statistics = [];

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Gets public reply count and latest published post for visible topics.
   *
   * Callers pass topic IDs already returned by the access-checked public
   * Forum View. Published ForumPosts inherit their public visibility from the
   * accessible topic according to ForumPostAccessHooks.
   *
   * @param array<int|string, mixed> $topicIds
   *   Node IDs from the current public Forum View result page.
   *
   * @return array<int, array{count: int, last_activity: int, last_post_id: int}>
   *   Aggregate data keyed by topic ID.
   */
  public function getByTopics(array $topicIds): array {
    $cleanIds = [];
    foreach ($topicIds as $val) {
      if (is_numeric($val) && (int) $val > 0) {
        $cleanIds[] = (int) $val;
      }
    }
    $cleanIds = array_values(array_unique($cleanIds));
    if ($cleanIds === []) {
      return [];
    }

    $missingIds = array_values(array_filter($cleanIds, fn (int $id): bool => !isset($this->statistics[$id])));
    foreach ($missingIds as $topicId) {
      $this->statistics[$topicId] = [
        'count' => 0,
        'last_activity' => 0,
        'last_post_id' => 0,
      ];
    }

    if ($missingIds !== []) {
      $result = $this->database->query(
        'SELECT post.topic, COUNT(*) AS post_count,
          MAX(post.changed) AS last_activity,
          MAX(latest_post.last_post_id) AS last_post_id
        FROM {aculta_forum_post_field_data} post
        LEFT JOIN (
          SELECT latest.topic, MAX(latest.id) AS last_post_id
          FROM {aculta_forum_post_field_data} latest
          INNER JOIN (
            SELECT topic, MAX(changed) AS last_changed
            FROM {aculta_forum_post_field_data}
            WHERE topic IN (:latest_topic_ids[])
              AND status = 1
              AND default_langcode = 1
            GROUP BY topic
          ) latest_change
            ON latest_change.topic = latest.topic
            AND latest_change.last_changed = latest.changed
          WHERE latest.status = 1
            AND latest.default_langcode = 1
          GROUP BY latest.topic
        ) latest_post ON latest_post.topic = post.topic
        WHERE post.topic IN (:topic_ids[])
          AND post.status = 1
          AND post.default_langcode = 1
        GROUP BY post.topic',
        [
          ':latest_topic_ids[]' => $missingIds,
          ':topic_ids[]' => $missingIds,
        ],
      );

      if ($result instanceof \Traversable) {
        foreach ($result as $row) {
          if (is_object($row) && isset($row->topic) && is_numeric($row->topic)) {
            $this->statistics[(int) $row->topic] = [
              'count' => isset($row->post_count) && is_numeric($row->post_count) ? (int) $row->post_count : 0,
              'last_activity' => isset($row->last_activity) && is_numeric($row->last_activity) ? (int) $row->last_activity : 0,
              'last_post_id' => isset($row->last_post_id) && is_numeric($row->last_post_id) ? (int) $row->last_post_id : 0,
            ];
          }
        }
      }
    }

    return array_intersect_key($this->statistics, array_fill_keys($cleanIds, TRUE));
  }

}
