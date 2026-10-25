<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_messenger\Hook\PrivateMessageReplyHooks;
use Drupal\aculta_messenger\Service\ConversationMessagingState;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests generic blocked-state copy for multi-member conversations.
 */
#[CoversClass(PrivateMessageReplyHooks::class)]
#[Group('aculta_messenger')]
final class PrivateMessageReplyHooksTest extends UnitTestCase {

  /**
   * Group-block notices avoid claiming which participant made the block.
   */
  public function testGroupBlockedNoticesUseGenericParticipantCopy(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([42, 84, 85]);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->method('isBanned')->willReturnCallback(static fn(int $uid): bool => $uid === 84);
    $state = new ConversationMessagingState($currentUser, $banManager);
    $hook = (new \ReflectionClass(PrivateMessageReplyHooks::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(PrivateMessageReplyHooks::class, 'messagingState'))->setValue($hook, $state);
    $noticeMethod = new \ReflectionMethod(PrivateMessageReplyHooks::class, 'notice');

    $notice = $noticeMethod->invoke($hook, $thread, $state->stateFor($thread));

    self::assertSame(
      'Você bloqueou um participante desta conversa. Remova o bloqueio nas opções dessa pessoa para enviar mensagens novamente.',
      $notice['text']['#plain_text'],
    );
    self::assertArrayNotHasKey('action', $notice);
  }

  /**
   * A group member blocked by another participant sees no identity disclosure.
   */
  public function testGroupInboundBlockCopyDoesNotIdentifyParticipant(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([42, 84, 85]);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->method('isBanned')->willReturn(FALSE);
    $banManager->method('isCurrentUserBannedByUser')->willReturnCallback(static fn(int $uid): bool => $uid === 85);
    $state = new ConversationMessagingState($currentUser, $banManager);
    $hook = (new \ReflectionClass(PrivateMessageReplyHooks::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(PrivateMessageReplyHooks::class, 'messagingState'))->setValue($hook, $state);
    $noticeMethod = new \ReflectionMethod(PrivateMessageReplyHooks::class, 'notice');

    $notice = $noticeMethod->invoke($hook, $thread, $state->stateFor($thread));

    self::assertSame(
      'Um participante bloqueou você, por isso não pode enviar ou receber mensagens nesta conversa.',
      $notice['text']['#plain_text'],
    );
    self::assertArrayNotHasKey('action', $notice);
  }

}
