<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Friendship;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\message\MessageInterface;
use Drupal\aculta_messenger\Service\MessengerEmailLock;
use Psr\Log\LoggerInterface;

/**
 * Enqueues and recovers friendship email notices using their Message records. */
final class FriendshipNoticeQueue implements FriendshipNoticeQueueInterface {

  private const STATUS_FIELD = 'field_aculta_msg_delivery';

  private const DELIVERY_ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  private const ENQUEUE_ATTEMPTS_FIELD = 'field_aculta_enqueue_attempts';

  private const MAX_ENQUEUE_ATTEMPTS = 5;

  private const RECOVERY_DELAY = 600;

  private const BATCH_SIZE = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public function enqueue(MessageInterface $message): void {
    $messageId = (int) $message->id();
    if ($messageId < 1) {
      throw new \InvalidArgumentException('A persisted friendship notice is required.');
    }
    // Serialize state transitions with the worker, including preference
    // cancellations, so an enqueue lease cannot resurrect a terminal row.
    $lockId = FriendshipNoticeSubscriber::EMAIL_LOCK_PREFIX . $messageId;
    if (!$this->lock->acquire($lockId, MessengerEmailLock::TIMEOUT)) {
      throw new \RuntimeException('Friendship notice enqueue is already in progress.');
    }

    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $current = $storage->loadUnchanged($messageId);
      if (!$current instanceof MessageInterface || $current->bundle() !== FriendshipNoticeSubscriber::TEMPLATE
        || !in_array($current->get(self::STATUS_FIELD)->value, ['pending', 'failed'], TRUE)
        || (int) $current->get(self::DELIVERY_ATTEMPTS_FIELD)->value > 0) {
        return;
      }

      $attempts = (int) $current->get(self::ENQUEUE_ATTEMPTS_FIELD)->value;
      if ($attempts >= self::MAX_ENQUEUE_ATTEMPTS) {
        $current->set(self::STATUS_FIELD, 'failed_permanent')->save();
        $this->logger->error('Friendship notice Message @id exhausted queue enqueue attempts.', ['@id' => $messageId]);
        throw new \RuntimeException('Friendship notice queue enqueue attempts are exhausted.');
      }

      // Persist the queue lease before adding the item. A cron recovery can
      // safely retry if the process stops in the gap before Queue API insert.
      $current->set(self::ENQUEUE_ATTEMPTS_FIELD, $attempts + 1)
        ->set(self::STATUS_FIELD, 'pending')
        ->save();
      try {
        $itemId = $this->queueFactory->get(FriendshipNoticeSubscriber::EMAIL_QUEUE)
          ->createItem([
            'message_id' => $messageId,
            'mailer_managed' => (bool) $current->get('field_aculta_mailer_managed')->value,
          ]);
        if ($itemId === FALSE) {
          throw new \RuntimeException('Friendship notice email could not be queued.');
        }
      }
      catch (\Throwable $exception) {
        // Queue insertion can race with cancellation in another request. Read
        // the persisted state rather than trusting this request's entity cache.
        $latest = $storage->loadUnchanged($messageId);
        if ($latest instanceof MessageInterface
          && in_array($latest->get(self::STATUS_FIELD)->value, ['pending', 'failed'], TRUE)
          && (int) $latest->get(self::DELIVERY_ATTEMPTS_FIELD)->value === 0) {
          $latest->set(self::STATUS_FIELD, 'failed')->save();
        }
        $this->logger->error('Could not queue friendship notice email for Message @id.', ['@id' => $messageId]);
        throw $exception;
      }
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * {@inheritdoc} */
  public function requeuePending(): void {
    $cutoff = $this->time->getRequestTime() - self::RECOVERY_DELAY;
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', FriendshipNoticeSubscriber::TEMPLATE)
      ->condition(self::STATUS_FIELD, ['pending', 'failed'], 'IN')
      ->condition(self::DELIVERY_ATTEMPTS_FIELD, 0)
      ->condition('changed', $cutoff, '<=')
      ->sort('changed', 'ASC')
      ->range(0, self::BATCH_SIZE)
      ->execute();
    foreach ($storage->loadMultiple($ids) as $message) {
      if (!$message instanceof MessageInterface || $message->bundle() !== FriendshipNoticeSubscriber::TEMPLATE) {
        continue;
      }
      try {
        $this->enqueue($message);
      }
      catch (\Throwable) {
        // enqueue() already logs the failure. Leave the message visible and
        // let the next cron pass advance the bounded enqueue retry count.
      }
    }
  }

}
