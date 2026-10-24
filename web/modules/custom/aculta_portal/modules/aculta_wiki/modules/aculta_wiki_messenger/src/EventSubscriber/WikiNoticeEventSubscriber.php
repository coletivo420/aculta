<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki_messenger\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_wiki_messenger\WikiNoticeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Consumes Wiki events from the shared Messenger event stream. */
final class WikiNoticeEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly WikiNoticeManagerInterface $notices,
  ) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Persists only the two declared Wiki event contracts. */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_wiki'
      || $event->purpose() !== 'wiki'
      || ($event->metadata()['mail_policy'] ?? NULL) !== 'user_preference'
      || !in_array($event->eventType(), ['wiki.review_requested', 'wiki.proposal_outcome'], TRUE)) {
      return;
    }
    $expectedSource = $event->eventType() === 'wiki.review_requested' ? 'node' : 'aculta_wiki_proposal';
    $expectedNotice = $event->eventType() === 'wiki.review_requested' ? 'review_requested' : 'proposal_outcome';
    if ($event->sourceEntityType() !== $expectedSource || ($event->metadata()['notice_type'] ?? NULL) !== $expectedNotice) {
      return;
    }
    $this->notices->record($event);
  }

}
