<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_portal\Cart\CartConsolidator;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Guards non-mutating boundaries of the existing Portal cart consolidator.
 *
 * No container, database, cart deletion, or order creation required.
 *
 * @group aculta_commerce
 */
final class PortalCartConsolidatorBoundaryTest extends UnitTestCase {

  /**
   * An empty cart collection must not attempt to create a Commerce order.
   */
  public function testEmptyCartDoesNotConsolidate(): void {
    $provider = $this->createMock(CartProviderInterface::class);
    $provider->method('getCarts')->willReturn([]);

    $session = $this->createMock(CartSessionInterface::class);
    $session->expects(self::never())->method('addCartId');

    $consolidator = new CartConsolidator(
      $provider,
      $session,
      $this->createMock(CurrentStoreInterface::class),
      $this->createMock(AccountInterface::class),
    );

    self::assertNull($consolidator->consolidar());
  }

  /**
   * Subscription-only cart remains outside the shared checkout.
   */
  public function testSubscriptionOnlyIsNotConsolidated(): void {
    $subscription = $this->createMock(OrderInterface::class);
    $subscription->method('bundle')->willReturn(CartConsolidator::TIPO_ASSINATURA);

    $provider = $this->createMock(CartProviderInterface::class);
    $provider->method('getCarts')->willReturn([$subscription]);

    $session = $this->createMock(CartSessionInterface::class);
    $session->expects(self::never())->method('addCartId');
    $subscription->expects(self::never())->method('delete');

    $consolidator = new CartConsolidator(
      $provider,
      $session,
      $this->createMock(CurrentStoreInterface::class),
      $this->createMock(AccountInterface::class),
    );

    self::assertNull($consolidator->consolidar());
  }

}
