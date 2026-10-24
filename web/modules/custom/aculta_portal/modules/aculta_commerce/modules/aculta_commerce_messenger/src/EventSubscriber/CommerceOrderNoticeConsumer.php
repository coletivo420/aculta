<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_messenger\EventSubscriber;

use Drupal\aculta_commerce_messenger\CommerceOrderNoticeManagerInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Consumes shared Messenger events for the Store adapter. */
final class CommerceOrderNoticeConsumer implements EventSubscriberInterface {

  public function __construct(
    private readonly CommerceOrderNoticeManagerInterface $notices,
  ) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Persists only the declared store order event. */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_commerce'
      || $event->sourceEntityType() !== 'commerce_order'
      || $event->purpose() !== 'shop'
      || $event->eventType() !== 'store.order_placed'
      || ($event->metadata()['notice_type'] ?? NULL) !== 'order_placed'
      || ($event->metadata()['mail_policy'] ?? NULL) !== 'user_preference'
      || !in_array($event->metadata()['delivery_override'] ?? NULL, [NULL, 'off'], TRUE)) {
      return;
    }
    $this->notices->record($event);
  }

}
