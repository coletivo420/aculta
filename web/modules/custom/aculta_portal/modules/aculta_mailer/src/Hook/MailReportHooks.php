<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Hook;

use Drupal\aculta_mailer\Report\MailSummary;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Cron dos relatórios por e-mail: o resumo decide a própria cadência.
 */
final class MailReportHooks {

  public function __construct(
    private readonly MailSummary $summary,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->summary->cron();
  }

}
