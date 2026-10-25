<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Serializes private-message writes against retention for each thread.
 */
final class ConversationWriteLock {

  public const LEASE_SECONDS = 180.0;

  private const PREFIX = 'aculta_messenger:conversation_write:';

  public function __construct(
    private readonly LockBackendInterface $lock,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Acquires the thread lock and returns a fresh persisted entity.
   *
   * A new, unsaved thread needs no lock because retention cannot select it.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThreadInterface|null
   *   Fresh thread when the lock was acquired, the original unsaved thread, or
   *   NULL when another operation owns the lock or the persisted thread
   *   vanished.
   */
  public function acquire(PrivateMessageThreadInterface $thread): ?PrivateMessageThreadInterface {
    $threadId = (int) $thread->id();
    if ($threadId < 1) {
      return $thread;
    }

    $lockName = self::PREFIX . $threadId;
    if (!$this->lock->acquire($lockName, self::LEASE_SECONDS)) {
      return NULL;
    }

    try {
      $fresh = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->loadUnchanged($threadId);
    }
    catch (\Throwable $exception) {
      $this->lock->release($lockName);
      throw $exception;
    }
    if (!$fresh instanceof PrivateMessageThreadInterface) {
      $this->lock->release($lockName);
      return NULL;
    }
    return $fresh;
  }

  /**
   * Acquires the thread lock by ID for the retention worker.
   */
  public function acquireId(int $threadId): bool {
    return $threadId > 0
      && $this->lock->acquire(self::PREFIX . $threadId, self::LEASE_SECONDS);
  }

  /**
   * Releases a persisted thread lock.
   */
  public function release(int $threadId): void {
    if ($threadId > 0) {
      $this->lock->release(self::PREFIX . $threadId);
    }
  }

}
