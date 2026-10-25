<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\ProviderMessageRegistry;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * ID do provedor guardado por correlação, consumido uma única vez.
 */
#[Group('aculta_mailer')]
final class ProviderMessageRegistryTest extends UnitTestCase {

  /**
   * O ID é entregue uma vez; depois some, e a correlação sem ID devolve NULL.
   */
  public function testTakeReturnsIdOnceAndNullAfterwards(): void {
    $registry = new ProviderMessageRegistry();
    $registry->remember('corr-1', '201798300811.5787683@relay.example.invalid');
    $this->assertSame('201798300811.5787683@relay.example.invalid', $registry->take('corr-1'));
    $this->assertNull($registry->take('corr-1'));
    $this->assertNull($registry->take('corr-sem-id'));
  }

  /**
   * Correlações diferentes não se misturam.
   */
  public function testIdsAreKeptPerCorrelation(): void {
    $registry = new ProviderMessageRegistry();
    $registry->remember('corr-a', 'id-a');
    $registry->remember('corr-b', 'id-b');
    $this->assertSame('id-b', $registry->take('corr-b'));
    $this->assertSame('id-a', $registry->take('corr-a'));
  }

}
