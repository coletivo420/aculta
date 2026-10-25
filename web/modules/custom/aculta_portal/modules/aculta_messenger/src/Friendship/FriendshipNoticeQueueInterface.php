<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Friendship;

use Drupal\message\MessageInterface;

/**
 * Queue contract and recovery for persisted friendship notices. */
interface FriendshipNoticeQueueInterface {

  /**
   * Adds one persisted friendship notice to its direct-email queue. */
  public function enqueue(MessageInterface $message): void;

  /**
   * Requeues a bounded batch of stale notices that have not reached a worker. */
  public function requeuePending(): void;

}
