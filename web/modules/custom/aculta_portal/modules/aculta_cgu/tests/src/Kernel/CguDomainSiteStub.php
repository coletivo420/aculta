<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Kernel;

use Drupal\aculta_portal\Domain\DomainSiteManager;

/**
 * Fábrica do stub de DomainSiteManager para Kernel do CGU.
 *
 * Cria a instância sem construtor: o controller Marley só a recebe como dependência,
 * e as decisões de denúncia testadas não chamam o domínio.
 */
final class CguDomainSiteStub {

  /**
   * Cria a instância de stub do DomainSiteManager.
   */
  public static function create(): DomainSiteManager {
    return (new \ReflectionClass(DomainSiteManager::class))->newInstanceWithoutConstructor();
  }

}
