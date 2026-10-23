<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Hook;

use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\aculta_index\Integrity\ContentIndexIntegrityChecker;
use Drupal\aculta_index\Query\ContentIndexCursorManager;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs bounded Content Index maintenance during cron.
 */
final class ContentIndexCronHooks {

  public function __construct(
    #[Autowire(service: 'aculta_index.work_manager')]
    private readonly ContentIndexWorkManager $workManager,
    #[Autowire(service: 'aculta_index.cursor_manager')]
    private readonly ContentIndexCursorManager $cursorManager,
    #[Autowire(service: 'aculta_index.integrity_checker')]
    private readonly ContentIndexIntegrityChecker $integrityChecker,
    #[Autowire(service: 'aculta_index.generation_manager')]
    private readonly ContentIndexGenerationManager $generationManager,
  ) {}

  /**
   * Recovers queued work and advances bounded cleanup/reconciliation jobs.
   */
  #[Hook('cron')]
  public function maintainContentIndex(): void {
    $this->workManager->recover();
    $this->cursorManager->prune();
    $this->integrityChecker->runScheduledAuditBatch();
    $this->generationManager->discardPendingBatch(500);
  }

}
