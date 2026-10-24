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
   * Requeues pending and stale processing records during cron. */
  public function requeuePending(): void;

}
