<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeQueue;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests friendship notice queue creation and cron recovery. */
#[CoversClass(FriendshipNoticeQueue::class)]
#[Group('aculta_messenger')]
final class FriendshipNoticeQueueTest extends UnitTestCase {

  /**
   * A persisted notice gets a bounded enqueue claim before Queue API insertion.
   */
  public function testEnqueuePersistsAttemptBeforeCreatingQueueItem(): void {
    $message = $this->message(0, 'pending', TRUE);
    $calls = [];
    $message->expects(self::exactly(2))
      ->method('set')
      ->willReturnCallback(static function (string $field, int|string $value) use ($message, &$calls): MessageInterface {
        $calls[] = [$field, $value];
        return $message;
      });
    $message->expects(self::once())->method('save');
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->with(['message_id' => 83, 'mailer_managed' => TRUE])->willReturn(501);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())->method('get')->with(FriendshipNoticeSubscriber::EMAIL_QUEUE)->willReturn($queue);
    $storage = $this->messageStorage($message);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->with('aculta_messenger:friendship_notice_enqueue:83', 30.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger:friendship_notice_enqueue:83');

    $this->queue($storage, $queueFactory, $lock)->enqueue($message);
    self::assertSame([
      ['field_aculta_enqueue_attempts', 1],
      ['field_aculta_msg_delivery', 'pending'],
    ], $calls);
  }

  /**
   * Queue refusal leaves the same Message failed, logged and retryable.
   */
  public function testQueueRefusalPersistsFailedStateAndThrows(): void {
    $message = $this->message(0, 'pending');
    $calls = [];
    $expectedCalls = [
      ['field_aculta_enqueue_attempts', 1],
      ['field_aculta_msg_delivery', 'pending'],
      ['field_aculta_msg_delivery', 'failed'],
    ];
    $message->expects(self::exactly(3))
      ->method('set')
      ->willReturnCallback(static function (string $field, int|string $value) use ($message, &$calls, $expectedCalls): MessageInterface {
        $calls[] = [$field, $value];
        self::assertSame($expectedCalls[count($calls) - 1], end($calls));
        return $message;
      });
    $message->expects(self::exactly(2))->method('save');
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->willReturn(FALSE);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($queue);
    $storage = $this->messageStorage($message, reloadCount: 2);
    $lock = $this->createConfiguredMock(LockBackendInterface::class, ['acquire' => TRUE]);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error')->with(
      'Could not queue friendship notice email for Message @id.',
      ['@id' => 83],
    );
    $manager = $this->queue($storage, $queueFactory, $lock, $logger);

    $this->expectException(\RuntimeException::class);
    $manager->enqueue($message);
  }

  /**
   * A concurrent terminal state is not overwritten when Queue API refuses.
   */
  public function testQueueRefusalPreservesConcurrentCancellation(): void {
    $state = ['status' => 'pending', 'enqueue_attempts' => 0, 'delivery_attempts' => 0];
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn('83');
    $message->method('bundle')->willReturn(FriendshipNoticeSubscriber::TEMPLATE);
    $message->method('get')->willReturnCallback(function (string $field) use (&$state): FieldItemListInterface {
      return $this->fieldValue(match ($field) {
        'field_aculta_msg_delivery' => $state['status'],
        'field_aculta_enqueue_attempts' => $state['enqueue_attempts'],
        'field_aculta_messenger_attempts' => $state['delivery_attempts'],
        'field_aculta_mailer_managed' => FALSE,
        default => throw new \InvalidArgumentException($field),
      });
    });
    $calls = [];
    $message->expects(self::exactly(2))->method('set')
      ->willReturnCallback(static function (string $field, int|string $value) use (&$state, &$calls, $message): MessageInterface {
        $calls[] = [$field, $value];
        if ($field === 'field_aculta_enqueue_attempts') {
          $state['enqueue_attempts'] = $value;
        }
        elseif ($field === 'field_aculta_msg_delivery') {
          $state['status'] = $value;
        }
        return $message;
      });
    $message->expects(self::once())->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly(2))->method('load')->with(83)->willReturn($message);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->willReturnCallback(static function () use (&$state): bool {
      // Simulate an independent worker cancelling after the enqueue claim was
      // saved but before this Queue API call reports its failure.
      $state['status'] = 'cancelled';
      return FALSE;
    });
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($queue);
    $lock = $this->createConfiguredMock(LockBackendInterface::class, ['acquire' => TRUE]);

    try {
      $this->queue($storage, $queueFactory, $lock)->enqueue($message);
      self::fail('Queue refusal must be surfaced for bounded retry.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('Friendship notice email could not be queued.', $exception->getMessage());
    }

    self::assertSame('cancelled', $state['status']);
    self::assertSame([
      ['field_aculta_enqueue_attempts', 1],
      ['field_aculta_msg_delivery', 'pending'],
    ], $calls);
  }

  /**
   * Cron requeues only stale pending/failed notices with no delivery attempts.
   */
  public function testCronRequeuesOldPendingNotice(): void {
    $message = $this->message(0, 'pending');
    $message->expects(self::exactly(2))->method('set')->willReturnSelf();
    $message->expects(self::once())->method('save');
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->with('changed', 'ASC')->willReturnSelf();
    $query->method('range')->with(0, 50)->willReturnSelf();
    $query->expects(self::once())->method('execute')->willReturn([83]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->expects(self::once())->method('loadMultiple')->with([83])->willReturn([$message]);
    $storage->expects(self::once())->method('load')->with(83)->willReturn($message);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->with(['message_id' => 83, 'mailer_managed' => FALSE])->willReturn(501);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($queue);
    $lock = $this->createConfiguredMock(LockBackendInterface::class, ['acquire' => TRUE]);
    $time = $this->createConfiguredMock(TimeInterface::class, ['getRequestTime' => 1_800_000_000]);

    $this->queue($storage, $queueFactory, $lock, time: $time)->requeuePending();
  }

  /**
   * Creates a friendship Message fixture with the technical queue fields.
   */
  private function message(int $enqueueAttempts, string $status, bool $mailerManaged = FALSE): MessageInterface {
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn('83');
    $message->method('bundle')->willReturn(FriendshipNoticeSubscriber::TEMPLATE);
    $message->method('get')->willReturnMap([
      ['field_aculta_msg_delivery', $this->fieldValue($status)],
      ['field_aculta_messenger_attempts', $this->fieldValue(0)],
      ['field_aculta_enqueue_attempts', $this->fieldValue($enqueueAttempts)],
      ['field_aculta_mailer_managed', $this->fieldValue($mailerManaged)],
    ]);
    return $message;
  }

  /**
   * Creates storage returning the given Message on each requested reload. */
  private function messageStorage(MessageInterface $message, int $reloadCount = 1): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly($reloadCount))->method('load')->with(83)->willReturn($message);
    return $storage;
  }

  /**
   * Builds the queue with controlled collaborators. */
  private function queue(
    EntityStorageInterface $storage,
    QueueFactory $queueFactory,
    LockBackendInterface $lock,
    ?LoggerInterface $logger = NULL,
    ?TimeInterface $time = NULL,
  ): FriendshipNoticeQueue {
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('message')->willReturn($storage);
    $time ??= $this->createConfiguredMock(TimeInterface::class, ['getRequestTime' => 1_800_000_000]);
    return new FriendshipNoticeQueue($entityTypes, $queueFactory, $lock, $time, $logger ?? $this->createMock(LoggerInterface::class));
  }

  /**
   * Creates a field list whose value can be read by the queue service. */
  private function fieldValue(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

}
