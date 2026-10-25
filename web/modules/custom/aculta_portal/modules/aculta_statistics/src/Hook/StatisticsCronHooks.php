<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Retention\RetentionPolicy;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Aplica a retenção configurada por métrica durante o cron do Drupal.
 *
 * Mesmo padrão de `LinkAnalyticsCronHooks` (aculta_links), com o prazo de cada
 * métrica vindo de `RetentionPolicy` em vez de uma constante.
 */
final class StatisticsCronHooks {

  public function __construct(
    private readonly MetricCatalog $catalog,
    private readonly RetentionPolicy $retention,
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * Remove, por métrica, os agregados mais antigos que o prazo configurado.
   */
  #[Hook('cron')]
  public function cron(): void {
    $currentDay = $this->storage->currentDay();
    foreach (array_keys($this->catalog->all()) as $metric) {
      $cutoff = $this->retention->cutoffDay($this->retention->daysFor($metric), $currentDay);
      if ($cutoff !== NULL) {
        $this->storage->purgeMetricBefore($metric, $cutoff);
      }
    }
  }

}
