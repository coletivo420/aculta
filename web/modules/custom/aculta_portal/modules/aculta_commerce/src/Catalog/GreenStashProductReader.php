<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Catalog;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Cursor-based, read-only product list with entity and store access checks.
 *
 * The cursor indicates the last scanned product ID, not an offset. No count
 * is exposed, including counts for products belonging to other stores.
 */
final class GreenStashProductReader {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * @return array{items: array, next: ?int}
   */
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
        if (!$product->access('view', $account) || !$product->access('update', $account)) {
          continue;
        }
        $allowed = FALSE;
        foreach ($product->getStores() as $store) {
          if ($store->access('update', $account)) {
            $allowed = TRUE;
            break;
          }
        }
        if (!$allowed) {
          continue;
        }
        if (count($items) === $limit) {
          $hasMore = TRUE;
          break 2;
        }
        $items[] = $product;
      }
      // If a round yielded fewer than the batch size, no further IDs exist.
      if (count($ids) < 100) {
        break;
      }
    }

    return ['items' => $items, 'next' => $hasMore ? $cursor : NULL];
  }

}
