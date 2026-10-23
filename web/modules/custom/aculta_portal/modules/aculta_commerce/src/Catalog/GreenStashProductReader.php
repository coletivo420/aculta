<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Catalog;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_commerce\Store\GreenStashStoreResolver;

/**
 * Cursor-based, read-only product list with entity and store access checks.
 *
 * The cursor indicates the last scanned product ID, not an offset. No count
 * is exposed, including counts for products belonging to other stores.
 */
final class GreenStashProductReader {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly GreenStashStoreResolver $storeResolver,
  ) {}

  /**
   * @return array{items: array, next: ?int}
   */
  /**
   * Only expose a store product when both entity and store permit management.
   */
  public function canManage(\Drupal\Core\Entity\EntityInterface $product, AccountInterface $account): bool {
    if ($product->bundle() !== 'produto_loja'
      || !$product->access('view', $account)
      || !$product->access('update', $account)
      || !method_exists($product, 'getStores')) {
      return FALSE;
    }
    $managed = $this->storeResolver->resolve($account);
    if ($managed === NULL) {
      return FALSE;
    }
    foreach ($product->getStores() as $store) {
      if ((string) $store->id() === (string) $managed->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function page(AccountInterface $account, int $before = 0, string $search = '', int $limit = 20): array {
    $limit = max(1, min(20, $limit));
    $search = trim($search);
    if (mb_strlen($search) > 100) {
      $search = mb_substr($search, 0, 100);
    }
    $storage = $this->entities->getStorage('commerce_product');
    $items = [];
    $cursor = $before;
    $hasMore = FALSE;
    $lastEmitted = NULL;

    // Bounded rounds prevent an unbounded request when access filters are selective.
    for ($round = 0; $round < 5; $round++) {
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'produto_loja')
        ->sort('product_id', 'DESC')
        ->range(0, 100);
      if ($cursor > 0) {
        $query->condition('product_id', $cursor, '<');
      }
      if ($search !== '') {
        $query->condition('title', $search, 'CONTAINS');
      }
      $ids = array_values($query->execute());
      if ($ids === []) {
        break;
      }
      foreach ($storage->loadMultiple($ids) as $product) {
        $cursor = (int) $product->id();
        if (!$this->canManage($product, $account)) {
          continue;
        }
        if (count($items) === $limit) {
          $hasMore = TRUE;
          break 2;
        }
        $items[] = $product;
        $lastEmitted = (int) $product->id();
      }
      // If a round yielded fewer than the batch size, no further IDs exist.
      if (count($ids) < 100) {
        break;
      }
    }

    return ['items' => $items, 'next' => $hasMore ? $lastEmitted : NULL];
  }

}
