<?php

namespace Drupal\aculta_pagamentos_mercadopago\Plugin\Commerce\PaymentGateway;

/**
 * Static map of Mercado Pago payment types and methods by country.
 *
 * Based on official Mercado Pago documentation. Hierarchy: country → payment
 * type → payment methods. Used to build the gateway configuration form and
 * excluded_payment_methods / excluded_payment_types in the Checkout Preference.
 *
 * @see https://www.mercadopago.com/developers/en/docs/checkout-pro/additional-settings/payment-methods
 */
final class MercadoPagoPaymentMethodsMap {

  /**
   * Payment type IDs that are always enabled and must not appear in the form.
   *
   * Account money and wallet (digital_currency) are always available in
   * Checkout Pro and cannot be disabled.
   *
   * @var string[]
   */
  public static $alwaysEnabledPaymentTypes = [
    'account_money',
    'digital_currency',
  ];

  /**
   * Payment type IDs (API) and labels. Order matches documentation.
   *
   * @var string[]
   */
  public static $paymentTypes = [
    'account_money' => 'Account money',
    'atm' => 'ATM',
    'bank_transfer' => 'Bank transfer',
    'credit_card' => 'Credit',
    'debit_card' => 'Debit',
    'prepaid_card' => 'Prepaid card',
    'ticket' => 'Payment slip / Cash',
    'digital_currency' => 'Digital currency',
  ];

  /**
   * Payment methods by country and type.
   *
   * Keys: country code (AR, BR, CL, CO, MX, PE, UY) → type id → [ method_id => label ].
   * Method IDs match the Mercado Pago API (excluded_payment_methods).
   *
   * @var array<string, array<string, array<string, string>>>
   */
  public static $methodsByCountryAndType = [
    'AR' => [
      'account_money' => ['account_money' => 'Mercado Pago'],
      'credit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'amex' => 'American Express',
        'diners' => 'Diners Club',
        'naranja' => 'Naranja',
        'cabal' => 'Cabal',
        'tarjeta_shopping' => 'Tarjeta Shopping',
        'cencosud' => 'Cencosud',
        'argencard' => 'Argencard',
        'cmr' => 'CMR',
      ],
      'debit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'maestro' => 'Maestro',
        'cabal' => 'Cabal',
      ],
      'prepaid_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'ticket' => [
        'rapipago' => 'Rapipago',
        'pagofacil' => 'Pago Fácil',
      ],
      'digital_currency' => ['digital_currency' => 'Mercado Pago'],
    ],
    'BR' => [
      'account_money' => ['account_money' => 'Mercado Pago'],
      'bank_transfer' => ['pix' => 'Pix'],
      'credit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'amex' => 'American Express',
        'hipercard' => 'Hipercard',
        'elo' => 'Elo',
      ],
      'debit_card' => [
        'caixa' => 'CAIXA virtual debit card',
        'elo' => 'Elo',
      ],
      'prepaid_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'ticket' => ['bolbradesco' => 'Boleto'],
      'digital_currency' => ['digital_currency' => 'Mercado Pago'],
    ],
    'CL' => [
      'account_money' => ['account_money' => 'Mercado Pago'],
      'credit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'amex' => 'American Express',
        'magnum' => 'Magna',
        'presto' => 'Presto',
      ],
      'debit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'redcompra' => 'RedCompra',
      ],
      'prepaid_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'digital_currency' => ['digital_currency' => 'Mercado Pago'],
    ],
    'CO' => [
      'account_money' => ['account_money' => 'Mercado Pago'],
      'bank_transfer' => ['pse' => 'PSE'],
      'credit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'amex' => 'American Express',
        'diners' => 'Diners Club',
        'codensa' => 'Codensa',
      ],
      'debit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'prepaid_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'ticket' => ['efecty' => 'Efecty'],
    ],
    'MX' => [
      'account_money' => ['account_money' => 'Mercado Pago'],
      'atm' => [
        'bbva_bancomer' => 'BBVA Bancomer',
        'citibanamex' => 'Citibanamex',
        'santander' => 'Santander',
      ],
      'bank_transfer' => ['clabe' => 'CLABE'],
      'credit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'amex' => 'American Express',
      ],
      'debit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'prepaid_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'mercadopago' => 'Mercado Pago',
      ],
      'ticket' => [
        'oxxo' => 'Oxxo',
        'paycash' => 'PayCash',
      ],
      'digital_currency' => ['digital_currency' => 'Mercado Pago'],
    ],
    'PE' => [
      'account_money' => ['account_money' => 'Mercado Pago'],
      'atm' => ['pagoefectivo' => 'PagoEfectivo'],
      'bank_transfer' => ['yape' => 'Yape'],
      'credit_card' => [
        'visa' => 'Visa',
        'diners' => 'Diners Club',
        'master' => 'Mastercard',
        'amex' => 'American Express',
      ],
      'debit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'prepaid_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
    ],
    'UY' => [
      'account_money' => ['account_money' => 'Mercado Pago'],
      'credit_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
        'oca' => 'Oca',
        'amex' => 'American Express',
        'creditel' => 'Creditel',
        'lider' => 'Líder',
      ],
      'debit_card' => [
        'visa' => 'Visa',
      ],
      'prepaid_card' => [
        'visa' => 'Visa',
        'master' => 'Mastercard',
      ],
      'ticket' => [
        'abitab' => 'Abitab',
        'redpagos' => 'Red Pagos',
      ],
    ],
  ];

  /**
   * Returns payment types available for a country (types that have at least one method).
   *
   * @param string $country_code
   *   Country code (AR, BR, CL, CO, MX, PE, UY).
   *
   * @return string[]
   *   Associative array type_id => label.
   */
  public static function getPaymentTypesForCountry($country_code) {
    $types = [];
    $available = self::$methodsByCountryAndType[$country_code] ?? [];
    foreach (array_keys($available) as $type_id) {
      if (isset(self::$paymentTypes[$type_id])) {
        $types[$type_id] = self::$paymentTypes[$type_id];
      }
    }
    return $types;
  }

  /**
   * Returns configurable payment types for a country (excludes always-enabled).
   *
   * Used for the gateway form: account_money and digital_currency (wallet) are
   * always enabled by Mercado Pago and are not shown as options.
   *
   * @param string $country_code
   *   Country code (AR, BR, CL, CO, MX, PE, UY).
   *
   * @return string[]
   *   Associative array type_id => label.
   */
  public static function getConfigurablePaymentTypesForCountry($country_code) {
    $all = self::getPaymentTypesForCountry($country_code);
    return array_diff_key($all, array_flip(self::$alwaysEnabledPaymentTypes));
  }

  /**
   * Returns payment methods for a country and one or more types.
   *
   * @param string $country_code
   *   Country code.
   * @param string[] $type_ids
   *   Payment type IDs to include.
   *
   * @return array<string, array<string, string>>
   *   Keys: type_id. Values: [ method_id => label ].
   */
  public static function getMethodsForCountryAndTypes(string $country_code, array $type_ids): array {
    $by_country = self::$methodsByCountryAndType[$country_code] ?? [];
    $result = [];
    foreach ($type_ids as $type_id) {
      if (isset($by_country[$type_id])) {
        $result[$type_id] = $by_country[$type_id];
      }
    }
    return $result;
  }

  /**
   * Returns all payment method IDs for a country (for exclusion list).
   *
   * @param string $country_code
   *   Country code.
   *
   * @return string[]
   */
  public static function getAllMethodIdsForCountry(string $country_code): array {
    $by_country = self::$methodsByCountryAndType[$country_code] ?? [];
    $ids = [];
    foreach ($by_country as $methods) {
      foreach (array_keys($methods) as $id) {
        $ids[(string) $id] = TRUE;
      }
    }
    return array_keys($ids);
  }

}
