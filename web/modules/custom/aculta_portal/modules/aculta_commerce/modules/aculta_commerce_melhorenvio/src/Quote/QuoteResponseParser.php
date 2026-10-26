<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

/**
 * Lê a resposta da cotação. Serviços com `error` (trecho não atendido, limite de seguro) ficam de fora.
 *
 * O preço usado é `custom_price` (já com os ajustes da conta), com `price` como reserva. O prazo usado é
 * `custom_delivery_time`, com `delivery_time` como reserva.
 */
final class QuoteResponseParser {

  /**
   * Opções disponíveis, na ordem da resposta.
   *
   * @param array<mixed> $response
   *   JSON decodificado da cotação.
   *
   * @return list<\Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption>
   *   Opções disponíveis.
   */
  public function parse(array $response): array {
    $options = [];
    foreach ($response as $item) {
      if (!is_array($item) || isset($item['error']) || !isset($item['id'], $item['name'])) {
        continue;
      }
      $amount = self::decimal($item['custom_price'] ?? $item['price'] ?? NULL);
      if ($amount === NULL) {
        continue;
      }
      $days = $item['custom_delivery_time'] ?? $item['delivery_time'] ?? NULL;
      $options[] = new QuoteOption(
        serviceId: (string) $item['id'],
        name: trim((string) $item['name']),
        company: trim((string) ($item['company']['name'] ?? '')),
        amount: $amount,
        deliveryDays: is_numeric($days) ? (int) $days : NULL,
      );
    }
    return $options;
  }

  /**
   * Decimal com ponto e duas casas, ou NULL quando o valor não é um número não negativo.
   */
  private static function decimal(mixed $value): ?string {
    if (!is_numeric($value) || (float) $value < 0) {
      return NULL;
    }
    return number_format((float) $value, 2, '.', '');
  }

}
