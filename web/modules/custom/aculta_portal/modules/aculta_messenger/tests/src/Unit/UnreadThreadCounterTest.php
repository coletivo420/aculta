<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
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
    $thread = $this->thread(updated: 1000, lastAccess: 1000, messages: [], loadMessages: $this->never());
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

    $service = $this->createMock(PrivateMessageServiceInterface::class);
    $service->expects($this->once())->method('getCountThreadsForUser')->willReturn(3);
    $service->expects($this->once())->method('getThreadsForUser')->with(3)->willReturn([
      'threads' => [$unread, $read, $inaccessible],
      'next_exists' => FALSE,
    ]);

    $this->assertSame(1, (new UnreadThreadCounter($account, $service))->count());
  }

  /**
   * Avoids querying thread data for an anonymous account.
   */
  public function testAnonymousCountDoesNotQueryPrivateMessageService(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);

    $service = $this->createMock(PrivateMessageServiceInterface::class);
    $service->expects($this->never())->method('getCountThreadsForUser');
    $service->expects($this->never())->method('getThreadsForUser');

    $this->assertSame(0, (new UnreadThreadCounter($account, $service))->count());
  }

  /**
   * Creates the counter with an authenticated test account.
   */
  private function counter(): UnreadThreadCounter {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(self::ME);
    $account->method('isAuthenticated')->willReturn(TRUE);
    return new UnreadThreadCounter($account, $this->createMock(PrivateMessageServiceInterface::class));
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
  ): PrivateMessageThreadInterface {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('access')->willReturn($accessResult ?? AccessResult::allowed());
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
