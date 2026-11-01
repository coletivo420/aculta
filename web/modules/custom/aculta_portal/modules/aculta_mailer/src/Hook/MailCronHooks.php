<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Hook;

use Drupal\aculta_mailer\Log\MailLogRetention;
use Drupal\aculta_mailer\Monitor\MailMonitor;
use Drupal\aculta_mailer\Report\MailSummary;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Cron do ACULTA Mailer: uma única implementação de hook_cron (o Core recusa mais de uma por módulo).
 *
 * Cada parte decide a própria cadência: retenção do registro, leitura IMAP (30 min e 12 h) e resumo semanal.
 */
final class MailCronHooks {

  public function __construct(
    private readonly MailLogRetention $retention,
    private readonly MailMonitor $monitor,
    private readonly MailSummary $summary,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->retention->cron();
    $this->retention->purgeExpiredPayloads();
    $this->monitor->cron();
    $this->summary->cron();
  }

}
