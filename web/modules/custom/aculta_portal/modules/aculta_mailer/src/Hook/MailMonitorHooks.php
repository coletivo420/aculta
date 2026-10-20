<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Hook;

use Drupal\aculta_mailer\Monitor\MailMonitor;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Cron do monitoramento IMAP: a cadência de cada leitura é decidida pelo próprio monitor.
 */
final class MailMonitorHooks {

  public function __construct(
    private readonly MailMonitor $monitor,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->monitor->cron();
  }

}
