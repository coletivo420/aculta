<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Counts unread conversations using Private Message's per-thread read state.
 */
final class UnreadThreadCounter {

  /**
   * Bounds the number of thread entities hydrated for one counter request.
   */
  private const PAGE_SIZE = 100;

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns accessible conversations with incoming unread messages.
   */
  public function count(): int {
    if (!$this->currentUser->isAuthenticated()) {
      return 0;
    }

    $storage = $this->entityTypeManager->getStorage('private_message_thread');
    $count = 0;
    $cursor = 0;
    do {
      // Use a stable entity-ID cursor rather than Private Message's timestamp
      // cursor, which uses a strict comparison and can skip threads sharing a
      // timestamp at a page boundary. EntityQuery keeps memory bounded without
      // coupling this counter to the contrib mapper's table layout.
      $threadIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('members.target_id', (int) $this->currentUser->id())
        ->condition('id', $cursor, '>')
        ->sort('id', 'ASC')
        ->range(0, self::PAGE_SIZE)
        ->execute();
      if (!$threadIds) {
        break;
      }

      foreach ($storage->loadMultiple($threadIds) as $thread) {
        if ($thread instanceof PrivateMessageThreadInterface && $this->isUnread($thread)) {
          $count++;
        }
      }

      $cursor = (int) end($threadIds);
    } while (count($threadIds) === self::PAGE_SIZE);

    return $count;
  }

  /**
   * Checks a thread using its native per-user access timestamp.
   *
   * A thread is unread when another member sent a message created after this
   * member's last access.
   * This is the only definition used by the badges, the inbox and the thread
   * indicator.
   */
  public function isUnread(PrivateMessageThreadInterface $thread): bool {
    $lastAccess = (int) ($thread->getLastAccessTimestamp($this->currentUser) ?: 0);
    // The thread's updated time is its latest message time. If nothing changed
    // after the last access, no message can be newer. Short-circuit before
    // access checking because private_message's access handler loads the list
    // to apply the member's deletion cutoff.
    if ((int) $thread->get('updated')->value <= $lastAccess) {
      return FALSE;
    }
    if (!$thread->access('view', $this->currentUser, TRUE)->isAllowed()) {
      return FALSE;
    }

    // Thread message references are appended chronologically. Walk backward
    // so a recent incoming message exits quickly and old history is not
    // examined once the member's last-access boundary is reached.
    foreach (array_reverse($thread->getMessages()) as $message) {
      if ((int) $message->getCreatedTime() <= $lastAccess) {
        break;
      }
      if ((int) $message->getOwnerId() !== (int) $this->currentUser->id()) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
