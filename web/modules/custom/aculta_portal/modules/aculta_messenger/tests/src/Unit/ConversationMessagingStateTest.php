<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\aculta_messenger\Service\ConversationMessagingState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests reply eligibility for existing private conversations.
 */
#[CoversClass(ConversationMessagingState::class)]
#[Group('aculta_messenger')]
final class ConversationMessagingStateTest extends UnitTestCase {

  /**
   * Friends-only privacy does not affect replies in an existing thread.
   */
  public function testExistingThreadRemainsReplyableWhenFriendshipEnds(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([42, 84]);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->expects(self::once())->method('isBanned')->with(84)->willReturn(FALSE);
    $banManager->expects(self::once())->method('isCurrentUserBannedByUser')->with(84)->willReturn(FALSE);

    $state = new ConversationMessagingState($currentUser, $banManager);

    self::assertSame(ConversationMessagingState::OPEN, $state->stateFor($thread));
  }

  /**
   * Bilateral blocking still prevents replying in an existing thread.
   */
  public function testBlockByEitherMemberPreventsReplies(): void {
    foreach ([
      [TRUE, FALSE, ConversationMessagingState::BLOCKED_BY_ME],
      [FALSE, TRUE, ConversationMessagingState::BLOCKED_BY_THEM],
    ] as [$blockedByMe, $blockedByThem, $expected]) {
      $currentUser = $this->createMock(AccountProxyInterface::class);
      $currentUser->method('id')->willReturn(42);
      $thread = $this->createMock(PrivateMessageThreadInterface::class);
      $thread->method('getMembersId')->willReturn([42, 84]);
      $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
      $banManager->method('isBanned')->with(84)->willReturn($blockedByMe);
      $banManager->method('isCurrentUserBannedByUser')->with(84)->willReturn($blockedByThem);

      self::assertSame($expected, (new ConversationMessagingState($currentUser, $banManager))->stateFor($thread));
    }
  }

}
