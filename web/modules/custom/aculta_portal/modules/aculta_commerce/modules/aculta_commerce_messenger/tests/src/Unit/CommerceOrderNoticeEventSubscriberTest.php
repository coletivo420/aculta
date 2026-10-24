<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_messenger\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_commerce_messenger\EventSubscriber\CommerceOrderNoticeConsumer;
use Drupal\aculta_commerce_messenger\CommerceOrderNoticeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Messenger consumer for Commerce notice envelopes. */
#[CoversClass(CommerceOrderNoticeConsumer::class)]
#[Group('aculta_commerce_messenger')]
final class CommerceOrderNoticeEventSubscriberTest extends UnitTestCase {

  /**
   * Accepts only declared Commerce event types and source entity mappings. */
  public function testConsumesSupportedCommerceEventsOnly(): void {
    $manager = $this->createMock(CommerceOrderNoticeManagerInterface::class);
    $manager->expects(self::once())->method('record')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool => $event->eventType() === 'store.order_placed',
    ));
    $subscriber = new CommerceOrderNoticeConsumer($manager);

    $subscriber->consume($this->event(
      'store.order_placed',
      'commerce_order',
      ['notice_type' => 'order_placed', 'mail_policy' => 'user_preference'],
    ));
    $subscriber->consume($this->event(
      'store.order_updated',
      'commerce_order',
      ['notice_type' => 'order_updated', 'mail_policy' => 'user_preference'],
    ));
    $subscriber->consume($this->event(
      'store.order_updated',
      'node',
      ['notice_type' => 'order_updated', 'mail_policy' => 'site_digest'],
    ));
  }

  /**
   * Builds a valid event envelope for one test case. */
  private function event(string $type, string $entityType, array $metadata): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      $type,
      'aculta_commerce',
      $entityType,
      'e56f3248-f7b3-4e55-9ea1-91b8e6a0df9f',
      'placed',
      27,
      'shop',
      new \DateTimeImmutable('2026-10-24T00:00:00Z'),
      'test-key-' . $type . '-' . $entityType,
      $metadata,
    );
  }

}
