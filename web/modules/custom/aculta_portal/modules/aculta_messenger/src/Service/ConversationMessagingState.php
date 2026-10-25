<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;

/**
 * Whether bilateral blocking prevents a reply in an existing conversation.
 *
 * Friends-only privacy controls creation of new conversations only. Existing
 * threads remain replyable unless one member has blocked the other; the native
 * send validator enforces the same bilateral block rule.
 */
final class ConversationMessagingState {

  public const OPEN = 'open';

  /**
   * The current member blocked the other one.
   */
  public const BLOCKED_BY_ME = 'blocked_by_me';

  /**
   * The other member blocked the current one.
   */
  public const BLOCKED_BY_THEM = 'blocked_by_them';

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly PrivateMessageBanManagerInterface $banManager,
  ) {}

  /**
   * The state of the conversation for the current member.
   */
  public function stateFor(PrivateMessageThreadInterface $thread): string {
    $others = $this->otherMembers($thread);
    foreach ($others as $other) {
      if ($this->banManager->isBanned($other)) {
        return self::BLOCKED_BY_ME;
      }
    }
    foreach ($others as $other) {
      if ($this->banManager->isCurrentUserBannedByUser($other)) {
        return self::BLOCKED_BY_THEM;
      }
    }
    return self::OPEN;
  }

  /**
   * The other member in a one-to-one conversation, or NULL for other threads.
   */
  public function otherMember(PrivateMessageThreadInterface $thread): ?int {
    $others = $this->otherMembers($thread);
    return count($others) === 1 ? $others[0] : NULL;
  }

  /**
   * Returns distinct positive participants other than the current account.
   *
   * @return int[]
   *   Other member IDs in the thread's stable membership order.
   */
  private function otherMembers(PrivateMessageThreadInterface $thread): array {
    $currentId = (int) $this->currentUser->id();
    return array_values(array_unique(array_filter(
      array_map('intval', $thread->getMembersId()),
      static fn(int $id): bool => $id > 0 && $id !== $currentId,
    )));
  }

}
