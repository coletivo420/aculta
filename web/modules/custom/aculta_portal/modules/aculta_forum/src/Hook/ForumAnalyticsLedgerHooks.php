<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Analytics\ForumActivityLedger;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Mantém o ledger de idempotência abaixo do limite de retenção.
 */
final class ForumAnalyticsLedgerHooks {

  public function __construct(
    private readonly ForumActivityLedger $ledger,
  ) {}

  /**
   * Remove impressões digitais expiradas.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->ledger->purgeExpired();
  }

}
