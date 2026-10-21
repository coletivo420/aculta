<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Recorder;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;

/**
 * Ponto único de gravação de métricas para os módulos produtores.
 *
 * Valida o identificador contra o catálogo antes de gravar. Módulos não chamam
 * o storage diretamente.
 */
final class StatisticsRecorder {

  public function __construct(
    private readonly MetricCatalog $catalog,
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * Soma uma quantidade à métrica no dia UTC atual.
   *
   * @throws \InvalidArgumentException
   *   Quando a métrica não está no catálogo ou a dimensão é inválida.
   */
  public function record(string $metric, string $dimension = '', int $amount = 1): void {
    $this->catalog->get($metric);
    $this->storage->record($metric, $dimension, $amount);
  }

}
