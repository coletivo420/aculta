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
   * Valor monetário como texto decimal com ponto e duas casas, sem passar por float.
   *
   * Aceita apenas valores não negativos com até duas casas. Um valor com mais precisão (por exemplo, 10.005) é
   * recusado em vez de arredondado em silêncio: não se inventa centavo que a API não informou.
   */
  private static function decimal(mixed $value): ?string {
    if (is_int($value) || is_float($value)) {
      // Com oito casas o float é reproduzido sem ruído binário; zeros finais são removidos.
      $value = rtrim(rtrim(sprintf('%.8F', (float) $value), '0'), '.');
    }
    if (!is_string($value) || preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
      return NULL;
    }
    [$integer, $fraction] = explode('.', $value) + [1 => ''];
    return $integer . '.' . str_pad($fraction, 2, '0');
  }

}
