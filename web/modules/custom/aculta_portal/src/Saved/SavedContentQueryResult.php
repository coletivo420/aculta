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
   * Constructs a SavedContentQueryResult instance.
   *
   * @param \Drupal\aculta_portal\Saved\SavedContentItem[] $items
   *   Authorized saved items in current page.
   * @param int $totalCount
   *   Total count of accessible items matching filter.
   * @param bool $hasMore
   *   Whether more items exist beyond current page.
   * @param string[] $cacheContexts
   *   Cache contexts.
   * @param string[] $cacheTags
   *   Cache tags.
   * @param int $cacheMaxAge
   *   Cache max-age.
   */
  public function __construct(
    public readonly array $items,
    public readonly int $totalCount,
    public readonly bool $hasMore,
    public readonly array $cacheContexts = ['user', 'user.permissions'],
    public readonly array $cacheTags = ['flagging_list'],
    public readonly int $cacheMaxAge = Cache::PERMANENT,
  ) {}

  /**
   * Gets the items in current page.
   *
   * @return \Drupal\aculta_portal\Saved\SavedContentItem[]
   *   Array of saved items.
   */
  public function getItems(): array {
    return $this->items;
  }

  /**
   * Gets the total accessible count.
   */
  public function getTotalCount(): int {
    return $this->totalCount;
  }

  /**
   * Checks whether more items exist.
   */
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
