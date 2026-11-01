<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Events\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Events\EventsContributionNoticeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists only authorized, minimal Events proposal outcome events. */
final class EventsContributionNoticeConsumer implements EventSubscriberInterface {

  public function __construct(private readonly EventsContributionNoticeManagerInterface $notices) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Validates stable source and outcome metadata before persistence. */
  public function consume(MessengerNoticeEvent $event): void {
    $metadata = $event->metadata();
    $metadataKeys = array_keys($metadata);
    sort($metadataKeys);
    $outcome = $metadata['notice_type'] ?? NULL;
    if ($event->sourceModule() !== 'aculta_events'
      || $event->sourceEntityType() !== 'aculta_contribution'
      || $event->purpose() !== 'eventos'
      || $event->eventType() !== 'events.organization_proposal_outcome'
      || $metadataKeys !== ['mail_policy', 'notice_type', 'proposal_source']
      || ($metadata['mail_policy'] ?? NULL) !== 'user_preference'
      || !in_array($metadata['proposal_source'] ?? NULL, ['events_organization', 'events_location', 'events_event'], TRUE)
      || !in_array($outcome, ['submitted', 'needs_changes', 'declined', 'accepted'], TRUE)) {
      return;
    }
    $this->notices->record($event);
  }

}
