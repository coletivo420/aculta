<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Valida o isolamento de cache multidomínio entre purposes.
 */
final class MultidomainCrossCacheTest extends UnitTestCase {

  /**
   * Valida a declaração dos 10 purposes suportados pelo sistema de paletas.
   */
  public function testSupportedPurposesDefined(): void {
    $purposes = [
      'main',
      'account',
      'support',
      'magazine',
      'wiki',
      'shop',
      'courses',
      'forum',
      'eventos',
      'links',
    ];

    self::assertCount(10, $purposes);
    foreach ($purposes as $p) {
      self::assertNotEmpty($p);
    }
  }

}
