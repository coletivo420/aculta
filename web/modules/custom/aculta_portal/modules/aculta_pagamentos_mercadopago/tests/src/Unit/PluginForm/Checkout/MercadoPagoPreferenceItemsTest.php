<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\PluginForm\Checkout;

use Drupal\aculta_pagamentos_mercadopago\PluginForm\Checkout\MercadoPagoPreferenceItems;
use Drupal\commerce_price\Price;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies preference amounts include Commerce adjustments exactly.
 */
#[CoversClass(MercadoPagoPreferenceItems::class)]
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoPreferenceItemsTest extends UnitTestCase {

  /**
   * Allocates an order-level promotion without changing the total.
   */
  public function testOrderLevelDiscountIsAppliedToPreferenceItems(): void {
    $items = MercadoPagoPreferenceItems::build([
      ['item' => ['title' => 'Camisa', 'quantity' => 2, 'unit_price' => 10.0], 'amount' => new Price('20.00', 'BRL')],
      ['item' => ['title' => 'Caneca', 'quantity' => 1, 'unit_price' => 8.0], 'amount' => new Price('8.00', 'BRL')],
    ], new Price('23.00', 'BRL'));

    self::assertSame('Camisa (2 un.)', $items[0]['title']);
    self::assertSame(1, $items[0]['quantity']);
    self::assertSame(15.0, $items[0]['unit_price']);
    self::assertSame(8.0, $items[1]['unit_price']);
    self::assertSame(23.0, array_sum(array_map(static fn (array $item): float => $item['unit_price'] * $item['quantity'], $items)));
  }

  /**
   * Spreads a discount over multiple merchandise lines when necessary.
   */
  public function testOrderLevelDiscountConsumesMultipleLinesWithoutNegativePrices(): void {
    $items = MercadoPagoPreferenceItems::build([
      ['item' => ['title' => 'Caderno', 'quantity' => 1], 'amount' => new Price('5.00', 'BRL')],
      ['item' => ['title' => 'Caneta', 'quantity' => 1], 'amount' => new Price('4.00', 'BRL')],
    ], new Price('1.00', 'BRL'));

    self::assertCount(1, $items);
    self::assertSame('Caneta', $items[0]['title']);
    self::assertSame(1.0, $items[0]['unit_price']);
    self::assertGreaterThan(0, $items[0]['unit_price']);
  }

  /**
   * Adds a positive order-level charge as a visible preference item.
   */
  public function testPositiveOrderAdjustmentIsIncluded(): void {
    $items = MercadoPagoPreferenceItems::build([
      ['item' => ['title' => 'Livro', 'quantity' => 1], 'amount' => new Price('10.00', 'BRL')],
    ], new Price('12.50', 'BRL'));

    self::assertSame('Outros ajustes do pedido', $items[1]['title']);
    self::assertSame(2.5, $items[1]['unit_price']);
    self::assertSame(12.5, array_sum(array_column($items, 'unit_price')));
  }

  /**
   * Rejects a non-positive merchandise target before preference creation.
   */
  public function testRejectsNonPositiveMerchandiseTarget(): void {
    $this->expectException(\InvalidArgumentException::class);
    MercadoPagoPreferenceItems::build([
      ['item' => ['title' => 'Livro', 'quantity' => 1], 'amount' => new Price('10.00', 'BRL')],
    ], new Price('0.00', 'BRL'));
  }

}
