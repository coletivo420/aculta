<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cart, checkout and payment routes always belong to MAIN.
 *
 */
#[Group('aculta_portal')]
final class DomainRoutePolicyTest extends UnitTestCase {

  #[DataProvider('centralTransactionRoutes')]
  public function testTransactionRoutesBelongToMain(string $route): void {
    $this->assertTrue(DomainRoutePolicy::isCentralTransactionRouteName($route));
    $this->assertTrue(DomainRoutePolicy::isTransactionalSeoRouteName($route));
  }

  public static function centralTransactionRoutes(): array {
    return [
      'cart' => ['commerce_cart.page'],
      'checkout' => ['commerce_checkout.form'],
      'payment checkout' => ['commerce_payment.checkout.return'],
      'payment notify' => ['commerce_payment.notify'],
      'donation flow' => ['aculta_donation.form'],
    ];
  }

  #[DataProvider('publicRoutes')]
  public function testPublicRoutesAreNotTransactional(string $route): void {
    $this->assertFalse(DomainRoutePolicy::isCentralTransactionRouteName($route));
    $this->assertFalse(DomainRoutePolicy::isTransactionalSeoRouteName($route));
  }

  public static function publicRoutes(): array {
    return [
      'wiki home' => ['aculta_portal.wiki_home'],
      'courses' => ['aculta_portal.courses_home'],
      'login' => ['user.login'],
      'node canonical' => ['entity.node.canonical'],
    ];
  }

  public function testOrderEntitiesAreSeoTransactionalButNotCentral(): void {
    $this->assertTrue(DomainRoutePolicy::isTransactionalSeoRouteName('entity.commerce_order.canonical'));
    $this->assertFalse(DomainRoutePolicy::isCentralTransactionRouteName('entity.commerce_order.canonical'));
  }

}
