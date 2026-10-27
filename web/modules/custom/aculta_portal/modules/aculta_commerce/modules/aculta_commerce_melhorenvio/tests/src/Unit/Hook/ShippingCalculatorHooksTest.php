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

  /**
   * Creates the hooks with a configured simulator and session quote.
   */
  private function hooks(?string $reason, ?array $stored = NULL, ?string $lastPostalCode = NULL): ShippingCalculatorHooks {
    $simulator = $this->createMock(ShippingSimulator::class);
    $simulator->method('availabilityReason')->willReturn($reason);
    $store = $this->createMock(ShippingQuoteStore::class);
    $store->method('load')->willReturn($stored);
    $store->method('loadLastPostalCode')->willReturn($lastPostalCode);
    return new ShippingCalculatorHooks($simulator, $store);
  }

  /**
   * Without a quote, the component waits for a postal code.
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
   * Without a package, the component shows a generic unavailable message.
   */
  public function testUnavailableWithVisitorMessage(): void {
    $props = $this->hooks(ShippingSimulation::REASON_NO_PACKAGE)->props('frete-produto');
    $this->assertSame('unavailable', $props['state']);
    $this->assertSame('Cálculo de frete indisponível no momento.', $props['message']);
  }

  /**
   * A saved account CEP prefills the component without a previous quote.
   */
  public function testLastPostalCodePrefillsTheCalculator(): void {
    $props = $this->hooks(NULL, NULL, '74003010')->props('frete-produto');
    $this->assertSame('74003010', $props['postal_code']);
    $this->assertSame('idle', $props['state']);
  }

  /**
   * The saved session quote appears on product and cart without recalculation.
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
   * The full cart form gets the component without a separate submit handler.
   */
  public function testCartFormGetsComponentAndRefreshSubmit(): void {
    $form = [];
    $this->hooks(ShippingSimulation::REASON_NO_PACKAGE)->alterCartForm($form);
    $this->assertSame('aculta_shipping_calculator', $form['aculta_shipping_calculator']['#theme']);
    $this->assertSame(
      ['aculta_commerce_melhorenvio/shipping-calculator'],
      $form['aculta_shipping_calculator']['#attached']['library'],
    );
    $this->assertSame(['user', 'session'], $form['aculta_shipping_calculator']['#cache']['contexts']);
    $this->assertSame(0, $form['aculta_shipping_calculator']['#cache']['max-age']);
    $this->assertSame('unavailable', $form['aculta_shipping_calculator']['#state']);
    $this->assertArrayNotHasKey('#submit', $form);
  }

}
