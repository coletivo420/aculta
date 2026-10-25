<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Reconciliation\EmailReconciler;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Recalcula a janela recente de e-mails no cron.
 *
 * Os dias antigos saem da janela e ficam como foram calculados. Confirmações de entrega que
 * chegam depois de seis dias não mudam o contador desse dia (limite assumido; ver CHANGELOG).
 */
final class EmailReconciliationCronHooks {

  /**
   * Dias recalculados a cada cron: o atual e os seis anteriores.
   */
  private const WINDOW_DAYS = 7;

  public function __construct(
    private readonly EmailReconciler $reconciler,
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * Recalcula a janela de e-mails até o dia atual.
   */
  #[Hook('cron')]
  public function cron(): void {
    $today = $this->storage->currentDay();
    $this->reconciler->reconcile($today - self::WINDOW_DAYS + 1, $today);
  }

}
