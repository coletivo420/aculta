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
    $this->assertSame('support', DomainRoutePolicy::siteKeyForDonationRoute('aculta_donation.donation.default'));
    $this->assertSame('support', DomainRoutePolicy::siteKeyForDonationRoute('aculta_donation.donation.quick'));
    $this->assertSame('main', DomainRoutePolicy::siteKeyForDonationRoute('aculta_donation.donation_controller_formPage'));
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
      '_aculta_site_key' => 'eventos',
      '_aculta_site_keys' => ['eventos', 'main'],
    ]);
    self::assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'main'));
    self::assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'eventos'));
    self::assertFalse(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'forum'));

    $adminRoute = new Route('/admin/example', [], [], [
      '_admin_route' => TRUE,
      '_aculta_site_key' => 'eventos',
      '_aculta_site_keys' => ['eventos', 'main'],
    ]);
    self::assertFalse(DomainRoutePolicy::allowsSharedSite($adminRoute, 'eventos', 'main'));
  }

  /**
   * Ensures only the canonical route option is accepted.
   */
  public function testRemovedPurposeOptionsFailClosed(): void {
    $legacyPurposeOption = implode('', ['_aculta_domain_', 'purpose']);
    $legacyPurposeListOption = implode('', ['_aculta_domain_', 'purposes']);
    $legacy = new Route('/legacy', [], [], [$legacyPurposeOption => 'links']);
    self::assertNull(DomainRoutePolicy::declaredSiteKey($legacy));
    self::assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($legacy));

    $legacyShared = new Route('/legacy-shared', [], [], [
      $legacyPurposeListOption => ['eventos', 'main'],
    ]);
    self::assertFalse(DomainRoutePolicy::allowsSharedSite($legacyShared, 'eventos', 'main'));
    self::assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($legacyShared));

    $current = new Route('/current', [], [], ['_aculta_site_key' => 'links']);
    self::assertSame('links', DomainRoutePolicy::declaredSiteKey($current));
    self::assertFalse(DomainRoutePolicy::hasInvalidSiteKeyOptions($current));

    $invalid = new Route('/invalid', [], [], ['_aculta_site_key' => 'unknown']);
    self::assertNull(DomainRoutePolicy::declaredSiteKey($invalid));
    self::assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($invalid));

    $conflict = new Route('/conflict', [], [], [
      '_aculta_site_key' => 'links',
      $legacyPurposeOption => 'main',
    ]);
    self::assertNull(DomainRoutePolicy::declaredSiteKey($conflict));
    self::assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($conflict));
    self::assertFalse(DomainRoutePolicy::allowsSharedSite($conflict, 'links', 'links'));
  }

  /**
   * Ensures canonical and shared site options support the new names.
   */
  public function testCanonicalAndSharedSiteOptionsUseNewNames(): void {
    $legacyCanonicalOption = implode('', ['_aculta_cross_domain_canonical_', 'purpose']);
    $route = new Route('/shared', [], [], [
      '_aculta_site_key' => 'eventos',
      '_aculta_site_keys' => ['eventos', 'main'],
      '_aculta_cross_domain_canonical_site_key' => 'main',
    ]);
    self::assertSame('main', DomainRoutePolicy::canonicalSiteKey($route));
    self::assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'main'));

    $route->setOption($legacyCanonicalOption, 'eventos');
    self::assertNull(DomainRoutePolicy::canonicalSiteKey($route));
    self::assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($route));
  }

}
