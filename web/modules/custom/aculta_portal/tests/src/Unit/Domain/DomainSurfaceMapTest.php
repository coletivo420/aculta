<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Domain;

use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Paridade do mapa único de superfícies com os valores anteriores (Fase 3.1).
 */
#[Group('aculta_portal')]
final class DomainSurfaceMapTest extends UnitTestCase {

  /**
   * Os dez purposes continuam os mesmos.
   */
  public function testPurposesSaoOsDez(): void {
    $this->assertSame(
      ['main', 'account', 'support', 'magazine', 'wiki', 'shop', 'courses', 'forum', 'links', 'eventos'],
      array_keys(DomainSurfaceMap::DOMAIN_IDS),
    );
  }

  /**
   * IDs de Domain preservados.
   */
  public function testDomainIdsPreservados(): void {
    $this->assertSame('aculta_org', DomainSurfaceMap::DOMAIN_IDS['main']);
    $this->assertSame('wiki420_aculta_org', DomainSurfaceMap::DOMAIN_IDS['wiki']);
    $this->assertSame('loja_aculta_org', DomainSurfaceMap::DOMAIN_IDS['shop']);
    $this->assertSame('eventos_aculta_org', DomainSurfaceMap::DOMAIN_IDS['eventos']);
  }

  /**
   * Raízes internas preservadas.
   */
  public function testFrontPathsPreservados(): void {
    $this->assertSame([
      'support' => '/apoio',
      'wiki' => '/wiki',
      'courses' => '/cursos',
      'shop' => '/loja',
      'forum' => '/discussoes',
      'links' => '/redes-sociais',
      'account' => '/aculta-studio-dashboard',
      'eventos' => '/eventos',
    ], DomainSurfaceMap::FRONT_PATHS);
  }

  /**
   * Nó da loja e caminho legado do Forum preservados.
   */
  public function testRootNodeAndLegacyPathsArePreserved(): void {
    $this->assertSame(['shop' => '/node/372'], DomainSurfaceMap::NO_DA_RAIZ);
    $this->assertSame(['forum' => ['/forum']], DomainSurfaceMap::CAMINHOS_LEGADOS);
  }

  /**
   * Constantes de purpose usadas no subscriber.
   */
  public function testConstantesDePurpose(): void {
    $this->assertSame('main', DomainSurfaceMap::MAIN);
    $this->assertSame('account', DomainSurfaceMap::ACCOUNT);
  }

}
