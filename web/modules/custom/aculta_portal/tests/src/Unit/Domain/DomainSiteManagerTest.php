<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Domain;

use Drupal\aculta_portal\Domain\DomainPurposeManagerInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\Domain\SiteKeyRegistry;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the new site-key API delegates to the legacy behavior unchanged.
 */
#[Group('aculta_portal')]
final class DomainSiteManagerTest extends UnitTestCase {

  /**
   * Verifies current-site resolution delegates to the compatibility API.
   */
  public function testCurrentSiteKeyUsesExistingResolverResult(): void {
    $legacy = $this->createMock(DomainPurposeManagerInterface::class);
    $legacy->expects($this->once())
      ->method('getCurrentPurpose')
      ->willReturn('links');
    $manager = new DomainSiteManager($legacy, new SiteKeyRegistry());

    $this->assertSame(SiteKey::Links, $manager->getCurrentSiteKey());
  }

  /**
   * Verifies Domain ID resolution preserves the current result.
   */
  public function testDomainIdResolutionKeepsExistingResolverResult(): void {
    $legacy = $this->createMock(DomainPurposeManagerInterface::class);
    $legacy->expects($this->once())
      ->method('getPurposeForDomainId')
      ->with('forum_aculta_org')
      ->willReturn('forum');
    $manager = new DomainSiteManager($legacy, new SiteKeyRegistry());

    $this->assertSame(SiteKey::Forum, $manager->getSiteKeyForDomainId('forum_aculta_org'));
  }

  /**
   * Verifies temporary site context delegates and returns the same result.
   */
  public function testRunInSiteDelegatesSameKeyAndCallback(): void {
    $callback = static fn (): string => 'result';
    $legacy = $this->createMock(DomainPurposeManagerInterface::class);
    $legacy->expects($this->once())
      ->method('runInPurpose')
      ->with('wiki', $callback)
      ->willReturn('result');
    $manager = new DomainSiteManager($legacy, new SiteKeyRegistry());

    $this->assertSame('result', $manager->runInSite(SiteKey::Wiki, $callback));
  }

  /**
   * Verifies URL helpers retain the legacy arguments and result.
   */
  public function testUrlHelpersDelegateUnchanged(): void {
    $url = (new \ReflectionClass(Url::class))->newInstanceWithoutConstructor();
    $legacy = $this->createMock(DomainPurposeManagerInterface::class);
    $legacy->expects($this->once())
      ->method('routeUrl')
      ->with('links', 'example.route', ['id' => 1])
      ->willReturn($url);
    $legacy->expects($this->once())
      ->method('canonicalRouteUrl')
      ->with('links', 'example.route', ['id' => 1])
      ->willReturn($url);
    $legacy->expects($this->once())
      ->method('canonicalPathUrl')
      ->with('links', '/redes-sociais')
      ->willReturn($url);
    $legacy->expects($this->once())
      ->method('environmentRouteUrl')
      ->with('links', 'example.route', ['id' => 1])
      ->willReturn($url);
    $legacy->expects($this->once())
      ->method('environmentPathUrl')
      ->with('links', '/redes-sociais')
      ->willReturn($url);
    $legacy->expects($this->once())
      ->method('pathUrl')
      ->with('links', '/redes-sociais')
      ->willReturn($url);
    $manager = new DomainSiteManager($legacy, new SiteKeyRegistry());

    $this->assertSame($url, $manager->routeUrl(SiteKey::Links, 'example.route', ['id' => 1]));
    $this->assertSame($url, $manager->canonicalRouteUrl(SiteKey::Links, 'example.route', ['id' => 1]));
    $this->assertSame($url, $manager->canonicalPathUrl(SiteKey::Links, '/redes-sociais'));
    $this->assertSame($url, $manager->environmentRouteUrl(SiteKey::Links, 'example.route', ['id' => 1]));
    $this->assertSame($url, $manager->environmentPathUrl(SiteKey::Links, '/redes-sociais'));
    $this->assertSame($url, $manager->pathUrl(SiteKey::Links, '/redes-sociais'));
  }

}
