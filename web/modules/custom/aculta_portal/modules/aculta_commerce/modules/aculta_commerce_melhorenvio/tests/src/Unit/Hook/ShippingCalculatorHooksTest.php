<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Hook;

use Drupal\aculta_commerce_melhorenvio\Hook\ShippingCalculatorHooks;
use Drupal\aculta_commerce_melhorenvio\Quote\ShippingQuoteStore;
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

  private function hooks(?string $reason, ?array $stored = NULL): ShippingCalculatorHooks {
    $simulator = $this->createMock(ShippingSimulator::class);
    $simulator->method('availabilityReason')->willReturn($reason);
    $store = $this->createMock(ShippingQuoteStore::class);
    $store->method('load')->willReturn($stored);
    return new ShippingCalculatorHooks($simulator, $store);
  }

  /**
   * Sem motivo de indisponibilidade e sem cálculo guardado, o componente aguarda o CEP.
   */
  public function testIdleWhenAvailable(): void {
    $props = $this->hooks(NULL)->props('frete-produto');
    $this->assertSame([
      'id' => 'frete-produto',
      'endpoint' => '/frete/simular',
      'help_url' => 'https://buscacepinter.correios.com.br/app/endereco/index.php',
      'state' => 'idle',
      'message' => '',
      'options' => [],
      'postal_code' => '',
    ], $props);
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
   * Cálculo já feito na sessão aparece no produto e no carrinho, com o CEP, sem recalcular.
   */
  public function testStoredQuoteCarriesToCart(): void {
    $stored = [
      'postal_code' => '01310100',
      'at' => 1_000_000,
      'result' => [
        'state' => 'available',
        'reason' => NULL,
        'message' => '',
        'options' => [['name' => 'PAC', 'company' => 'Correios', 'price' => 'R$ 37,79', 'days' => 9]],
      ],
    ];
    $props = $this->hooks(ShippingSimulation::REASON_NO_PACKAGE, $stored)->props('frete-carrinho-lateral');
    $this->assertSame('available', $props['state']);
    $this->assertSame('01310100', $props['postal_code']);
    $this->assertSame('R$ 37,79', $props['options'][0]['price']);
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

  /**
   * Carrinho completo ganha o componente com o estado atual e o recálculo no submit.
   */
  public function testCartFormGetsComponentAndRefreshSubmit(): void {
    $form = [];
    $this->hooks(ShippingSimulation::REASON_NO_PACKAGE)->alterCartForm($form);
    $this->assertSame('component', $form['aculta_shipping_calculator']['#type']);
    $this->assertSame('aculta420:shipping-calculator', $form['aculta_shipping_calculator']['#component']);
    $this->assertSame('unavailable', $form['aculta_shipping_calculator']['#props']['state']);
    $this->assertArrayNotHasKey('#submit', $form);
  }

}
