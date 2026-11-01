<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Report;

/**
 * Série diária de uma métrica num período de dias UTC, com total do período.
 *
 * Período escolhido pelo administrador: 7, 14 ou 30 dias (ADR-0005). Valor fora do conjunto volta ao padrão.
 */
final class MetricSeries {

  /**
   * Períodos selecionáveis, em dias.
   */
  public const PERIODS = [7, 14, 30];

  /**
   * Período padrão quando o pedido não traz um valor válido.
   */
  public const DEFAULT_PERIOD = 7;

  /**
   * Normaliza o período pedido para um valor de PERIODS.
   */
  public static function normalizePeriod(mixed $value): int {
    $days = is_scalar($value) ? (int) $value : 0;
    return in_array($days, self::PERIODS, TRUE) ? $days : self::DEFAULT_PERIOD;
  }

  /**
   * Monta a série do período terminando no dia informado.
   *
   * @param int $today
   *   Dia UTC final (número de dias desde 1970-01-01), incluído na série.
   * @param int $days
   *   Tamanho do período, em dias.
   * @param callable(int): int $dailyTotal
   *   Total da métrica num dia.
   *
   * @return array{labels: string[], values: int[], total: int}
   *   Rótulos `dd/mm` em ordem cronológica, valores por dia e o total do período.
   */
  public static function build(int $today, int $days, callable $dailyTotal): array {
    $labels = [];
    $values = [];
    for ($day = $today - $days + 1; $day <= $today; $day++) {
      $labels[] = gmdate('d/m', $day * 86400);
      $values[] = (int) $dailyTotal($day);
    }
    return ['labels' => $labels, 'values' => $values, 'total' => array_sum($values)];
  }

}
