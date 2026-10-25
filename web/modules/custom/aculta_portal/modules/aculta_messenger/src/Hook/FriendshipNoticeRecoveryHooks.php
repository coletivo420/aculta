<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Friendship\FriendshipNoticeQueueInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Recovers stale friendship email notices from their persisted Message rows. */
final class FriendshipNoticeRecoveryHooks {

  public function __construct(private readonly FriendshipNoticeQueueInterface $queue) {}

  /**
 * Requeues a bounded batch of stale friendship email notices. */
  #[Hook('cron')]
  public function recover(): void {
    $this->queue->requeuePending();
  }

}
