<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

use CommerceGuys\Intl\Currency\CurrencyRepository;
use CommerceGuys\Intl\Exception\UnknownCurrencyException;

/**
 * Normalizes Mercado Pago monetary values to the currency minor unit.
 */
final class MercadoPagoAmount {

  /**
   * Returns ISO fraction digits for a currency supported by Commerce.
   *
   * @throws \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException
   *   When the currency code is unknown.
   */
  public static function currencyPrecision(string $currencyCode): int {
    try {
      return (new CurrencyRepository())
        ->get($currencyCode)
        ->getFractionDigits();
    }
    catch (UnknownCurrencyException) {
      throw new MercadoPagoApiException('O Mercado Pago informou uma moeda inválida.');
    }
  }

  /**
   * Returns a decimal amount at the currency precision without float math.
   *
   * Scientific notation is accepted because SDK JSON decoding can turn a
   * numeric amount into a float. Precision below a minor unit is rejected.
   *
   * @throws \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException
   *   When the provider amount is malformed or cannot be represented safely.
   */
  public static function normalize(mixed $value, int $precision = 2): string {
    if ($precision < 0 || $precision > 6) {
      throw new MercadoPagoApiException('A precisão monetária informada é inválida.');
    }
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
      throw new MercadoPagoApiException('O Mercado Pago informou um valor decimal inválido.');
    }
    $raw = (string) $value;
    if (!preg_match('/^([+-]?)(\d+)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', $raw, $parts)) {
      throw new MercadoPagoApiException('O Mercado Pago informou um valor decimal inválido.');
    }

    $integer = $parts[2];
    $fraction = $parts[3] ?? '';
    $exponent = isset($parts[4]) ? (int) $parts[4] : 0;
    $digits = $integer . $fraction;
    if (abs($exponent) > 100 || strlen($digits) + abs($exponent) > 128) {
      throw new MercadoPagoApiException('O Mercado Pago informou um valor decimal inválido.');
    }

    $decimal_position = strlen($integer) + $exponent;
    if ($decimal_position <= 0) {
      $integer = '0';
      $fraction = str_repeat('0', -$decimal_position) . $digits;
    }
    elseif ($decimal_position >= strlen($digits)) {
      $integer = $digits . str_repeat('0', $decimal_position - strlen($digits));
      $fraction = '';
    }
    else {
      $integer = substr($digits, 0, $decimal_position);
      $fraction = substr($digits, $decimal_position);
    }

    $integer = ltrim($integer, '0') ?: '0';
    $fraction = rtrim($fraction, '0');
    if (strlen($fraction) > $precision) {
      throw new MercadoPagoApiException('O Mercado Pago informou um valor com precisão inválida.');
    }

    $normalized = ($parts[1] === '-' && ($integer !== '0' || $fraction !== '') ? '-' : '') . $integer;
    if ($fraction !== '') {
      $normalized .= '.' . $fraction;
    }
    return bcadd($normalized, '0', $precision);
  }

}
