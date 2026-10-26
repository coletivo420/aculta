<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\aculta_commerce_melhorenvio\Address\ShipmentAddressPair;

/**
 * Monta o corpo de POST /api/v2/me/shipment/calculate.
 *
 * Usa a forma `products` da especificação (obrigatória no schema), com um item que representa o pacote inteiro.
 * Dimensões vão em centímetros inteiros, arredondados para cima e no mínimo 1. Peso em quilos, no mínimo 0,01.
 * Não faz HTTP.
 */
final class QuoteRequestBuilder {

  /**
   * Corpo da cotação.
   *
   * @return array<string, mixed>
   *   Payload pronto para o cliente.
   *
   * @throws \InvalidArgumentException
   *   Quando os endereços não servem para cotação.
   */
  public function build(ShipmentAddressPair $pair, QuotePackage $package): array {
    if (!$pair->isValid()) {
      throw new \InvalidArgumentException('Endereços inválidos para cotação: ' . self::describeErrors($pair));
    }
    return [
      'from' => $pair->from->toQuoteArray(),
      'to' => $pair->to->toQuoteArray(),
      'products' => [
        [
          'id' => 'pacote',
          'width' => self::centimeters($package->widthCm),
          'height' => self::centimeters($package->heightCm),
          'length' => self::centimeters($package->lengthCm),
          'weight' => max(0.01, round($package->weightKg, 3)),
          'insurance_value' => max(0.0, round($package->insuranceValue, 2)),
          'quantity' => 1,
        ],
      ],
      'options' => ['receipt' => FALSE, 'own_hand' => FALSE],
    ];
  }

  /**
   * Códigos de erro por lado, para log e exceção: "origem: x; destino: y". Não inclui CEP nem endereço.
   */
  public static function describeErrors(ShipmentAddressPair $pair): string {
    $parts = [];
    foreach (['origin' => 'origem', 'destination' => 'destino'] as $side => $label) {
      $codes = $pair->errors()[$side];
      if ($codes !== []) {
        $parts[] = $label . ': ' . implode(', ', $codes);
      }
    }
    return implode('; ', $parts);
  }

  /**
   * Centímetros inteiros, arredondados para cima, no mínimo 1.
   */
  private static function centimeters(float $value): int {
    return max(1, (int) ceil($value));
  }

}
