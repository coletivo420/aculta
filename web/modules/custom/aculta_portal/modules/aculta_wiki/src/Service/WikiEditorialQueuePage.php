<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * A bounded page of authorized Wiki editorial work.
 */
final class WikiEditorialQueuePage implements CacheableDependencyInterface {

  /**
   * Constructs a page of authorized entities.
   *
   * @param array<int, object> $items
   *   Entities the caller may display or link to.
   * @param string|null $nextCursor
   *   Opaque positional cursor for the next page, if one exists.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cache metadata of the query and access decisions.
   */
  public function __construct(
    private readonly array $items,
    private readonly ?string $nextCursor,
    private readonly CacheableMetadata $cacheability,
  ) {}

  /**
   * Returns authorized entities on this page.
   *
   * @return array<int, object>
   *   Authorized editorial entities, such as proposal entities or Node
   *   revisions.
   */
  public function getItems(): array {
    return $this->items;
  }

  /**
   * Returns the positional cursor for the next page, if any.
   */
  public function getNextCursor(): ?string {
    return $this->nextCursor;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
