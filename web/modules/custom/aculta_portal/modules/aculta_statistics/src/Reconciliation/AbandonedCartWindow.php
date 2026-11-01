<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Reconciliation;

/**
 * Janela de alteração que define um carrinho abandonado (24 horas sem finalizar).
 *
 * No cron de um dia D, os carrinhos alterados entre o início de D-2 e o início de D-1
 * estão sem alteração há pelo menos 24 horas. Cada carrinho entra numa única janela
 * diária, então é contado uma vez, desde que o cron rode todo dia.
 */
final class AbandonedCartWindow {

  private const DAY_SECONDS = 86400;

  /**
   * Limites de `changed` (inclusivos) para os carrinhos abandonados do dia informado.
   *
   * @return array{0: int, 1: int}
   *   Início e fim, em timestamp Unix.
   */
  public static function bounds(int $day): array {
    $from = ($day - 2) * self::DAY_SECONDS;
    $to = ($day - 1) * self::DAY_SECONDS - 1;
    return [$from, $to];
  }

}
