<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Saved;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Result object for saved content queries with full cacheability metadata.
 */
final class SavedContentQueryResult implements CacheableDependencyInterface {

  /**
   * @param \Drupal\aculta_portal\Saved\SavedContentItem[] $items
   * @param int $totalCount
   * @param bool $hasMore
   * @param string[] $cacheContexts
   * @param string[] $cacheTags
   * @param int $cacheMaxAge
   */
  public function __construct(
    private readonly array $items,
    private readonly int $totalCount,
    private readonly bool $hasMore,
    private readonly array $cacheContexts = ['user', 'user.permissions'],
    private readonly array $cacheTags = ['flagging_list'],
    private readonly int $cacheMaxAge = Cache::PERMANENT,
  ) {}

  /**
   * @return \Drupal\aculta_portal\Saved\SavedContentItem[]
   */
  public function getItems(): array {
    return $this->items;
  }

  public function getTotalCount(): int {
    return $this->totalCount;
  }

  public function hasMore(): bool {
    return $this->hasMore;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->cacheContexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->cacheTags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->cacheMaxAge;
  }

}
