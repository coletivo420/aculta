<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Event\FriendshipChangedEvent;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies friendship transitions create only the intended persistent notice.
 */
#[CoversClass(FriendshipNoticeSubscriber::class)]
#[Group('aculta_messenger')]
final class FriendshipNoticeSubscriberTest extends UnitTestCase {

  /**
   * New requests notify the recipient and enqueue the transactional email.
   */
  public function testRequestNotifiesRecipientAndQueuesEmail(): void {
    $this->assertNoticeForTransition(
      FriendshipChangedEvent::REQUESTED,
      requesterId: 10,
      recipientId: 20,
      noticeRecipientId: 20,
    );
  }

  /**
   * Acceptance notifies the member who initiated the request.
   */
  public function testAcceptanceNotifiesOriginalRequester(): void {
    $this->assertNoticeForTransition(
      FriendshipChangedEvent::ACCEPTED,
      requesterId: 10,
      recipientId: 20,
      noticeRecipientId: 10,
    );
  }

  /**
   * Reprocessing a persisted transition does not enqueue a duplicate.
   */
  public function testDuplicateTransitionDoesNotCreateAnotherNoticeOrEmail(): void {
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(20)->willReturn($recipient);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->with(0, 1)->willReturnSelf();
    $query->method('execute')->willReturn([83]);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($query);
    $messageStorage->expects(self::never())->method('create');

    $entityTypeManager = $this->entityTypeManager($userStorage, $messageStorage);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::never())->method('get');

    $subscriber = new FriendshipNoticeSubscriber($entityTypeManager, $queueFactory);
    $subscriber->consume($this->event(FriendshipChangedEvent::REQUESTED));
  }

  /**
   * Inactive accounts do not receive a notice or email.
   */
  public function testInactiveRecipientDoesNotReceiveNoticeOrEmail(): void {
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(FALSE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(20)->willReturn($recipient);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->expects(self::never())->method('getQuery');
    $messageStorage->expects(self::never())->method('create');

    $friendship = $this->friendship(FriendshipStatus::Pending->value);
    $entityTypeManager = $this->entityTypeManager(
      $userStorage,
      $messageStorage,
      $this->friendshipStorage($friendship),
    );
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::never())->method('get');

    (new FriendshipNoticeSubscriber($entityTypeManager, $queueFactory))
      ->consume($this->event(FriendshipChangedEvent::REQUESTED));
  }

  /**
   * Asserts the transition creates a Message for the expected recipient.
   */
  private function assertNoticeForTransition(string $transition, int $requesterId, int $recipientId, int $noticeRecipientId): void {
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('load')->with($noticeRecipientId)->willReturn($recipient);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->with(0, 1)->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn('83');
    $message->expects(self::once())->method('save');
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($query);
    $messageStorage->expects(self::once())->method('create')->with([
      'template' => FriendshipNoticeSubscriber::TEMPLATE,
      'uid' => $noticeRecipientId,
      FriendshipNoticeSubscriber::SOURCE_FIELD => '44:' . $transition . ':1700000000',
      FriendshipNoticeSubscriber::EVENT_FIELD => $transition,
      'field_aculta_msg_delivery' => 'pending',
      'field_aculta_messenger_attempts' => 0,
    ])->willReturn($message);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->with(['message_id' => 83]);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())->method('get')->with(FriendshipNoticeSubscriber::EMAIL_QUEUE)->willReturn($queue);

    $status = $transition === FriendshipChangedEvent::REQUESTED
      ? FriendshipStatus::Pending->value
      : FriendshipStatus::Accepted->value;
    $subscriber = new FriendshipNoticeSubscriber($this->entityTypeManager(
      $userStorage,
      $messageStorage,
      $this->friendshipStorage($this->friendship($status)),
    ), $queueFactory);
    $subscriber->consume($this->event($transition, $requesterId, $recipientId));
  }

  /**
   * Creates the entity manager used by the subscriber.
   */
  private function entityTypeManager(
    EntityStorageInterface $userStorage,
    EntityStorageInterface $messageStorage,
    ?EntityStorageInterface $friendshipStorage = NULL,
  ): EntityTypeManagerInterface {
    $friendshipStorage ??= $this->friendshipStorage($this->friendship(FriendshipStatus::Pending->value));
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['message', $messageStorage],
      ['aculta_friendship', $friendshipStorage],
    ]);
    return $manager;
  }

  /**
   * Creates the friendship used to revalidate the source event.
   */
  private function friendship(string $status): AcultaFriendshipInterface {
    $friendship = $this->createMock(AcultaFriendshipInterface::class);
    $friendship->method('uuid')->willReturn('friendship-uuid');
    $friendship->method('id')->willReturn('44');
    $friendship->method('getRequesterId')->willReturn(10);
    $friendship->method('getRecipientId')->willReturn(20);
    $friendship->method('getStatus')->willReturn($status);
    return $friendship;
  }

  /**
   * Creates UUID lookup storage.
   */
  private function friendshipStorage(AcultaFriendshipInterface $friendship): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->with(['uuid' => 'friendship-uuid'])->willReturn([$friendship]);
    return $storage;
  }

  /**
   * Creates a transition with stable identifiers and timestamp.
   */
  private function event(string $transition, int $requesterId = 10, int $recipientId = 20): MessengerNoticeEvent {
    $uid = $transition === FriendshipChangedEvent::REQUESTED ? $recipientId : $requesterId;
    $eventType = $transition === FriendshipChangedEvent::REQUESTED
      ? 'aculta_user.friendship_requested'
      : 'aculta_user.friendship_accepted';
    return new MessengerNoticeEvent(
      $eventType,
      'aculta_user',
      'aculta_friendship',
      'friendship-uuid',
      '1700000000',
      $uid,
      'account',
      (new \DateTimeImmutable())->setTimestamp(1700000000),
      implode(':', ['user', 'friendship', 'friendship-uuid', $transition, '1700000000', (string) $uid]),
      ['transition' => $transition],
    );
  }

}
