<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Statistics;

use Drupal\Core\Session\AccountInterface;

/**
 * Aggregates Statistics dimensions for a bounded set of authorized entities.
 */
interface ContentEntityMetricAggregatorInterface {

  /**
   * Returns totals for supported entity-ID dimensions in a day range.
   *
   * The caller must pass only entities whose current access was verified.
   * Implementations enforce their own metric catalog and reporting permission.
   *
   * @param string $metric
   *   Metric identifier owned by the Statistics module.
   * @param int $fromDay
   *   First UTC day, inclusive.
   * @param int $toDay
   *   Last UTC day, inclusive.
   * @param list<array{entity_type: string, bundle: string, entity_id: string}> $entities
   *   Authorized identities selected by the content owner, at most 100.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account whose Statistics reporting permission is checked.
   *
   * @return array<string|int, int>
   *   Aggregate totals keyed by the entity ID dimension. Missing dimensions
   *   are omitted; callers may present them as zero.
   */
  public function aggregateForAuthorizedEntities(
    string $metric,
    int $fromDay,
    int $toDay,
    array $entities,
    AccountInterface $account,
  ): array;

}
