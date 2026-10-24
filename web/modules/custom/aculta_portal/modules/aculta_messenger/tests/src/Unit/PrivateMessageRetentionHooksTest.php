<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\aculta_messenger\Hook\PrivateMessageRetentionHooks;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Psr\Log\NullLogger;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests transactional cleanup of private threads and their notice records.
 */
#[CoversClass(PrivateMessageRetentionHooks::class)]
#[Group('aculta_messenger')]
final class PrivateMessageRetentionHooksTest extends UnitTestCase {

  /**
   * Removes notice records only after the thread deletion succeeds.
   */
  public function testNoticesAreDeletedAfterThreadWithinTransaction(): void {
    $state = (object) [
      'thread_deleted' => FALSE,
      'notices_deleted' => FALSE,
      'committed' => FALSE,
      'rolled_back' => FALSE,
    ];
    $thread = $this->thread($state);
    $messageStorage = $this->noticeStorage($state);
    $transaction = new RetentionHooksTestTransaction($state);
    $hook = $this->hooks($thread, $messageStorage, $transaction);

    $hook->expireDormantThreads();

    self::assertTrue($state->thread_deleted);
    self::assertTrue($state->notices_deleted);
    self::assertTrue($state->committed);
    self::assertFalse($state->rolled_back);
  }

  /**
   * Leaves notification records untouched when thread deletion fails.
   */
  public function testThreadDeletionFailureRollsBackBeforeNoticeLookup(): void {
    $state = (object) [
      'thread_deleted' => FALSE,
      'notices_deleted' => FALSE,
      'committed' => FALSE,
      'rolled_back' => FALSE,
    ];
    $thread = $this->thread($state, TRUE);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->expects(self::never())->method('getQuery');
    $transaction = new RetentionHooksTestTransaction($state);
    $hook = $this->hooks($thread, $messageStorage, $transaction);

    $hook->expireDormantThreads();

    self::assertFalse($state->notices_deleted);
    self::assertFalse($state->committed);
    self::assertTrue($state->rolled_back);
  }

  /**
   * A failed first page does not starve older-ID-independent later threads.
   */
  public function testRetentionCursorAdvancesPastFailuresAndRewindsAtEnd(): void {
    $stateData = (object) [
      'thread_deleted' => FALSE,
      'notices_deleted' => FALSE,
      'committed' => FALSE,
      'rolled_back' => FALSE,
      'cursor' => 0,
    ];
    $cursorKey = 'aculta_messenger.private_message_retention_cursor';
    $retentionState = $this->createMock(StateInterface::class);
    $retentionState->method('get')->with($cursorKey, 0)->willReturnCallback(static fn(): int => $stateData->cursor);
    $retentionState->expects(self::once())->method('set')->with($cursorKey, 18)
      ->willReturnCallback(static function (string $key, int $value) use ($stateData): void {
        $stateData->cursor = $value;
      });
    $retentionState->expects(self::once())->method('delete')->with($cursorKey)
      ->willReturnCallback(static function () use ($stateData): void {
        $stateData->cursor = 0;
      });

    $oldestThread = $this->thread($stateData, TRUE, 1700);
    $nextThread = $this->thread($stateData, FALSE, 1800);
    $laterThread = $this->thread($stateData, FALSE, 1900);
    $cursorConditions = [];
    $threadQuery = $this->createMock(QueryInterface::class);
    $threadQuery->method('accessCheck')->willReturnSelf();
    $threadQuery->method('condition')->willReturnCallback(static function (string $field, mixed $value, ?string $operator = NULL) use ($threadQuery, &$cursorConditions): QueryInterface {
      if ($field === 'id') {
        $cursorConditions[] = [$value, $operator];
      }
      return $threadQuery;
    });
    $threadQuery->method('sort')->with('id', 'ASC')->willReturnSelf();
    $threadQuery->method('range')->with(0, 2)->willReturnSelf();
    $threadQuery->method('execute')->willReturnOnConsecutiveCalls([17, 18], [19]);
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->method('getQuery')->willReturn($threadQuery);
    $threadStorage->expects(self::exactly(2))->method('loadMultiple')
      ->willReturnOnConsecutiveCalls([17 => $oldestThread, 18 => $nextThread], [19 => $laterThread]);

    $noticeQuery = $this->createMock(QueryInterface::class);
    $noticeQuery->method('accessCheck')->willReturnSelf();
    $noticeQuery->method('condition')->willReturnSelf();
    $noticeQuery->method('execute')->willReturn([90]);
    $notification = $this->createMock(EntityInterface::class);
    $noticeStorage = $this->createMock(EntityStorageInterface::class);
    $noticeStorage->method('getQuery')->willReturn($noticeQuery);
    $noticeStorage->method('loadMultiple')->with([90])->willReturn([$notification]);
    $noticeStorage->expects(self::exactly(2))->method('delete');

    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('hasDefinition')->with('private_message_thread')->willReturn(TRUE);
    $entityTypes->method('getStorage')->willReturnMap([
      ['private_message_thread', $threadStorage],
      ['message', $noticeStorage],
    ]);
    $transaction = new RetentionHooksTestTransaction($stateData);
    $database = $this->createMock(Connection::class);
    $database->expects(self::exactly(3))->method('startTransaction')->willReturn($transaction);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->with('aculta_messenger')->willReturn(new NullLogger());
    $hook = new PrivateMessageRetentionHooks(
      $entityTypes,
      $database,
      $time,
      $loggerFactory,
      new ConversationPolicy($this->getConfigFactoryStub([
        'aculta_messenger.settings' => ['cleanup_batch' => 2],
      ])),
      $retentionState,
    );

    $hook->expireDormantThreads();
    self::assertSame(18, $stateData->cursor);
    $hook->expireDormantThreads();

    self::assertSame([[0, '>'], [18, '>']], $cursorConditions);
    self::assertSame(0, $stateData->cursor, 'A short final page resets the scan for the next cron pass.');
    self::assertTrue($stateData->rolled_back, 'The failed thread was rolled back without blocking the next ID.');
    self::assertTrue($stateData->committed, 'Later threads were deleted and their notices cleaned up.');
  }

  /**
   * Builds the retention hook with controlled storage and transaction fixtures.
   */
  private function hooks(
    PrivateMessageThreadInterface $thread,
    EntityStorageInterface $messageStorage,
    RetentionHooksTestTransaction $transaction,
  ): PrivateMessageRetentionHooks {
    $threadQuery = $this->createMock(QueryInterface::class);
    $threadQuery->method('accessCheck')->willReturnSelf();
    $threadQuery->method('condition')->willReturnSelf();
    $threadQuery->method('sort')->willReturnSelf();
    $threadQuery->method('range')->willReturnSelf();
    $threadQuery->method('execute')->willReturn([17]);
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->method('getQuery')->willReturn($threadQuery);
    $threadStorage->method('loadMultiple')->with([17])->willReturn([$thread]);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('hasDefinition')->with('private_message_thread')->willReturn(TRUE);
    $entityTypes->method('getStorage')->willReturnMap([
      ['private_message_thread', $threadStorage],
      ['message', $messageStorage],
    ]);

    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('startTransaction')->willReturn($transaction);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1_792_800_000);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->with('aculta_messenger')->willReturn(new NullLogger());

    return new PrivateMessageRetentionHooks(
      $entityTypes,
      $database,
      $time,
      $loggerFactory,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      $this->createMock(StateInterface::class),
    );
  }

  /**
   * Creates a thread fixture that can fail during deletion.
   */
  private function thread(object $state, bool $failDeletion = FALSE, int $messageId = 700): PrivateMessageThreadInterface {
    $privateMessage = $this->createMock(PrivateMessageInterface::class);
    $privateMessage->method('id')->willReturn((string) $messageId);
    $updated = $this->createMock(FieldItemListInterface::class);
    $updated->method('__get')->with('value')->willReturn(0);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('get')->with('updated')->willReturn($updated);
    $thread->method('getMessages')->with(TRUE)->willReturn([$privateMessage]);
    $thread->expects(self::once())->method('delete')->willReturnCallback(static function () use ($state, $failDeletion): void {
      $state->thread_deleted = TRUE;
      if ($failDeletion) {
        throw new \RuntimeException('Fixture thread delete failure.');
      }
    });
    return $thread;
  }

  /**
   * Creates notice storage that records deletion order.
   */
  private function noticeStorage(object $state): EntityStorageInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturn([90]);
    $notification = $this->createMock(EntityInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([90])->willReturn([$notification]);
    $storage->expects(self::once())->method('delete')->willReturnCallback(static function () use ($state): void {
      self::assertTrue($state->thread_deleted, 'Thread lifecycle must finish before notice deletion.');
      $state->notices_deleted = TRUE;
    });
    return $storage;
  }

}

/**
 * Transaction double records commit and rollback. */
final class RetentionHooksTestTransaction extends Transaction {

  public function __construct(private readonly object $state) {}

  /**
   * {@inheritdoc} */
  public function commitOrRelease(): void {
    $this->state->committed = TRUE;
  }

  /**
   * {@inheritdoc} */
  public function rollBack(): void {
    $this->state->rolled_back = TRUE;
  }

  /**
   * No database resources are held by this test double. */
  public function __destruct() {}

}
