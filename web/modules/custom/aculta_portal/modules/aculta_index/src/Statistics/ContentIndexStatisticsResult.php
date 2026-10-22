<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Statistics;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Statistics totals correlated with one authorized Content Index page.
 */
final readonly class ContentIndexStatisticsResult {

  /**
   * Creates the correlation result.
   *
   * @param bool $available
   *   TRUE when ACULTA Statistics is enabled and supplied the totals.
   * @param list<\Drupal\Core\Entity\ContentEntityInterface> $entities
   *   Authorized entities from the same page used for aggregation.
   * @param array<string|int, int> $totals
   *   Totals keyed by authorized entity ID; missing metrics are zero.
   * @param string|null $nextCursor
   *   Opaque Content Index continuation token, if present.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Metadata of the authorized content query.
   */
  public function __construct(
    public bool $available,
    public array $entities,
    public array $totals,
    public ?string $nextCursor,
    public CacheableMetadata $cacheability,
  ) {}

}
