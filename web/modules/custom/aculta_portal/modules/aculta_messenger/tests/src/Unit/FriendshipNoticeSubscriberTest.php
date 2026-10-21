<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\aculta_user\Event\FriendshipChangedEvent;
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
    $subscriber->onFriendshipChanged($this->event(FriendshipChangedEvent::REQUESTED));
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

    $entityTypeManager = $this->entityTypeManager($userStorage, $messageStorage);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::never())->method('get');

    (new FriendshipNoticeSubscriber($entityTypeManager, $queueFactory))
      ->onFriendshipChanged($this->event(FriendshipChangedEvent::REQUESTED));
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

    $subscriber = new FriendshipNoticeSubscriber($this->entityTypeManager($userStorage, $messageStorage), $queueFactory);
    $subscriber->onFriendshipChanged($this->event($transition, $requesterId, $recipientId));
  }

  /**
   * Creates the entity manager used by the subscriber.
   */
  private function entityTypeManager(EntityStorageInterface $userStorage, EntityStorageInterface $messageStorage): EntityTypeManagerInterface {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['message', $messageStorage],
    ]);
    return $manager;
  }

  /**
   * Creates a transition with stable identifiers and timestamp.
   */
  private function event(string $transition, int $requesterId = 10, int $recipientId = 20): FriendshipChangedEvent {
    return new FriendshipChangedEvent($transition, '44', $requesterId, $recipientId, 1700000000);
  }

}
