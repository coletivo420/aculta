<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Catalog;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Read-only, bounded list of store products the operator may edit.
 *
 * No customer, order, payment or cross-store information is exposed.
 */
final class GreenStashProductReader {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * @return array<int, \Drupal\Core\Entity\EntityInterface>
   */
  public function listEditable(AccountInterface $account, int $limit = 20): array {
    $limit = max(1, min(20, $limit));
    $storage = $this->entities->getStorage('commerce_product');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'produto_loja')
      ->sort('product_id', 'DESC')
      ->range(0, 100)
      ->execute();
    if (!$ids) {
      return [];
    }
    $result = [];
    foreach ($storage->loadMultiple($ids) as $product) {
      if (!$product->access('view', $account) || !$product->access('update', $account)) {
        continue;
      }
      // Restrict to products in stores the operator can administer.
      if (!method_exists($product, 'getStores')) {
        continue;
      }
      $allowedStore = FALSE;
      foreach ($product->getStores() as $store) {
        if ($store->access('update', $account)) {
          $allowedStore = TRUE;
          break;
        }
      }
      if (!$allowedStore) {
        continue;
      }
      $result[] = $product;
      if (count($result) >= $limit) {
        break;
      }
    }
    return $result;
  }

}
