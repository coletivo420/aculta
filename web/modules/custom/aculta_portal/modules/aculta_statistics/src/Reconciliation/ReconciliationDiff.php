<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Reconciliation;

/**
 * Compara a fonte oficial do Commerce com os contadores do Statistics.
 *
 * Função pura, para teste sem banco. Qualquer diferença em pedidos ou em centavos
 * é reportada (decisão do responsável: tolerância zero na fase de integração).
 */
final class ReconciliationDiff {

  /**
   * Divergências por moeda.
   *
   * @param array<string, array{orders: int, cents: int}> $official
   *   Totais oficiais por moeda ISO.
   * @param array<string, array{orders: int, cents: int}> $counted
   *   Totais contados pelo Statistics por moeda ISO.
   *
   * @return array<string, array{orders: int, cents: int}>
   *   Moedas com diferença, com a diferença (contado menos oficial).
   */
  public static function compare(array $official, array $counted): array {
    $differences = [];
    foreach (array_unique([...array_keys($official), ...array_keys($counted)]) as $currency) {
      $expected = $official[$currency] ?? ['orders' => 0, 'cents' => 0];
      $actual = $counted[$currency] ?? ['orders' => 0, 'cents' => 0];
      $orders = $actual['orders'] - $expected['orders'];
      $cents = $actual['cents'] - $expected['cents'];
      if ($orders !== 0 || $cents !== 0) {
        $differences[$currency] = ['orders' => $orders, 'cents' => $cents];
      }
    }
    ksort($differences);
    return $differences;
  }

}
