<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Reconciliation;

use Drupal\aculta_statistics\Email\EmailFunnel;
use Drupal\aculta_statistics\Email\EmailProviderInterface;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;

/**
 * Recalcula os dias de um intervalo a partir do `aculta_mail_log` e substitui os contadores.
 *
 * Substituir (e não somar) faz a conciliação ser idempotente: rodar de novo, ou depois que
 * um webhook de entrega chegou, não conta o envio em dobro.
 */
final class EmailReconciler {

  public function __construct(
    private readonly EmailProviderInterface $provider,
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * Recalcula os dias UTC de `$fromDay` a `$toDay`, inclusive.
   */
  public function reconcile(int $fromDay, int $toDay): void {
    for ($day = $fromDay; $day <= $toDay; $day++) {
      $counts = $this->provider->countsForDay($day);
      foreach (EmailFunnel::METRICS as $metric) {
        $this->storage->replaceDay($metric, $day, $counts[$metric]);
      }
    }
  }

}
