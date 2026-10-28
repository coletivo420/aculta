<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access policy for Mercado Pago plan administration.
 */
final class MercadoPagoPlanAccess {

  /**
   * Checks authenticated Portal access and the donation-admin permission.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access aculta portal'))
      ->andIf(AccessResult::allowedIfHasPermission($account, 'administrar doacoes'))
      ->cachePerUser();
  }

}
