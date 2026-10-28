<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit\Shipping;

use Drupal\aculta_commerce\Shipping\ShippingRate;
use Drupal\aculta_commerce\Shipping\ShippingService;
use Drupal\commerce_price\Price;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(\Drupal\aculta_commerce\Shipping\ShippingRate::class)]
#[Group('aculta_commerce')]
final class ShippingRateTest extends UnitTestCase {

  /**
   * A rate derives a stable ID and keeps its original amount.
   */
  public function testRateIdentityAndAmount(): void {
    $rate = new ShippingRate([
      'shipping_method_id' => '3',
      'service' => new ShippingService('pac', 'PAC'),
      'amount' => new Price('23.04', 'BRL'),
    ]);

    $this->assertSame('3--pac', $rate->getId());
    $this->assertSame('3', $rate->getShippingMethodId());
    $this->assertSame('PAC', $rate->getService()->getLabel());
    $this->assertSame('23.04', $rate->getAmount()->getNumber());
    $this->assertSame('23.04', $rate->getOriginalAmount()->getNumber());
    $this->assertSame('BRL', $rate->getOriginalAmount()->getCurrencyCode());
  }

  /**
   * Missing required properties are rejected.
   */
  public function testMissingAmountRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    new ShippingRate([
      'shipping_method_id' => '3',
      'service' => new ShippingService('pac', 'PAC'),
    ]);
  }

  /**
   * The service must be a ShippingService instance.
   */
  public function testInvalidServiceRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    new ShippingRate([
      'shipping_method_id' => '3',
      'service' => 'pac',
      'amount' => new Price('23.04', 'BRL'),
    ]);
  }

  /**
   * Arbitrary provider data is preserved for the checkout layer.
   */
  public function testProviderData(): void {
    $rate = new ShippingRate([
      'shipping_method_id' => '3',
      'service' => new ShippingService('pac', 'PAC'),
      'amount' => new Price('23.04', 'BRL'),
      'data' => ['delivery_days' => 5],
    ]);

    $this->assertSame(5, $rate->getData('delivery_days'));
    $this->assertSame('fallback', $rate->getData('missing', 'fallback'));
  }

}
