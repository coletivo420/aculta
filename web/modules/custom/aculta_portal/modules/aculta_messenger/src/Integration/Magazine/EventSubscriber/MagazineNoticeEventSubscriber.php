<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Magazine\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Magazine\MagazineNoticeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists allowlisted Magazine events in the shared Messenger inbox. */
final class MagazineNoticeEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly MagazineNoticeManagerInterface $notices,
  ) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Consumes only Magazine publication events with the declared envelope. */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_magazine'
      || $event->sourceEntityType() !== 'node'
      || $event->purpose() !== 'magazine'
      || ($event->metadata()['mail_policy'] ?? NULL) !== 'user_preference'
      || !in_array($event->eventType(), ['magazine.publication_published', 'magazine.publication_updated'], TRUE)) {
      return;
    }
    $expected = $event->eventType() === 'magazine.publication_published' ? 'published' : 'updated';
    if (($event->metadata()['notice_type'] ?? NULL) !== $expected) {
      return;
    }
    $this->notices->record($event);
  }

}
