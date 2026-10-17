<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

/**
 * Centralizes route-family ownership rules shared across Portal boundaries.
 */
final class DomainRoutePolicy {

  /**
   * Returns TRUE for cart/checkout/payment routes that always belong to MAIN.
   */
  public static function isCentralTransactionRouteName(string $routeName): bool {
    return str_starts_with($routeName, 'commerce_cart.')
      || str_starts_with($routeName, 'commerce_checkout.')
      || str_starts_with($routeName, 'commerce_payment.checkout.')
      || $routeName === 'commerce_payment.notify'
      || self::isDonationFlowRouteName($routeName);
  }

  /** Returns TRUE for Commerce Donation Flow routes. */
  public static function isDonationFlowRouteName(string $routeName): bool {
    return str_starts_with($routeName, 'aculta_donation.');
  }

  /** Returns TRUE for private transaction routes that must not be indexed. */
  public static function isTransactionalSeoRouteName(string $routeName): bool {
    return self::isCentralTransactionRouteName($routeName)
      || str_starts_with($routeName, 'commerce_payment.')
      || str_starts_with($routeName, 'entity.commerce_order.');
  }

}
