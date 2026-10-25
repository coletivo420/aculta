<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Wiki\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Wiki\EventSubscriber\WikiNoticeEventSubscriber;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Messenger consumer for Wiki notice envelopes. */
#[CoversClass(WikiNoticeEventSubscriber::class)]
#[Group('aculta_messenger')]
final class WikiNoticeEventSubscriberTest extends UnitTestCase {

  /**
   * Accepts only declared Wiki event types and source entity mappings. */
  public function testConsumesSupportedWikiEventsOnly(): void {
    $manager = $this->createMock(WikiNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool => $event->eventType() === 'wiki.proposal_outcome',
    ));
    $subscriber = new WikiNoticeEventSubscriber($manager);

    $subscriber->consume($this->event(
      'wiki.proposal_outcome',
      'aculta_wiki_proposal',
      ['notice_type' => 'proposal_outcome', 'mail_policy' => 'user_preference'],
    ));
    $subscriber->consume($this->event(
      'wiki.review_requested',
      'aculta_wiki_proposal',
      ['notice_type' => 'review_requested', 'mail_policy' => 'user_preference'],
    ));
    $subscriber->consume($this->event(
      'wiki.review_requested',
      'node',
      ['notice_type' => 'review_requested', 'mail_policy' => 'site_digest'],
    ));
  }

  /**
   * Builds a valid event envelope for one test case. */
  private function event(string $type, string $entityType, array $metadata): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      $type,
      'aculta_wiki',
      $entityType,
      'e56f3248-f7b3-4e55-9ea1-91b8e6a0df9f',
      '12',
      27,
      'wiki',
      new \DateTimeImmutable('2026-10-24T00:00:00Z'),
      'test-key-' . $type . '-' . $entityType,
      $metadata,
    );
  }

}
