<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Connector\SearchConsole;

/**
 * Compara dois períodos de métricas do Search Console.
 *
 * Adaptado de `SearchConsoleClient::calculateChange()` e `formatPercentageChange()`
 * (drupal/analyze_search_console, GPL-2.0-or-later, commit 6f054ac). Diferenças:
 *   - devolve números, sem texto formatado: a apresentação é do ACULTA420;
 *   - variação a partir de zero retorna NULL em vez de "+100%", que é uma
 *     convenção de exibição e não uma medida real;
 *   - CTR é diferença em pontos percentuais, não variação relativa.
 */
final class SearchMetricsDelta {

  /**
   * Compara o período atual com o anterior.
   *
   * @param array{clicks: float|int, impressions: float|int, ctr: float, position: float} $current
   *   Métricas do período atual (CTR em fração, de 0 a 1).
   * @param array{clicks: float|int, impressions: float|int, ctr: float, position: float}|null $previous
   *   Métricas do período anterior, ou NULL quando não há comparação.
   *
   * @return array{clicks: array{percent: float|null}, impressions: array{percent: float|null}, ctr: array{points: float}, position: array{value: float}}|null
   *   Variações, ou NULL quando não há período anterior.
   */
  public static function between(array $current, ?array $previous): ?array {
    if ($previous === NULL) {
      return NULL;
    }

    return [
      'clicks' => ['percent' => self::percent((float) $current['clicks'], (float) $previous['clicks'])],
      'impressions' => ['percent' => self::percent((float) $current['impressions'], (float) $previous['impressions'])],
      'ctr' => ['points' => ((float) $current['ctr'] - (float) $previous['ctr']) * 100],
      'position' => ['value' => (float) $current['position'] - (float) $previous['position']],
    ];
  }

  /**
   * Variação percentual relativa ao valor anterior.
   *
   * @return float|null
   *   Variação em porcentagem, `0.0` quando ambos são zero, ou NULL quando o
   *   anterior é zero e o atual não (variação indefinida).
   */
  public static function percent(float $current, float $previous): ?float {
    if ($previous == 0.0) {
      return $current == 0.0 ? 0.0 : NULL;
    }

    return (($current - $previous) / $previous) * 100;
  }

}
