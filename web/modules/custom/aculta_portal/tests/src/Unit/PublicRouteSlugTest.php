<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Routing\PublicRouteSlug;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('aculta_portal')]
final class PublicRouteSlugTest extends UnitTestCase {

  public function testNormalizesCaseAndOuterWhitespaceWithoutRewritingContent(): void {
    $this->assertSame('piradopirata', PublicRouteSlug::normalize('  PiradoPirata  '));
    $this->assertSame('ação-com-caixa', PublicRouteSlug::normalize('ação-com-Caixa'));
    $this->assertSame('nome_inalterado', PublicRouteSlug::normalize('Nome_inalterado'));
  }

}
