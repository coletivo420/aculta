<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_messenger\Database\ForumNoticeEventSchema;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerInterface;

/**
 * Persists and queues direct Forum email using the existing event ledger.
 */
final class ForumNoticeEmailOutbox implements ForumNoticeEmailOutboxInterface {

  public const QUEUE = 'aculta_messenger_forum_direct_email';

  public const STATUS_PENDING = 'pending';

  public const STATUS_QUEUED = 'queued';

  public const STATUS_PROCESSING = 'processing';

  public const STATUS_SENT = 'sent';

  public const STATUS_CANCELLED = 'cancelled';

  public const STATUS_FAILED = 'failed';

  private const LEASE_SECONDS = 600;

  private const BATCH_SIZE = 50;

  public function __construct(
    private readonly Connection $database,
    private readonly QueueFactory $queueFactory,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function queue(int $uid, string $eventType, int $eventId): bool {
    $row = $this->findEvent($uid, $eventType, $eventId);
    if ($row === NULL || !in_array($row['email_status'], ['none', self::STATUS_PENDING], TRUE)) {
      return FALSE;
    }
    if ($row['email_status'] === 'none') {
      $this->database->update(ForumNoticeEventSchema::TABLE)
        ->fields([
          'email_status' => self::STATUS_PENDING,
          'email_updated' => $this->time->getRequestTime(),
        ])
        ->condition('id', (int) $row['id'])
        ->condition('email_status', 'none')
        ->execute();
    }
    return $this->enqueuePendingId((int) $row['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function claim(int $id): ?array {
    $now = $this->time->getRequestTime();
    $record = $this->load($id);
    if ($record === NULL) {
      return NULL;
    }
    $update = $this->database->update(ForumNoticeEventSchema::TABLE)
      ->fields([
        'email_status' => self::STATUS_PROCESSING,
        'email_updated' => $now,
      ])
      ->expression('email_attempts', 'email_attempts + 1')
      ->condition('id', $id);
    if ($record['email_status'] === self::STATUS_QUEUED) {
      $update->condition('email_status', self::STATUS_QUEUED);
    }
    elseif ($record['email_status'] === self::STATUS_PROCESSING
      && (int) $record['email_updated'] <= $now - self::LEASE_SECONDS) {
      $update->condition('email_status', self::STATUS_PROCESSING)
        ->condition('email_updated', $now - self::LEASE_SECONDS, '<=');
    }
    else {
      return NULL;
    }
    if ($update->execute() !== 1) {
      return NULL;
    }
    return $this->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(int $id, string $status): void {
    if (!in_array($status, [self::STATUS_PENDING, self::STATUS_SENT, self::STATUS_CANCELLED, self::STATUS_FAILED], TRUE)) {
      throw new \InvalidArgumentException('Invalid Forum notice email state.');
    }
    $this->database->update(ForumNoticeEventSchema::TABLE)
      ->fields([
        'email_status' => $status,
        'email_updated' => $this->time->getRequestTime(),
      ])
      ->condition('id', $id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function requeuePending(): void {
    $now = $this->time->getRequestTime();
    $table = ForumNoticeEventSchema::TABLE;
    $stale = $this->database->select($table, 'event')
      ->fields('event', ['id'])
      ->condition('email_status', [self::STATUS_PROCESSING, self::STATUS_QUEUED], 'IN')
      ->condition('email_updated', $now - self::LEASE_SECONDS, '<=')
      ->orderBy('email_updated', 'ASC')
      ->range(0, self::BATCH_SIZE)
      ->execute()
      ->fetchCol();
    if ($stale !== []) {
      $this->database->update($table)
        ->fields(['email_status' => self::STATUS_PENDING, 'email_updated' => $now])
        ->condition('id', $stale, 'IN')
        ->condition('email_status', [self::STATUS_PROCESSING, self::STATUS_QUEUED], 'IN')
        ->execute();
    }
    $pending = $this->database->select($table, 'event')
      ->fields('event', ['id'])
      ->condition('email_status', self::STATUS_PENDING)
      ->orderBy('email_updated', 'ASC')
      ->range(0, self::BATCH_SIZE)
      ->execute()
      ->fetchCol();
    foreach ($pending as $id) {
      $this->enqueuePendingId((int) $id);
    }
  }

  /**
   * Loads one event row for the worker. */
  public function load(int $id): ?array {
    $row = $this->database->select(ForumNoticeEventSchema::TABLE, 'event')
      ->fields('event')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  /**
   * Finds a source event by its unique recipient key. */
  private function findEvent(int $uid, string $eventType, int $eventId): ?array {
    $row = $this->database->select(ForumNoticeEventSchema::TABLE, 'event')
      ->fields('event', ['id', 'email_status'])
      ->condition('uid', $uid)
      ->condition('event_type', $eventType)
      ->condition('event_id', $eventId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  /**
   * Claims a pending row for the queue and recovers failed enqueue attempts. */
  private function enqueuePendingId(int $id): bool {
    $updated = $this->database->update(ForumNoticeEventSchema::TABLE)
      ->fields([
        'email_status' => self::STATUS_QUEUED,
        'email_updated' => $this->time->getRequestTime(),
      ])
      ->condition('id', $id)
      ->condition('email_status', self::STATUS_PENDING)
      ->execute();
    if ($updated !== 1) {
      return FALSE;
    }
    if ($this->queueFactory->get(self::QUEUE)->createItem(['event_id' => $id]) === FALSE) {
      $this->setStatus($id, self::STATUS_PENDING);
      $this->logger->error('Could not queue direct Forum notice email for event @id.', ['@id' => $id]);
      return FALSE;
    }
    return TRUE;
  }

}
