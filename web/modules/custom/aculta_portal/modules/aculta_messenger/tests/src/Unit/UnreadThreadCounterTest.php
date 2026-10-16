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
 * Unread rule: a message from another member created after this member's last access.
 */
#[Group('aculta_messenger')]
final class UnreadThreadCounterTest extends UnitTestCase {

  private const ME = 7;

  private const OTHER = 9;

  public function testThreadNotUpdatedSinceLastAccessIsReadWithoutLoadingMessages(): void {
    $thread = $this->thread(updated: 1000, lastAccess: 1000, messages: [], loadMessages: $this->never());
    $this->assertFalse($this->counter()->isUnread($thread));
  }

  public function testOtherMemberMessageAfterLastAccessIsUnread(): void {
    $thread = $this->thread(updated: 1200, lastAccess: 1000, messages: [$this->message(self::OTHER, 1200)]);
    $this->assertTrue($this->counter()->isUnread($thread));
  }

  public function testOwnMessageAfterLastAccessIsNotUnread(): void {
    $thread = $this->thread(updated: 1200, lastAccess: 1000, messages: [$this->message(self::ME, 1200)]);
    $this->assertFalse($this->counter()->isUnread($thread));
  }

  public function testMessageCreatedInTheSameSecondAsLastAccessIsRead(): void {
    $thread = $this->thread(updated: 1000, lastAccess: 1000, messages: [$this->message(self::OTHER, 1000)], loadMessages: $this->never());
    $this->assertFalse($this->counter()->isUnread($thread));
  }

  private function counter(): UnreadThreadCounter {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(self::ME);
    $account->method('isAuthenticated')->willReturn(TRUE);
    return new UnreadThreadCounter($account, $this->createMock(PrivateMessageServiceInterface::class));
  }

  /** @param \PHPUnit\Framework\MockObject\Rule\InvokedCount|null $loadMessages */
  private function thread(int $updated, int $lastAccess, array $messages, $loadMessages = NULL): PrivateMessageThreadInterface {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('access')->willReturn(AccessResult::allowed());
    $updatedField = new \stdClass();
    $updatedField->value = (string) $updated;
    $thread->method('get')->with('updated')->willReturn($updatedField);
    $thread->method('getLastAccessTimestamp')->willReturn($lastAccess);
    $expect = $loadMessages ?? $this->any();
    $thread->expects($expect)->method('getMessages')->willReturn($messages);
    return $thread;
  }

  private function message(int $owner, int $created): PrivateMessageInterface {
    $message = $this->createMock(PrivateMessageInterface::class);
    $message->method('getOwnerId')->willReturn($owner);
    $message->method('getCreatedTime')->willReturn($created);
    return $message;
  }

}
