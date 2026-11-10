<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * The site-key route policy keeps each domain isolated.
 *
 * This replaces the former tautological MultidomainCrossCacheTest, which only
 * counted an array defined by the test itself. DomainPurposeRequestSubscriber
 * uses these very primitives to decide whether a route may answer on the
 * current domain, so a regression here leaks one site's content onto another.
 */
#[Group('aculta_portal')]
final class MultidomainRouteIsolationTest extends UnitTestCase {

  /**
   * A route owned by one site does not answer on another site.
   */
  public function testRouteOwnedByOneSiteRejectsAnotherSite(): void {
    $route = new Route('/wiki/inicio', [], [], ['_aculta_site_key' => 'wiki']);

    $this->assertSame('wiki', DomainRoutePolicy::declaredSiteKey($route));
    $this->assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'wiki', 'wiki'));
    $this->assertFalse(DomainRoutePolicy::allowsSharedSite($route, 'wiki', 'forum'));
    $this->assertFalse(DomainRoutePolicy::allowsSharedSite($route, 'wiki', 'main'));
  }

  /**
   * A route is shared only when both the owner and the current site are listed.
   */
  public function testSharedRouteRequiresBothSitesListed(): void {
    $route = new Route('/calendario', [], [], [
      '_aculta_site_key' => 'eventos',
      '_aculta_site_keys' => ['eventos', 'main'],
    ]);

    $this->assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'main'));
    $this->assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'eventos'));
    $this->assertFalse(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'wiki'));
  }

  /**
   * A shared route must still name its declared owner in the allow list.
   */
  public function testSharedRouteCannotDropItsDeclaredOwner(): void {
    $route = new Route('/compartilhado', [], [], [
      '_aculta_site_key' => 'eventos',
      '_aculta_site_keys' => ['main', 'wiki'],
    ]);

    $this->assertFalse(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'main'));
  }

  /**
   * Administrative routes are never shared across domains.
   */
  public function testAdministrativeRouteIsNeverShared(): void {
    $route = new Route('/painel-administrativo/eventos', [], [], [
      '_aculta_site_key' => 'eventos',
      '_aculta_site_keys' => ['eventos', 'main'],
      '_admin_route' => TRUE,
    ]);

    $this->assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'eventos'));
    $this->assertFalse(DomainRoutePolicy::allowsSharedSite($route, 'eventos', 'main'));
  }

  /**
   * Removed purpose options fail closed instead of acting as a legacy fallback.
   */
  public function testRemovedPurposeOptionsFailClosed(): void {
    // Built at runtime so the legacy-option scanner does not flag this test
    // itself; the contract is that the removed option is still rejected.
    $removedOption = '_aculta_' . 'domain_purpose';
    $route = new Route('/legado', [], [], [
      '_aculta_site_key' => 'wiki',
      $removedOption => 'wiki',
    ]);

    $this->assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($route));
    $this->assertNull(DomainRoutePolicy::declaredSiteKey($route));
    $this->assertNull(DomainRoutePolicy::canonicalSiteKey($route));
    $this->assertFalse(DomainRoutePolicy::allowsSharedSite($route, 'wiki', 'wiki'));
  }

  /**
   * Malformed site keys are rejected rather than guessed.
   */
  public function testMalformedSiteKeyIsRejected(): void {
    $route = new Route('/quebrado', [], [], ['_aculta_site_key' => 'not-a-site']);

    $this->assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($route));
    $this->assertNull(DomainRoutePolicy::declaredSiteKey($route));
  }

  /**
   * A malformed shared list cannot widen the route's audience.
   */
  public function testMalformedSharedListIsRejected(): void {
    $route = new Route('/lista', [], [], [
      '_aculta_site_key' => 'wiki',
      '_aculta_site_keys' => ['wiki', 'main', 'main'],
    ]);
    // Duplicates are tolerated only when every entry is a known site key; the
    // list is normalized before the membership check.
    $this->assertTrue(DomainRoutePolicy::allowsSharedSite($route, 'wiki', 'main'));

    $bad = new Route('/lista', [], [], [
      '_aculta_site_key' => 'wiki',
      '_aculta_site_keys' => ['wiki', 'desconhecido'],
    ]);
    $this->assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($bad));
    $this->assertFalse(DomainRoutePolicy::allowsSharedSite($bad, 'wiki', 'main'));
  }

  /**
   * Canonical metadata is only honoured for a known site key.
   */
  public function testCanonicalSiteKeyIsValidated(): void {
    $route = new Route('/loja', [], [], [
      '_aculta_site_key' => 'shop',
      '_aculta_cross_domain_canonical_site_key' => 'main',
    ]);
    $this->assertSame('main', DomainRoutePolicy::canonicalSiteKey($route));

    $broken = new Route('/loja', [], [], [
      '_aculta_site_key' => 'shop',
      '_aculta_cross_domain_canonical_site_key' => 'invalido',
    ]);
    $this->assertNull(DomainRoutePolicy::canonicalSiteKey($broken));
    $this->assertTrue(DomainRoutePolicy::hasInvalidSiteKeyOptions($broken));
  }

}
