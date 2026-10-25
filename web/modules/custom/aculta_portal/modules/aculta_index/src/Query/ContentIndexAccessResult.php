<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Query;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Authorized source entities and the combined metadata required to cache them.
 */
final readonly class ContentIndexAccessResult {

  /**
   * Creates an access-filtered result.
   *
   * @param list<\Drupal\Core\Entity\ContentEntityInterface> $entities
   *   Source translations whose view access is allowed.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Combined cache contexts, tags and age from each checked source entity.
   */
  public function __construct(
    public array $entities,
    public CacheableMetadata $cacheability,
  ) {}

}
