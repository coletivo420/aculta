<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent;

/**
 * Persists Wiki editorial events for asynchronous Messenger consumers.
 */
final class WikiEditorialEventQueue {

  public const QUEUE_ID = 'aculta_wiki_editorial_events';

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly Connection $database,
  ) {}

  /**
   * Creates Core's shared queue table before editorial transactions run.
   *
   * DatabaseQueue lazily creates its table on the first item. Doing that from
   * an entity-save transaction runs DDL, which may implicitly commit the
   * surrounding editorial transaction on MariaDB.
   */
  public function prepareStorage(): void {
    if ($this->database->schema()->tableExists('queue')) {
      return;
    }
    $queue = $this->queueFactory->get(self::QUEUE_ID, TRUE);
    $itemId = $queue->createItem(['_aculta_wiki_queue_bootstrap' => TRUE]);
    if ($itemId === FALSE) {
      throw new \RuntimeException('The Wiki editorial queue storage could not be initialized.');
    }
    $item = $queue->claimItem(5);
    if ($item !== FALSE && ($item->data['_aculta_wiki_queue_bootstrap'] ?? FALSE) === TRUE) {
      $queue->deleteItem($item);
    }
  }

  /**
   * Adds an event to Drupal's reliable queue.
   *
   * @throws \RuntimeException
   *   When the queue backend cannot persist the event.
   */
  public function enqueue(WikiEntryReviewRequestedEvent|WikiProposalOutcomeRecordedEvent $event): void {
    $itemId = $this->queueFactory->get(self::QUEUE_ID, TRUE)->createItem($event->toQueuePayload());
    if ($itemId === FALSE) {
      throw new \RuntimeException('The Wiki editorial event could not be persisted.');
    }
  }

}
