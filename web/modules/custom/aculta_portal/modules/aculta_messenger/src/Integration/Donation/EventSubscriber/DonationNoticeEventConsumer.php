<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Consumes the public notice contract emitted by ACULTA Donation. */
final class DonationNoticeEventConsumer implements EventSubscriberInterface {

  public function __construct(private readonly DonationNoticeManagerInterface $notices) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Persists only the two Donation event types. */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_donation'
      || !in_array($event->eventType(), ['donation.order_placed', 'donation.payment_approved'], TRUE)
      || $event->purpose() !== 'account'
      || ($event->metadata()['mail_policy'] ?? NULL) !== 'user_preference') {
      return;
    }
    if ($this->notices->record($event) === NULL) {
      throw new \RuntimeException('Donation notice source or recipient failed revalidation.');
    }
  }

}
