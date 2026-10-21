<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Aplica a retenção dos agregados durante o cron do Drupal.
 *
 * Mesmo padrão de `LinkAnalyticsCronHooks` (aculta_links), com o mesmo prazo
 * de 90 dias, para que todos os agregados do ACULTA tenham uma só regra.
 */
final class StatisticsCronHooks {

  /**
   * Dias de retenção dos agregados diários.
   */
  public const RETENTION_DAYS = 90;

  public function __construct(
    #[Autowire(service: 'aculta_statistics.storage')]
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * Remove agregados mais antigos que o prazo de retenção.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->storage->purgeBefore($this->storage->currentDay() - self::RETENTION_DAYS);
  }

}
