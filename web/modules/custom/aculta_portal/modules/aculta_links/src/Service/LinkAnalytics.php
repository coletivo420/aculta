<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Queues minimal link-click events and reads their daily aggregate.
 */
final class LinkAnalytics {

  /**
   * Queue whose worker writes daily aggregate rows.
   */
  public const QUEUE_ID = 'aculta_links_analytics';

  /**
   * Aggregated click records are retained for this many days.
   */
  public const RETENTION_DAYS = 90;

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Queues a click without storing visitor or request metadata.
   */
  public function recordClick(int $linkId): void {
    if ($linkId < 1 || !$this->isEnabled()) {
      return;
    }

    $this->queueFactory->get(self::QUEUE_ID)->createItem([
      'event' => 'click',
      'target_id' => $linkId,
      'occurred_at' => $this->time->getRequestTime(),
    ]);
  }

  /**
   * Indicates whether the administrator explicitly enabled aggregate analytics.
   */
  public function isEnabled(): bool {
    return $this->configFactory->get('aculta_links.settings')->get('analytics_enabled') === TRUE;
  }

  /**
   * Returns click totals keyed by destination ID.
   *
   * @param int[] $linkIds
   *   Destination IDs already filtered by entity access.
   *
   * @return int[]
   *   Destination ID to total clicks over the retention period.
   */
  public function clickCounts(array $linkIds): array {
    $linkIds = array_values(array_unique(array_filter(array_map('intval', $linkIds), static fn (int $id): bool => $id > 0)));
    if ($linkIds === []) {
      return [];
    }

    $cutoff = gmdate('Y-m-d', $this->time->getRequestTime() - self::RETENTION_DAYS * 86400);
    $query = $this->database->select('aculta_links_stat_daily', 'stats')
      ->fields('stats', ['target_id'])
      ->condition('event', 'click')
      ->condition('target_id', $linkIds, 'IN')
      ->condition('event_date', $cutoff, '>=');
    $query->addExpression('SUM(count)', 'clicks');
    $query->groupBy('target_id');

    $counts = [];
    foreach ($query->execute() as $row) {
      $counts[(int) $row->target_id] = (int) $row->clicks;
    }
    return $counts;
  }

  /**
   * Removes daily aggregates older than the documented retention period.
   */
  public function purgeExpired(): int {
    $cutoff = gmdate('Y-m-d', $this->time->getRequestTime() - self::RETENTION_DAYS * 86400);
    $deleted = (int) $this->database->delete('aculta_links_stat_daily')
      ->condition('event_date', $cutoff, '<')
      ->execute();
    if ($deleted > 0) {
      $this->cacheTagsInvalidator->invalidateTags(['aculta_links_analytics']);
    }
    return $deleted;
  }

}
