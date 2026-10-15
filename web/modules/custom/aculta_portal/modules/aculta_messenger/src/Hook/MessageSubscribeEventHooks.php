<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\node\NodeInterface;

/** Queues publication events for the forum's existing follow flag. */
final class MessageSubscribeEventHooks {

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /** Enqueues an initially published forum topic. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'forum' && $entity->isPublished()) {
      $this->enqueue($entity);
    }
  }

  /** Enqueues a forum topic when it becomes published. */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'forum' || !$entity->isPublished()) {
      return;
    }

    $original = $entity->getOriginal();
    if ($original instanceof NodeInterface && !$original->isPublished()) {
      $this->enqueue($entity);
    }
  }

  /** Adds a safe entity identifier to the Core queue. */
  private function enqueue(NodeInterface $node): void {
    $itemId = $this->queueFactory->get('aculta_messenger_message_subscribe_event')
      ->createItem(['node_id' => (int) $node->id()]);
    if ($itemId === FALSE) {
      $this->loggerFactory->get('aculta_messenger')->error('Could not queue a forum publication event.');
    }
  }

}
