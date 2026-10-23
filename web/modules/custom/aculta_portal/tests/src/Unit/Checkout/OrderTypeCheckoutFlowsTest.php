<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Checkout;

use Drupal\aculta_portal\Checkout\OrderTypeCheckoutFlows;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Leitura do checkout flow nativo do tipo de pedido (P-CART C).
 */
#[Group('aculta_portal')]
final class OrderTypeCheckoutFlowsTest extends UnitTestCase {

  private function flows(bool $hasDefinition, mixed $setting, bool $typeExists = TRUE): OrderTypeCheckoutFlows {
    $type = NULL;
    if ($typeExists) {
      $type = $this->createMock(ConfigEntityInterface::class);
      $type->method('getThirdPartySetting')->with('commerce_checkout', 'checkout_flow')->willReturn($setting);
    }
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($type);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->willReturn($hasDefinition);
    $manager->method('getStorage')->willReturn($storage);
    return new OrderTypeCheckoutFlows($manager);
  }

  public function testDevolveOFluxoAssociadoAoTipo(): void {
    $this->assertSame('curso', $this->flows(TRUE, 'curso')->flowId('curso'));
  }

  public function testTipoSemFluxoUsaOFallbackNativoDoCommerce(): void {
    $this->assertSame('default', $this->flows(TRUE, NULL)->flowId('x'));
    $this->assertSame('default', $this->flows(TRUE, '')->flowId('x'));
  }

  public function testNullQuandoNaoHaTipoOuCommerce(): void {
    $this->assertNull($this->flows(TRUE, 'curso', FALSE)->flowId('x'));
    $this->assertNull($this->flows(FALSE, 'curso')->flowId('x'));
  }

}
