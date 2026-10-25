<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutboxInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Recovers direct Forum notice emails left pending by a queue failure. */
final class ForumNoticeEmailHooks {

  public function __construct(private readonly ForumNoticeEmailOutboxInterface $outbox) {}

  /**
 * Queues a bounded batch of pending or expired-lease direct notices. */
  #[Hook('cron')]
  public function requeuePending(): void {
    $this->outbox->requeuePending();
  }

}
