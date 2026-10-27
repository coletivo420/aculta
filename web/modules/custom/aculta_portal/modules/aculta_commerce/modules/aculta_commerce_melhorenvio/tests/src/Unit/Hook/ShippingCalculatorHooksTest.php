<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Hook;

use Drupal\aculta_commerce_melhorenvio\Hook\ShippingCalculatorHooks;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulation;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingSimulator;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Props do simulador na página do produto e tag de cache da origem.
 */
#[CoversClass(ShippingCalculatorHooks::class)]
#[Group('aculta_commerce_melhorenvio')]
final class ShippingCalculatorHooksTest extends UnitTestCase {

  private function hooks(?string $reason): ShippingCalculatorHooks {
    $simulator = $this->createMock(ShippingSimulator::class);
    $simulator->method('availabilityReason')->willReturn($reason);
    return new ShippingCalculatorHooks($simulator);
  }

  /**
   * Sem motivo de indisponibilidade, o componente aguarda o CEP.
   */
  public function testIdleWhenAvailable(): void {
    $props = $this->hooks(NULL)->props('frete-produto');
    $this->assertSame(['id' => 'frete-produto', 'state' => 'idle', 'message' => ''], $props);
  }

  /**
   * Sem pacote real (estado atual do cadastro), o componente já nasce indisponível, com a frase genérica.
   */
  public function testUnavailableWithVisitorMessage(): void {
    $props = $this->hooks(ShippingSimulation::REASON_NO_PACKAGE)->props('frete-produto');
    $this->assertSame('unavailable', $props['state']);
    $this->assertSame('Cálculo de frete indisponível no momento.', $props['message']);
  }

  /**
   * A tag de configuração entra no cache só do produto do bundle da loja.
   */
  public function testCacheTagOnlyForStoreProduct(): void {
    $hooks = $this->hooks(NULL);
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $product = $this->createMock(EntityInterface::class);
    $product->method('getEntityTypeId')->willReturn('commerce_product');
    $product->method('bundle')->willReturn('produto_loja');
    $build = [];
    $hooks->entityViewAlter($build, $product, $display);
    $this->assertSame([ShippingCalculatorHooks::CONFIG_TAG], $build['#cache']['tags']);

    $other = $this->createMock(EntityInterface::class);
    $other->method('getEntityTypeId')->willReturn('node');
    $other->method('bundle')->willReturn('article');
    $untouched = [];
    $hooks->entityViewAlter($untouched, $other, $display);
    $this->assertArrayNotHasKey('#cache', $untouched);
  }

}
