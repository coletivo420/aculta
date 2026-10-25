<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Game\EventSubscriber;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Game\GameAchievementNoticeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists ACULTA Game awards through the shared notice contract. */
final class GameAchievementNoticeEventConsumer implements EventSubscriberInterface {

  public function __construct(private readonly GameAchievementNoticeManagerInterface $notices) {}

  /**
   * {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [MessengerNoticeEvent::EVENT_NAME => 'consume'];
  }

  /**
   * Consumes only award events emitted by ACULTA Game. */
  public function consume(MessengerNoticeEvent $event): void {
    if ($event->sourceModule() !== 'aculta_game'
      || $event->sourceEntityType() !== 'aculta_badge_award'
      || $event->eventType() !== 'game.achievement_awarded'
      || $event->purpose() !== 'account'
      || $event->sourceRevisionOrVersion() === NULL
      || !ctype_digit($event->sourceRevisionOrVersion())
      || ($event->metadata()['notice_type'] ?? NULL) !== 'achievement') {
      return;
    }
    if ($this->notices->record($event) === NULL) {
      throw new \RuntimeException('Achievement notice source or recipient failed revalidation.');
    }
  }

}
