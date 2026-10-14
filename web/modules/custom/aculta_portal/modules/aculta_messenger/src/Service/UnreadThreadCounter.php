<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;

/** Counts unread conversations using Private Message's per-thread read state. */
final class UnreadThreadCounter {

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly PrivateMessageServiceInterface $privateMessageService,
  ) {}

  /** Returns accessible conversations with incoming unread messages. */
  public function count(): int {
    if (!$this->currentUser->isAuthenticated()) {
      return 0;
    }

    $threadCount = $this->privateMessageService->getCountThreadsForUser();
    if ($threadCount < 1) {
      return 0;
    }

    $count = 0;
    foreach ($this->privateMessageService->getThreadsForUser($threadCount)['threads'] as $thread) {
      if ($thread instanceof PrivateMessageThreadInterface && $this->isUnread($thread)) {
        $count++;
      }
    }

    return $count;
  }

  /** Checks a thread using its native per-user access timestamp. */
  public function isUnread(PrivateMessageThreadInterface $thread): bool {
    if (!$thread->access('view', $this->currentUser, TRUE)->isAllowed()) {
      return FALSE;
    }

    $lastAccess = (int) ($thread->getLastAccessTimestamp($this->currentUser) ?: 0);
    foreach ($thread->getMessages() as $message) {
      if ((int) $message->getOwnerId() !== (int) $this->currentUser->id()
        && $lastAccess <= (int) $message->getCreatedTime()) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
