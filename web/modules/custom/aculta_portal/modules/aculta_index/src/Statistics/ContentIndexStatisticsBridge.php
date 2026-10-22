<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Statistics;

use Drupal\aculta_index\Query\ContentIndexQuery;
use Drupal\aculta_portal\Statistics\ContentEntityMetricAggregatorInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;

/**
 * Correlates authorized Content Index pages with Statistics aggregates.
 */
final class ContentIndexStatisticsBridge {

  public function __construct(
    private readonly ContentIndexQuery $query,
    private readonly ?ContentEntityMetricAggregatorInterface $statistics,
  ) {}

  /**
   * Aggregates one metric over the authorized entities in a query page.
   *
   * The Index selects and access-checks the content first. Statistics then
   * validates its own metric, sensitivity, reporting permission and dimension
   * semantics. The two modules never join their tables directly.
   *
   * @param array<string, mixed> $filters
   *   Allowlisted Content Index query filters.
   * @param string $metric
   *   Statistics-owned metric identifier.
   * @param int $fromDay
   *   First UTC day, inclusive.
   * @param int $toDay
   *   Last UTC day, inclusive.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account checked by both the source Entity Access API and Statistics.
   * @param string|null $cursor
   *   Opaque cursor from the prior authorized content page.
   * @param int $limit
   *   Maximum candidates in the content page, from 1 through 100.
   *
   * @return \Drupal\aculta_index\Statistics\ContentIndexStatisticsResult
   *   Per-entity totals and the content query's continuation token.
   */
  public function aggregateForQuery(
    array $filters,
    string $metric,
    int $fromDay,
    int $toDay,
    AccountInterface $account,
    ?string $cursor = NULL,
    int $limit = 100,
  ): ContentIndexStatisticsResult {
    if ($this->statistics === NULL) {
      $cacheability = new CacheableMetadata();
      $cacheability->setCacheMaxAge(0);
      return new ContentIndexStatisticsResult(FALSE, [], NULL, $cacheability);
    }

    $page = $this->query->query($filters, $account, $cursor, $limit);
    $identities = [];
    foreach ($page->entities as $entity) {
      $identities[] = [
        'entity_type' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'entity_id' => (string) $entity->id(),
      ];
    }
    $aggregated = $this->statistics->aggregateForAuthorizedEntities(
      $metric,
      $fromDay,
      $toDay,
      $identities,
      $account,
    );

    $totals = [];
    foreach ($identities as $identity) {
      $dimension = $identity['entity_id'];
      $totals[$dimension] = (int) ($aggregated[$dimension] ?? 0);
    }
    return new ContentIndexStatisticsResult(TRUE, $totals, $page->nextCursor, $page->cacheability);
  }

}
