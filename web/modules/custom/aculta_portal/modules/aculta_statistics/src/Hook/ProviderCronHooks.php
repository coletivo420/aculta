<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Provider\MetricProviderCollector;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Coleta retratos diários dos provedores de métricas durante o cron.
 *
 * Separado da retenção para que a coleta rode mesmo quando não há prazo a
 * expirar. A gravação é idempotente: repetir o cron no mesmo dia recalcula o
 * mesmo retrato em vez de somar.
 */
final class ProviderCronHooks {

  public function __construct(
    private readonly MetricProviderCollector $collector,
  ) {}

  /**
   * Coleta os valores calculados na fonte para o dia UTC corrente.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->collector->collect();
  }

}
