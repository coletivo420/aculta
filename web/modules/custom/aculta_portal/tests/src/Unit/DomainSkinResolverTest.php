<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Appearance\DomainSkinResolver;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests domain ID to UI skin mapping and fail-safe default resolution.
 */
#[Group('aculta_portal')]
final class DomainSkinResolverTest extends UnitTestCase {

  /**
   * Checks that the active Domain ID selects its configured skin.
   */
  public function testUsesSkinMappedToCurrentDomainId(): void {
    $config = $this->createMock(Config::class);
    $config->expects($this->once())->method('get')->with('domains.links_aculta_org')->willReturn('ruderalis');
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_portal.visual_skins')->willReturn($config);
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getCurrentDomainId')->willReturn('links_aculta_org');

    self::assertSame('ruderalis', (new DomainSkinResolver($factory, $sites))->resolveCurrent());
  }

  /**
   * Checks invalid and missing Domain mappings use the deterministic default.
   */
  public function testUnknownOrInvalidSkinUsesAcultaDefault(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(static fn (string $key): ?string => $key === 'domains.unknown' ? 'not-a-skin' : NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $resolver = new DomainSkinResolver($factory, $sites);

    self::assertSame(DomainSkinResolver::DEFAULT_SKIN, $resolver->resolveDomainId('unknown'));
    self::assertSame(DomainSkinResolver::DEFAULT_SKIN, $resolver->resolveDomainId('links_aculta_org'));
  }

}
