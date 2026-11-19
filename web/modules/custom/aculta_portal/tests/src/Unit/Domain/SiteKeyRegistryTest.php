<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Domain;

use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\Domain\SiteKeyRegistry;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the typed site-key registry preserves existing domain mappings.
 */
#[Group('aculta_portal')]
final class SiteKeyRegistryTest extends UnitTestCase {

  /**
   * Verifies that the typed keys preserve the existing Domain mapping.
   */
  public function testKeysAndDomainIdsRemainUnchanged(): void {
    $registry = new SiteKeyRegistry();
    $expected = [
      'main', 'account', 'support', 'magazine', 'wiki',
      'shop', 'courses', 'forum', 'links', 'eventos',
    ];

    $this->assertSame($expected, array_map(static fn (SiteKey $key): string => $key->value, $registry->all()));
    foreach ($registry->all() as $siteKey) {
      $domainId = $registry->domainId($siteKey);
      $this->assertSame(DomainSurfaceMap::DOMAIN_IDS[$siteKey->value], $domainId);
      $this->assertSame($siteKey, $registry->forDomainId($domainId));
    }
  }

  /**
   * Verifies configured front paths and closed paths are unchanged.
   */
  public function testPathsAndClosedLegacyPathsRemainUnchanged(): void {
    $registry = new SiteKeyRegistry();
    foreach ($registry->all() as $siteKey) {
      $this->assertSame(DomainSurfaceMap::FRONT_PATHS[$siteKey->value] ?? NULL, $registry->frontPath($siteKey));
      $this->assertSame(DomainSurfaceMap::NO_DA_RAIZ[$siteKey->value] ?? NULL, $registry->rootNodePath($siteKey));
      $this->assertSame(DomainSurfaceMap::CAMINHOS_LEGADOS[$siteKey->value] ?? [], $registry->closedLegacyPaths($siteKey));
    }
  }

  /**
   * Verifies invalid keys and Domain IDs return no resolution.
   */
  public function testInvalidValuesAndDomainIdsFailClosed(): void {
    $registry = new SiteKeyRegistry();

    $this->assertNull($registry->fromValue('unknown'));
    $this->assertNull($registry->forDomainId('unknown_domain'));
  }

}
