<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Statistics;

use Drupal\aculta_index\Query\ContentIndexQueryInterface;
use Drupal\aculta_portal\Statistics\ContentEntityMetricAggregatorInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Correlates authorized Content Index pages with Statistics aggregates.
 */
final class ContentIndexStatisticsBridge {

  public function __construct(
    private readonly ContentIndexQueryInterface $query,
    private readonly ?ContentEntityMetricAggregatorInterface $statistics,
  ) {}

  /**
   * Aggregates one metric over the authorized entities in a query page.
   *
   * The Index selects and access-checks the content first. Statistics then
   * validates its own metric, sensitivity, reporting permission and dimension
   * semantics. Callers must filter the Index page to the entity type and bundle
   * supported by that metric; incompatible pages are rejected instead of
   * silently omitting items. The two modules never join tables directly.
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
   *
   * @throws \InvalidArgumentException
   *   If the metric dimensions or selected entities are incompatible with the
   *   Statistics contract.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If Statistics denies this metric to the account.
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
    $page = $this->query->query($filters, $account, $cursor, $limit);
    if ($this->statistics === NULL) {
      return new ContentIndexStatisticsResult(FALSE, $page->entities, [], $page->nextCursor, $page->cacheability);
    }

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
    return new ContentIndexStatisticsResult(TRUE, $page->entities, $totals, $page->nextCursor, $page->cacheability);
  }

}
