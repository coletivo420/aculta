<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Query;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * A bounded page of source entities authorized for one account.
 */
final readonly class ContentIndexQueryResult {

  /**
   * Creates a query result without totals or facet metadata.
   *
   * @param list<\Drupal\Core\Entity\ContentEntityInterface> $entities
   *   Authorized source translations.
   * @param string|null $nextCursor
   *   Opaque continuation token, when the candidate page reached its bound.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Combined cache metadata; initial query responses are not cacheable.
   */
  public function __construct(
    public array $entities,
    public ?string $nextCursor,
    public CacheableMetadata $cacheability,
  ) {}

}
