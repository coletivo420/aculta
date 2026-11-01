<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoAmount;
use Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests safe normalization of remote monetary amounts.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoAmountTest extends UnitTestCase {

  /**
   * Valid provider amounts and their exact Commerce decimal representation.
   *
   * @return array<string, array{mixed, int, string}>
   *   Amounts and normalized values.
   */
  public static function validAmounts(): array {
    return [
      'integer' => [120, 2, '120.00'],
      'decimal' => ['1.20', 2, '1.20'],
      'scientific integer' => ['1.2e2', 2, '120.00'],
      'scientific decimal' => ['1.234e2', 2, '123.40'],
      'zero exponent' => ['0e0', 2, '0.00'],
      'negative zero' => ['-0.00', 2, '0.00'],
      'zero fraction digits' => ['123', 0, '123'],
      'three fraction digits' => ['1.234', 3, '1.234'],
    ];
  }

  /**
   * Normalizes valid decimal and scientific provider formats.
   */
  #[DataProvider('validAmounts')]
  public function testNormalizesValidAmounts(mixed $amount, int $precision, string $expected): void {
    $this->assertSame($expected, MercadoPagoAmount::normalize($amount, $precision));
  }

  /**
   * Uses ISO currency precision for zero-, two-, and three-digit currencies.
   */
  public function testCurrencyPrecisionFollowsIso4217(): void {
    $this->assertSame(0, MercadoPagoAmount::currencyPrecision('JPY'));
    $this->assertSame(2, MercadoPagoAmount::currencyPrecision('BRL'));
    $this->assertSame(3, MercadoPagoAmount::currencyPrecision('BHD'));
  }

  /**
   * Rejects unknown currencies and unsupported precision values safely.
   */
  public function testRejectsUnknownCurrencyAndPrecision(): void {
    try {
      MercadoPagoAmount::currencyPrecision('ACU');
      $this->fail('Unknown currency should have been rejected.');
    }
    catch (MercadoPagoApiException $exception) {
      $this->assertSame('O Mercado Pago informou uma moeda inválida.', $exception->getMessage());
    }

    $this->expectException(MercadoPagoApiException::class);
    MercadoPagoAmount::normalize('1.0', 7);
  }

  /**
   * Rejects malformed, non-cent, and unbounded values without echoing them.
   */
  public function testRejectsUnsafeAmounts(): void {
    foreach (['120.001', '1e999', '10,00', ['10.00']] as $amount) {
      try {
        MercadoPagoAmount::normalize($amount);
        $this->fail('Unsafe amount should have been rejected.');
      }
      catch (MercadoPagoApiException $exception) {
        $this->assertStringNotContainsString((string) (is_array($amount) ? '10.00' : $amount), $exception->getMessage());
      }
    }
  }

}
