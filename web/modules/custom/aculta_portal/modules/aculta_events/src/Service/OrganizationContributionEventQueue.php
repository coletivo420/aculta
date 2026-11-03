<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;

/**
 * Reliable queue for minimal organization proposal outcome events. */
final class OrganizationContributionEventQueue {

  public const QUEUE_ID = 'aculta_events_contribution_events';

  private const OUTBOX_COLLECTION = 'aculta_events_contribution_outbox';

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly ?KeyValueFactoryInterface $keyValueFactory = NULL,
  ) {}

  /**
   * Enqueues an outcome without private proposal payload. */
  public function enqueue(OrganizationContributionOutcomeRecordedEvent $event): void {
    $payload = $event->toQueuePayload();
    $outbox = $this->keyValueFactory?->get(self::OUTBOX_COLLECTION);
    $outboxKey = hash('sha256', implode(':', [
      $event->source,
      $event->proposalUuid,
      $event->proposalRevisionId,
      $event->recipientUid,
      $event->outcome,
    ]));
    // The outbox survives a failure in Queue API storage and is retried by
    // cron. It contains identifiers and outcome only, never proposal fields.
    $outbox?->set($outboxKey, $payload);
    $itemId = $this->queueFactory->get(self::QUEUE_ID, TRUE)->createItem($payload);
    if ($itemId === FALSE) {
      throw new \RuntimeException('The Events contribution event could not be persisted.');
    }
    $outbox?->delete($outboxKey);
  }

  /**
   * Retries minimal outcome envelopes left in the durable outbox. */
  public function recoverUndelivered(int $limit = 25): int {
    if ($this->keyValueFactory === NULL || $limit < 1) {
      return 0;
    }
    $outbox = $this->keyValueFactory->get(self::OUTBOX_COLLECTION);
    $keys = [];
    foreach ($outbox->getAllKeys() as $key) {
      $keys[] = $key;
      if (count($keys) >= $limit) {
        break;
      }
    }
    $pending = $outbox->getMultiple($keys);
    $recovered = 0;
    foreach ($pending as $key => $payload) {
      if (!is_array($payload)) {
        $outbox->delete((string) $key);
        continue;
      }
      try {
        $event = new OrganizationContributionOutcomeRecordedEvent(
          is_string($payload['source'] ?? NULL) ? $payload['source'] : '',
          is_string($payload['source_uuid'] ?? NULL) ? $payload['source_uuid'] : '',
          is_int($payload['source_revision_id'] ?? NULL) ? $payload['source_revision_id'] : 0,
          is_int($payload['recipient_uid'] ?? NULL) ? $payload['recipient_uid'] : 0,
          is_string($payload['outcome'] ?? NULL) ? $payload['outcome'] : '',
        );
        $this->enqueue($event);
        $recovered++;
      }
      catch (\InvalidArgumentException) {
        $outbox->delete((string) $key);
      }
      catch (\Throwable) {
        // Leave transient queue failures in the outbox for the next cron run.
      }
    }
    return $recovered;
  }

}
