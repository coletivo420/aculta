<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Queue and state contract for direct Forum notice e-mail. */
interface ForumNoticeEmailOutboxInterface {

  /**
   * Queues the persisted event for immediate delivery, if it is still eligible. */
  public function queue(int $uid, string $eventType, int $eventId): bool;

  /**
   * Claims one pending or abandoned delivery attempt. */
  public function claim(int $id): ?array;

  /**
   * Stores a terminal or retryable delivery state. */
  public function setStatus(int $id, string $status): void;

  /**
   * Marks a direct delivery sent and acknowledges its digest counter.
   *
   * The callback runs inside the same database transaction as the outbox
   * state change. A failure leaves both the event and aggregate retryable.
   *
   * @param int $id
   *   Event row ID.
   * @param callable(array): void $acknowledge
   *   Acknowledges the event's corresponding site notice email count.
   *
   * @return bool
   *   TRUE when this call completed the processing event; FALSE if it was no
   *   longer processing.
   */
  public function completeDirectDelivery(int $id, callable $acknowledge): bool;

  /**
   * Requeues pending and stale processing records during cron. */
  public function requeuePending(): void;

}
