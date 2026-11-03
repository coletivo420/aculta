<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;

/** Removes expired raw statements in bounded monthly cron batches. */
final class XapiStatementRetentionPurger {

  private const LAST_MONTH_STATE = 'aculta_lms.xapi.last_completed_retention_month';
  private const BATCH_SIZE = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /** Purges a bounded batch; another cron run continues an unfinished month. */
  public function purge(int $retentionMonths): int {
    $now = $this->time->getRequestTime();
    $month = gmdate('Y-m', $now);
    if ((string) $this->state->get(self::LAST_MONTH_STATE, '') === $month) {
      return 0;
    }

    $cutoff = XapiRetention::cutoff($now, $retentionMonths);
    $storage = $this->entityTypeManager->getStorage('aculta_lms_xapi_statement');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('received', $cutoff, '<')
      ->sort('received')
      ->range(0, self::BATCH_SIZE)
      ->execute();
    if ($ids !== []) {
      $storage->delete($storage->loadMultiple($ids));
    }

    $removed = count($ids);
    if ($removed < self::BATCH_SIZE) {
      $this->state->set(self::LAST_MONTH_STATE, $month);
    }
    $this->logger->notice('Remoção mensal xAPI: {count} declarações removidas; {complete}.', [
      'count' => $removed,
      'complete' => $removed < self::BATCH_SIZE ? 'lote mensal concluído' : 'há mais registros para o próximo cron',
    ]);
    return $removed;
  }

}
