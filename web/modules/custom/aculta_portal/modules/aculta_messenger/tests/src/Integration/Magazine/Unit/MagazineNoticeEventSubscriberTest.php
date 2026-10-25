<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Magazine\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Magazine\EventSubscriber\MagazineNoticeEventSubscriber;
use Drupal\aculta_messenger\Integration\Magazine\MagazineNoticeManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Magazine event allowlist at the shared Messenger boundary. */
#[Group('aculta_messenger')]
final class MagazineNoticeEventSubscriberTest extends TestCase {

  /**
   * Publishes a valid publication event exactly once to its manager. */
  public function testConsumesPublishedEvent(): void {
    $manager = $this->createMock(MagazineNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::isInstanceOf(MessengerNoticeEvent::class));
    $subscriber = new MagazineNoticeEventSubscriber($manager);

    $subscriber->consume($this->event('magazine.publication_published', 'aculta_magazine', 'node', 'magazine', [
      'notice_type' => 'published',
      'mail_policy' => 'user_preference',
    ]));
  }

  /**
   * Accepts only the supported submission and return workflow events. */
  public function testConsumesEditorialReviewTransitions(): void {
    $manager = $this->createMock(MagazineNoticeManagerInterface::class);
    $manager->expects(self::exactly(2))->method('record')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool => in_array($event->eventType(), [
        'magazine.editorial_review_submitted',
        'magazine.editorial_review_returned',
      ], TRUE),
    ));
    $subscriber = new MagazineNoticeEventSubscriber($manager);
    foreach ([
      ['magazine.editorial_review_submitted', 'review_submitted'],
      ['magazine.editorial_review_returned', 'review_returned'],
    ] as [$eventType, $noticeType]) {
      $subscriber->consume($this->event($eventType, 'aculta_magazine', 'node', 'magazine', [
        'notice_type' => $noticeType,
        'mail_policy' => 'user_preference',
      ]));
    }
  }

  /**
   * Rejects events outside the exact source, purpose and metadata contract. */
  public function testRejectsMismatchedEnvelope(): void {
    $manager = $this->createMock(MagazineNoticeManagerInterface::class);
    $manager->expects(self::never())->method('record');
    $subscriber = new MagazineNoticeEventSubscriber($manager);

    $invalidEvents = [
      $this->event(
        'magazine.publication_published',
        'aculta_wiki',
        'node',
        'magazine',
        ['notice_type' => 'published', 'mail_policy' => 'user_preference'],
      ),
      $this->event(
        'magazine.publication_published',
        'aculta_magazine',
        'node',
        'wiki',
        ['notice_type' => 'published', 'mail_policy' => 'user_preference'],
      ),
      $this->event(
        'magazine.publication_updated',
        'aculta_magazine',
        'node',
        'magazine',
        ['notice_type' => 'published', 'mail_policy' => 'user_preference'],
      ),
      $this->event(
        'magazine.editorial_review_requested',
        'aculta_magazine',
        'node',
        'magazine',
        ['notice_type' => 'review_requested', 'mail_policy' => 'user_preference'],
      ),
    ];
    foreach ($invalidEvents as $event) {
      $subscriber->consume($event);
    }
  }

  /**
   * Builds a minimal shared event envelope. */
  private function event(string $type, string $source, string $entity, string $purpose, array $metadata): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      $type,
      $source,
      $entity,
      'revista-uuid',
      '17',
      41,
      $purpose,
      new \DateTimeImmutable('2026-10-25T10:00:00+00:00'),
      $type . ':revista-uuid:17:41',
      $metadata,
    );
  }

}
