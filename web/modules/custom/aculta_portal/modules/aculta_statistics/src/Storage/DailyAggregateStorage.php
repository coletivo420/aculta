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
 * O schema da tabela ainda não é instalado nesta fase (0.1.0 não executa
 * migração); ver docs/ENTITY-MODEL.md.
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
  public function purgeBefore(int $day): int {
    return (int) $this->database->delete(self::TABLE)
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
