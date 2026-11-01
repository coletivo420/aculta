<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches validated notice envelopes without selecting a storage backend.
 */
final class MessengerNoticePublisher implements MessengerNoticePublisherInterface {

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function publish(MessengerNoticeEvent $event): void {
    $this->eventDispatcher->dispatch($event, MessengerNoticeEvent::EVENT_NAME);
  }

}
