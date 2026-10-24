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
    );
  }

  /**
   * Creates a thread fixture that can fail during deletion.
   */
  private function thread(object $state, bool $failDeletion = FALSE): PrivateMessageThreadInterface {
    $privateMessage = $this->createMock(PrivateMessageInterface::class);
    $privateMessage->method('id')->willReturn('700');
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
