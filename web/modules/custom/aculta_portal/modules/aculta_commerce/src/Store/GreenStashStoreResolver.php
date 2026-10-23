<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce\Store;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Resolves the sole Commerce store used by Green Stash.
 *
 * Never guesses when the site contains multiple store entities.
 * LMS and donations remain separated by their respective domain modules.
 */
final class GreenStashStoreResolver {

  public function __construct(private readonly EntityTypeManagerInterface $entities) {}

  public function resolve(AccountInterface $account): ?object {
    $storage = $this->entities->getStorage('commerce_store');
    $ids = $storage->getQuery()->accessCheck(TRUE)->range(0, 2)->execute();
    if (count($ids) !== 1) {
      return NULL;
    }
    $store = $storage->load(reset($ids));
    return $store !== NULL && $store->access('update', $account) ? $store : NULL;
  }

}
