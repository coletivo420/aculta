<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the public event envelope and dispatcher boundary.
 */
#[CoversClass(MessengerNoticeEvent::class)]
#[CoversClass(MessengerNoticePublisher::class)]
#[Group('aculta_messenger')]
final class MessengerNoticePublisherTest extends UnitTestCase {

  /**
   * Confirms the event envelope exposes the required metadata.
   */
  public function testEnvelopeKeepsTheMinimumEventContract(): void {
    $event = $this->event(['course_id' => 35]);

    self::assertSame('lms.enrollment.created', $event->eventType());
    self::assertSame('aculta_lms', $event->sourceModule());
    self::assertSame('group', $event->sourceEntityType());
    self::assertSame('opaque-uuid', $event->sourceEntityUuid());
    self::assertSame('v3', $event->sourceRevisionOrVersion());
    self::assertSame(84, $event->recipientUid());
    self::assertSame('account', $event->purpose());
    self::assertSame('2026-10-24T12:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
    self::assertSame('lms:enrollment:opaque-uuid:v3:84', $event->idempotencyKey());
    self::assertSame(['course_id' => 35], $event->metadata());
  }

  /**
   * Rejects metadata fields that could contain private payloads.
   */
  public function testSensitiveAndNestedMetadataAreRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->event(['message_body' => 'private content']);
  }

  /**
   * Rejects nested metadata instead of accepting arbitrary payload structures.
   */
  public function testNestedMetadataIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->event(['references' => ['uid' => 84]]);
  }

  /**
   * Confirms publisher dispatches through the public event name.
   */
  public function testPublisherDispatchesTheEnvelopeToTheNamedEvent(): void {
    $event = $this->event([]);
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())
      ->method('dispatch')
      ->with($event, MessengerNoticeEvent::EVENT_NAME)
      ->willReturn($event);
    (new MessengerNoticePublisher($dispatcher))->publish($event);
  }

  /**
   * Creates an event fixture for these unit tests.
   */
  private function event(array $metadata): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'lms.enrollment.created',
      'aculta_lms',
      'group',
      'opaque-uuid',
      'v3',
      84,
      'account',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'lms:enrollment:opaque-uuid:v3:84',
      $metadata,
    );
  }

}
