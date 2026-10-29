<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Cart, checkout and payment routes always belong to MAIN.
 */
#[Group('aculta_portal')]
final class DomainRoutePolicyTest extends UnitTestCase {

  /**
   * Checks that central transaction routes belong to MAIN.
   */
  #[DataProvider('centralTransactionRoutes')]
  public function testTransactionRoutesBelongToMain(string $route): void {
    $this->assertTrue(DomainRoutePolicy::isCentralTransactionRouteName($route));
    $this->assertTrue(DomainRoutePolicy::isTransactionalSeoRouteName($route));
  }

  /**
   * Provides central transaction route names.
   */
  public static function centralTransactionRoutes(): array {
    return [
      'cart' => ['commerce_cart.page'],
      'checkout' => ['commerce_checkout.form'],
      'payment checkout' => ['commerce_payment.checkout.return'],
      'payment notify' => ['commerce_payment.notify'],
    ];
  }

  /**
   * Checks that donation flow routes belong to SUPPORT.
   */
  #[DataProvider('donationFlowRoutes')]
  public function testDonationFlowBelongsToSupport(string $route): void {
    $this->assertTrue(DomainRoutePolicy::isDonationFlowRouteName($route));
    $this->assertFalse(DomainRoutePolicy::isCentralTransactionRouteName($route));
    $this->assertTrue(DomainRoutePolicy::isTransactionalSeoRouteName($route));
  }

  /**
   * Provides donation flow route names.
   */
  public static function donationFlowRoutes(): array {
    return [
      'donation entry' => ['aculta_donation.donation.default'],
      'donation quick' => ['aculta_donation.donation.quick'],
    ];
  }

  /**
   * Checks donation and Commerce checkout purposes.
   */
  public function testDonationStepBelongsToMainAndValueChoiceToSupport(): void {
    $this->assertSame('support', DomainRoutePolicy::purposeForDonationRoute('aculta_donation.donation.default'));
    $this->assertSame('support', DomainRoutePolicy::purposeForDonationRoute('aculta_donation.donation.quick'));
    $this->assertSame('main', DomainRoutePolicy::purposeForDonationRoute('aculta_donation.donation_controller_formPage'));
  }

  /**
   * Checks that non-transactional routes remain outside transaction families.
   */
  #[DataProvider('publicRoutes')]
  public function testPublicRoutesAreNotTransactional(string $route): void {
    $this->assertFalse(DomainRoutePolicy::isCentralTransactionRouteName($route));
    $this->assertFalse(DomainRoutePolicy::isTransactionalSeoRouteName($route));
  }

  /**
   * Provides public route names.
   */
  public static function publicRoutes(): array {
    return [
      'wiki home' => ['aculta_portal.wiki_home'],
      'courses' => ['aculta_portal.courses_home'],
      'login' => ['user.login'],
      'node canonical' => ['entity.node.canonical'],
    ];
  }

  /**
   * Checks SEO classification for Commerce orders.
   */
  public function testOrderEntitiesAreSeoTransactionalButNotCentral(): void {
    $this->assertTrue(DomainRoutePolicy::isTransactionalSeoRouteName('entity.commerce_order.canonical'));
    $this->assertFalse(DomainRoutePolicy::isCentralTransactionRouteName('entity.commerce_order.canonical'));
  }

  /**
   * Checks that only explicitly shared purposes are allowed.
   */
  public function testOnlyExplicitlySharedPurposesAreAllowed(): void {
    $route = new Route('/calendario', [], [], [
      '_aculta_domain_purpose' => 'eventos',
      '_aculta_domain_purposes' => ['eventos', 'main'],
    ]);
    self::assertTrue(DomainRoutePolicy::allowsSharedPurpose($route, 'eventos', 'main'));
    self::assertTrue(DomainRoutePolicy::allowsSharedPurpose($route, 'eventos', 'eventos'));
    self::assertFalse(DomainRoutePolicy::allowsSharedPurpose($route, 'eventos', 'forum'));

    $adminRoute = new Route('/admin/example', [], [], [
      '_admin_route' => TRUE,
      '_aculta_domain_purpose' => 'eventos',
      '_aculta_domain_purposes' => ['eventos', 'main'],
    ]);
    self::assertFalse(DomainRoutePolicy::allowsSharedPurpose($adminRoute, 'eventos', 'main'));
  }

}
