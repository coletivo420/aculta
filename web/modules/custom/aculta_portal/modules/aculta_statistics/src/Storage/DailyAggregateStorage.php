<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Storage;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Armazenamento diário com `merge` atômico por métrica, dimensão e dia.
 *
 * Adaptado de `NodeStatisticsDatabaseStorage` (drupal/statistics, GPL-2.0-or-later,
 * commit fe20f9f). Mudanças: chave genérica (métrica + dimensão + dia UTC) em
 * vez de `node_counter` por nid; tempo injetado por `TimeInterface` em vez de
 * `REQUEST_TIME` da requisição; sem `RequestStack`, IP ou metadados de visita.
 *
 * Schema em `aculta_statistics.install`. Retenção por métrica em `RetentionPolicy`.
 */
final class DailyAggregateStorage implements AggregateStorageInterface {

  /**
   * Tabela de agregados diários.
   */
  public const TABLE = 'aculta_statistics_daily';

  /**
   * Tamanho máximo da dimensão, alinhado a índices de baixa cardinalidade.
   */
  public const MAX_DIMENSION_LENGTH = 128;

  /**
   * Segundos em um dia.
   */
  private const DAY_SECONDS = 86400;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function record(string $metric, string $dimension = '', int $amount = 1): void {
    $this->assertMetric($metric);
    $this->assertDimension($dimension);
    if ($amount < 1) {
      throw new \InvalidArgumentException('A quantidade registrada deve ser positiva.');
    }

    $this->database->merge(self::TABLE)
      ->keys([
        'metric' => $metric,
        'dimension' => $dimension,
        'day' => $this->currentDay(),
      ])
      ->fields([
        'total' => $amount,
        'updated' => $this->time->getRequestTime(),
      ])
      ->expression('total', '[total] + :amount', [':amount' => $amount])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function replaceDay(string $metric, int $day, array $totals): void {
    $this->assertMetric($metric);
    foreach ($totals as $dimension => $total) {
      $this->assertDimension((string) $dimension);
      if ($total < 0) {
        throw new \InvalidArgumentException('O total substituído não pode ser negativo.');
      }
    }

    // Apagar e regravar na mesma transação: leitor concorrente nunca vê o dia pela metade.
    $transaction = $this->database->startTransaction();
    $this->database->delete(self::TABLE)
      ->condition('metric', $metric)
      ->condition('day', $day)
      ->execute();
    foreach ($totals as $dimension => $total) {
      if ($total === 0) {
        continue;
      }
      $this->database->insert(self::TABLE)
        ->fields([
          'metric' => $metric,
          'dimension' => (string) $dimension,
          'day' => $day,
          'total' => $total,
          'updated' => $this->time->getRequestTime(),
        ])
        ->execute();
    }
    unset($transaction);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeStudent(string $metric, int $uid): int {
    $this->assertMetric($metric);
    return (int) $this->database->delete(self::TABLE)
      ->condition('metric', $metric)
      ->condition('dimension', '%/' . $uid . '|%', 'LIKE')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function total(string $metric, int $fromDay, int $toDay, ?string $dimension = NULL): int {
    $this->assertMetric($metric);
    if ($dimension !== NULL) {
      $this->assertDimension($dimension);
    }

    $query = $this->database->select(self::TABLE, 'stats')
      ->condition('metric', $metric)
      ->condition('day', [$fromDay, $toDay], 'BETWEEN');
    if ($dimension !== NULL) {
      $query->condition('dimension', $dimension);
    }
    $query->addExpression('SUM(stats.total)', 'total');

    return (int) $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function countBefore(string $metric, int $day): int {
    $this->assertMetric($metric);
    return (int) $this->database->select(self::TABLE, 'stats')
      ->condition('metric', $metric)
      ->condition('day', $day, '<')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function dimensions(string $metric, int $day): array {
    $this->assertMetric($metric);
    $values = $this->database->select(self::TABLE, 'stats')
      ->fields('stats', ['dimension'])
      ->distinct()
      ->condition('metric', $metric)
      ->condition('day', $day)
      ->orderBy('dimension')
      ->execute()
      ->fetchCol();
    return array_values(array_map('strval', $values));
  }

  /**
   * {@inheritdoc}
   */
  public function dimensionTotals(string $metric, int $fromDay, int $toDay): array {
    $this->assertMetric($metric);
    $query = $this->database->select(self::TABLE, 'stats')
      ->fields('stats', ['dimension'])
      ->condition('metric', $metric)
      ->condition('day', [$fromDay, $toDay], 'BETWEEN')
      ->groupBy('dimension')
      ->orderBy('total', 'DESC')
      ->orderBy('dimension');
    $query->addExpression('SUM(stats.total)', 'total');

    return array_map('intval', $query->execute()->fetchAllKeyed());
  }

  /**
   * {@inheritdoc}
   */
  public function purgeMetricBefore(string $metric, int $day): int {
    $this->assertMetric($metric);
    return (int) $this->database->delete(self::TABLE)
      ->condition('metric', $metric)
      ->condition('day', $day, '<')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function currentDay(): int {
    return intdiv($this->time->getRequestTime(), self::DAY_SECONDS);
  }

  /**
   * Garante que a métrica segue o formato do catálogo.
   */
  private function assertMetric(string $metric): void {
    if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*){1,3}$/', $metric) !== 1) {
      throw new \InvalidArgumentException(sprintf('Identificador de métrica inválido: %s.', $metric));
    }
  }

  /**
   * Rejeita dimensões longas ou com cara de dado pessoal (e-mail).
   */
  private function assertDimension(string $dimension): void {
    if (strlen($dimension) > self::MAX_DIMENSION_LENGTH || str_contains($dimension, '@')) {
      throw new \InvalidArgumentException('Dimensão inválida: use um identificador curto, sem dado pessoal.');
    }
  }

}
