<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unread means another member sent a message after the user's last access.
 */
#[Group('aculta_messenger')]
final class UnreadThreadCounterTest extends UnitTestCase {

  private const ME = 7;

  private const OTHER = 9;

  /**
   * Skips loading message entities when the thread timestamp has not changed.
   */
  public function testThreadNotUpdatedSinceLastAccessIsReadWithoutLoadingMessages(): void {
    $thread = $this->thread(
      updated: 1000,
      lastAccess: 1000,
      messages: [],
      loadMessages: $this->never(),
      checkAccess: $this->never(),
    );
    $this->assertFalse($this->counter()->isUnread($thread));
  }

  /**
   * Marks another member's newer message as unread.
   */
  public function testOtherMemberMessageAfterLastAccessIsUnread(): void {
    $thread = $this->thread(updated: 1200, lastAccess: 1000, messages: [$this->message(self::OTHER, 1200)]);
    $this->assertTrue($this->counter()->isUnread($thread));
  }

  /**
   * Does not count the current user's own newer message as unread.
   */
  public function testOwnMessageAfterLastAccessIsNotUnread(): void {
    $thread = $this->thread(updated: 1200, lastAccess: 1000, messages: [$this->message(self::ME, 1200)]);
    $this->assertFalse($this->counter()->isUnread($thread));
  }

  /**
   * Scans newest messages first and stops at the read boundary.
   */
  public function testUnreadScanDoesNotInspectOlderHistory(): void {
    $newestOwnMessage = $this->message(self::ME, 1200);
    $newestOwnMessage->expects(self::once())->method('getCreatedTime')->willReturn(1200);
    $newestOwnMessage->expects(self::once())->method('getOwnerId')->willReturn(self::ME);
    $oldIncomingMessage = $this->message(self::OTHER, 1000);
    $oldIncomingMessage->expects(self::once())->method('getCreatedTime')->willReturn(1000);
    $oldIncomingMessage->expects(self::never())->method('getOwnerId');
    $thread = $this->thread(
      updated: 1200,
      lastAccess: 1100,
      messages: [$oldIncomingMessage, $newestOwnMessage],
    );

    self::assertFalse($this->counter()->isUnread($thread));
  }

  /**
   * Returns as soon as the newest unread incoming message is found.
   */
  public function testUnreadScanExitsOnNewestIncomingMessage(): void {
    $newestIncomingMessage = $this->message(self::OTHER, 1200);
    $newestIncomingMessage->expects(self::once())->method('getCreatedTime')->willReturn(1200);
    $newestIncomingMessage->expects(self::once())->method('getOwnerId')->willReturn(self::OTHER);
    $olderMessage = $this->message(self::OTHER, 1150);
    $olderMessage->expects(self::never())->method('getCreatedTime');
    $olderMessage->expects(self::never())->method('getOwnerId');
    $thread = $this->thread(
      updated: 1200,
      lastAccess: 1100,
      messages: [$olderMessage, $newestIncomingMessage],
    );

    self::assertTrue($this->counter()->isUnread($thread));
  }

  /**
   * Treats messages created at the last-access second as read.
   */
  public function testMessageCreatedInTheSameSecondAsLastAccessIsRead(): void {
    $thread = $this->thread(updated: 1000, lastAccess: 1000, messages: [$this->message(self::OTHER, 1000)], loadMessages: $this->never());
    $this->assertFalse($this->counter()->isUnread($thread));
  }

  /**
   * Counts accessible unread threads and excludes other threads.
   */
  public function testCountIncludesOnlyAccessibleUnreadThreads(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(self::ME);
    $account->method('isAuthenticated')->willReturn(TRUE);

    $unread = $this->thread(updated: 1200, lastAccess: 1000, messages: [$this->message(self::OTHER, 1200)]);
    $read = $this->thread(updated: 1000, lastAccess: 1000, messages: [], loadMessages: $this->never());
    $inaccessible = $this->thread(
      updated: 1200,
      lastAccess: 1000,
      messages: [$this->message(self::OTHER, 1200)],
      accessResult: AccessResult::forbidden(),
    );

    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $query = $this->threadQuery([1, 2, 3], self::ME, 0);
    $threadStorage->expects($this->once())->method('getQuery')->willReturn($query);
    $threadStorage->expects($this->once())
      ->method('loadMultiple')
      ->with([1, 2, 3])
      ->willReturn([$unread, $read, $inaccessible]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())->method('getStorage')->with('private_message_thread')->willReturn($threadStorage);

    $this->assertSame(1, (new UnreadThreadCounter($account, $manager))->count());
  }

  /**
   * Avoids querying thread data for an anonymous account.
   */
  public function testAnonymousCountDoesNotQueryPrivateMessageService(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->never())->method('getStorage');

    $this->assertSame(0, (new UnreadThreadCounter($account, $manager))->count());
  }

  /**
   * Continues through stable-ID pages without skipping equal-time threads.
   */
  public function testCountLoadsThreadsInBoundedStableIdPages(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(self::ME);
    $account->method('isAuthenticated')->willReturn(TRUE);

    $incoming = $this->message(self::OTHER, 1200);
    $unread = $this->thread(updated: 1200, lastAccess: 1000, messages: [$incoming]);
    $firstPage = range(1, 100);
    $secondPage = [101];

    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->expects(self::exactly(2))->method('getQuery')->willReturnOnConsecutiveCalls(
      $this->threadQuery($firstPage, self::ME, 0),
      $this->threadQuery($secondPage, self::ME, 100),
    );
    $threadStorage->expects(self::exactly(2))->method('loadMultiple')->willReturnOnConsecutiveCalls(
      array_fill_keys($firstPage, $unread),
      [101 => $unread],
    );
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::once())->method('getStorage')->with('private_message_thread')->willReturn($threadStorage);

    self::assertSame(101, (new UnreadThreadCounter($account, $manager))->count());
  }

  /**
   * Creates the counter with an authenticated test account.
   */
  private function counter(): UnreadThreadCounter {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(self::ME);
    $account->method('isAuthenticated')->willReturn(TRUE);
    return new UnreadThreadCounter($account, $this->createMock(EntityTypeManagerInterface::class));
  }

  /**
   * Creates a query mock for one thread page.
   *
   * @param int[] $ids
   *   The returned IDs.
   * @param int $uid
   *   The member filter.
   * @param int $cursor
   *   The exclusive ID cursor.
   */
  private function threadQuery(array $ids, int $uid, int $cursor): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->expects(self::once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->expects(self::exactly(2))->method('condition')->willReturnCallback(
      static function (string $field, $value, ?string $operator = NULL) use ($uid, $cursor, $query) {
        if ($field === 'members.target_id') {
          self::assertSame($uid, $value);
          self::assertNull($operator);
        }
        else {
          self::assertSame('id', $field);
          self::assertSame($cursor, $value);
          self::assertSame('>', $operator);
        }
        return $query;
      },
    );
    $query->expects(self::once())->method('sort')->with('id', 'ASC')->willReturnSelf();
    $query->expects(self::once())->method('range')->with(0, 100)->willReturnSelf();
    $query->expects(self::once())->method('execute')->willReturn($ids);
    return $query;
  }

  /**
   * Creates a thread fixture with the requested access and message behavior.
   *
   * @param int $updated
   *   Last updated timestamp.
   * @param int $lastAccess
   *   Current user's last access timestamp.
   * @param \Drupal\private_message\Entity\PrivateMessageInterface[] $messages
   *   Messages attached to the thread.
   * @param \PHPUnit\Framework\MockObject\Rule\InvocationOrder|null $loadMessages
   *   Expected call count for loading messages.
   * @param \Drupal\Core\Access\AccessResult|null $accessResult
   *   Access result for the current user.
   * @param \PHPUnit\Framework\MockObject\Rule\InvocationOrder|null $checkAccess
   *   Expected access-check invocation count.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThreadInterface
   *   Thread mock.
   */
  private function thread(
    int $updated,
    int $lastAccess,
    array $messages,
    $loadMessages = NULL,
    ?AccessResult $accessResult = NULL,
    $checkAccess = NULL,
  ): PrivateMessageThreadInterface {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    if ($checkAccess !== NULL) {
      $thread->expects($checkAccess)->method('access')->willReturn($accessResult ?? AccessResult::allowed());
    }
    else {
      $thread->method('access')->willReturn($accessResult ?? AccessResult::allowed());
    }
    $updatedField = new \stdClass();
    $updatedField->value = (string) $updated;
    $thread->method('get')->with('updated')->willReturn($updatedField);
    $thread->method('getLastAccessTimestamp')->willReturn($lastAccess);
    $expect = $loadMessages ?? $this->any();
    $thread->expects($expect)->method('getMessages')->willReturn($messages);
    return $thread;
  }

  /**
   * Creates a message fixture.
   */
  private function message(int $owner, int $created): PrivateMessageInterface {
    $message = $this->createMock(PrivateMessageInterface::class);
    $message->method('getOwnerId')->willReturn($owner);
    $message->method('getCreatedTime')->willReturn($created);
    return $message;
  }

}
