<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeEventProducer;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Event\FriendshipChangedEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ACULTA User to Messenger event adapter.
 */
#[CoversClass(FriendshipNoticeEventProducer::class)]
#[Group('aculta_messenger')]
final class FriendshipNoticeEventProducerTest extends UnitTestCase {

  /**
   * A request publishes a recipient-scoped event without personal data.
   */
  public function testRequestPublishesCanonicalNoticeEvent(): void {
    $friendship = $this->createMock(AcultaFriendshipInterface::class);
    $friendship->method('getRequesterId')->willReturn(10);
    $friendship->method('getRecipientId')->willReturn(20);
    $friendship->method('uuid')->willReturn('friendship-uuid');
    $changed = $this->createMock(FieldItemListInterface::class);
    $changed->method('__get')->with('value')->willReturn(1700000000);
    $friendship->method('get')->with('changed')->willReturn($changed);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('44')->willReturn($friendship);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_friendship')->willReturn($storage);

    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())->method('publish')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool =>
        $event->eventType() === FriendshipNoticeEventProducer::REQUESTED
        && $event->sourceModule() === 'aculta_user'
        && $event->sourceEntityType() === 'aculta_friendship'
        && $event->sourceEntityUuid() === 'friendship-uuid'
        && $event->sourceRevisionOrVersion() === '1700000000'
        && $event->recipientUid() === 20
        && $event->purpose() === 'account'
        && $event->occurredAt()->getTimestamp() === 1700000000
        && $event->idempotencyKey() === 'user:friendship:friendship-uuid:requested:1700000000:20'
        && $event->metadata() === ['transition' => FriendshipChangedEvent::REQUESTED],
    ));

    (new FriendshipNoticeEventProducer($manager, $publisher))->publish(
      new FriendshipChangedEvent(FriendshipChangedEvent::REQUESTED, '44', 10, 20, 1700000000),
    );
  }

  /**
   * Stale or mismatched source events are not published. */
  public function testMismatchedRelationshipDoesNotPublish(): void {
    $friendship = $this->createMock(AcultaFriendshipInterface::class);
    $friendship->method('getRequesterId')->willReturn(11);
    $friendship->method('getRecipientId')->willReturn(20);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('44')->willReturn($friendship);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_friendship')->willReturn($storage);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');

    (new FriendshipNoticeEventProducer($manager, $publisher))->publish(
      new FriendshipChangedEvent(FriendshipChangedEvent::REQUESTED, '44', 10, 20, 1700000000),
    );
  }

  /**
   * A source event with a stale persisted timestamp is not published.
   */
  public function testStaleSourceVersionDoesNotPublish(): void {
    $friendship = $this->createMock(AcultaFriendshipInterface::class);
    $friendship->method('getRequesterId')->willReturn(10);
    $friendship->method('getRecipientId')->willReturn(20);
    $changed = $this->createMock(FieldItemListInterface::class);
    $changed->method('__get')->with('value')->willReturn(1700000001);
    $friendship->method('get')->with('changed')->willReturn($changed);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with('44')->willReturn($friendship);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('aculta_friendship')->willReturn($storage);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::never())->method('publish');

    (new FriendshipNoticeEventProducer($manager, $publisher))->publish(
      new FriendshipChangedEvent(FriendshipChangedEvent::REQUESTED, '44', 10, 20, 1700000000),
    );
  }

}
