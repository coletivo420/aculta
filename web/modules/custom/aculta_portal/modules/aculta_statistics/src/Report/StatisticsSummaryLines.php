<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Report;

use Drupal\aculta_mailer\Report\StatisticsLinesProviderInterface;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;

/**
 * Indicadores da semana para o resumo do administrador, como linhas de texto.
 *
 * Soma os agregados diários do intervalo. Valores em centavos aparecem por moeda, em formato
 * brasileiro (1.234,56). Só métricas agregadas; nenhum dado de pessoa.
 */
final class StatisticsSummaryLines implements StatisticsLinesProviderInterface {

  /**
   * Indicadores com contagem total (sem recorte de moeda).
   */
  private const COUNTS = [
    'commerce.orders.paid' => 'Pedidos pagos',
    'commerce.coupons.orders' => 'Pedidos com cupom',
    'commerce.carts.abandoned' => 'Carrinhos abandonados em 24 horas',
    'lms.enrollments.created' => 'Matrículas criadas',
    'lms.courses.completed' => 'Conclusões de curso',
    'lms.certificates.issued' => 'Certificados emitidos',
    'wiki.entries.read' => 'Leituras de verbetes da Wiki',
  ];

  /**
   * Indicadores em centavos, por moeda.
   */
  private const MONEY = [
    'commerce.revenue.gross' => 'Receita bruta',
    'commerce.refunds.total' => 'Reembolsos',
    'commerce.discounts.total' => 'Descontos de promoção',
  ];

  private const DAY_SECONDS = 86400;

  public function __construct(
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function lines(int $from, int $to): array {
    $firstDay = intdiv($from, self::DAY_SECONDS);
    $lastDay = intdiv(max($from, $to - 1), self::DAY_SECONDS);
    $lines = [];

    foreach (self::COUNTS as $metric => $label) {
      $lines[] = $label . ': ' . $this->sum($metric, $firstDay, $lastDay);
    }
    foreach (self::MONEY as $metric => $label) {
      foreach ($this->currencies($metric, $firstDay, $lastDay) as $currency) {
        $cents = $this->sum($metric, $firstDay, $lastDay, $currency);
        $lines[] = $label . ' (' . $currency . '): ' . $this->money($cents);
      }
    }
    return $lines;
  }

  /**
   * Total de uma métrica no intervalo, opcionalmente num recorte (moeda).
   */
  private function sum(string $metric, int $firstDay, int $lastDay, ?string $dimension = NULL): int {
    return $this->storage->total($metric, $firstDay, $lastDay, $dimension);
  }

  /**
   * Moedas com linha registrada na métrica no intervalo.
   *
   * @return string[]
   *   Códigos ISO, ordenados.
   */
  private function currencies(string $metric, int $firstDay, int $lastDay): array {
    $found = [];
    for ($day = $firstDay; $day <= $lastDay; $day++) {
      foreach ($this->storage->dimensions($metric, $day) as $dimension) {
        $found[$dimension] = TRUE;
      }
    }
    $currencies = array_keys($found);
    sort($currencies);
    return $currencies;
  }

  /**
   * Centavos formatados em moeda brasileira, sem o código da moeda.
   */
  private function money(int $cents): string {
    return number_format($cents / 100, 2, ',', '.');
  }

}
