<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\user\UserDataInterface;

/**
 * Whether the current member can send in a one-to-one conversation, and why not.
 *
 * One rule for the conversation view (what replaces the reply box) and for the send validation, so the two
 * never disagree. Staff keep the send, as they keep the privacy exception for new conversations.
 */
final class ConversationMessagingState {

  public const OPEN = 'open';

  /** The current member blocked the other one. */
  public const BLOCKED_BY_ME = 'blocked_by_me';

  /** The other member blocked the current one. */
  public const BLOCKED_BY_THEM = 'blocked_by_them';

  /** The other member only receives messages from friends, and they are not friends. */
  public const FRIENDS_ONLY = 'friends_only';

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly PrivateMessageBanManagerInterface $banManager,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly UserDataInterface $userData,
  ) {}

  /** The state of the conversation for the current member. */
  public function stateFor(PrivateMessageThreadInterface $thread): string {
    $other = $this->otherMember($thread);
    if ($other === NULL) {
      return self::OPEN;
    }
    if ($this->banManager->isBanned($other)) {
      return self::BLOCKED_BY_ME;
    }
    if ($this->banManager->isCurrentUserBannedByUser($other)) {
      return self::BLOCKED_BY_THEM;
    }
    if ($this->isStaff()) {
      return self::OPEN;
    }
    if ($this->userData->get('aculta_messenger', $other, 'message_privacy') === 'friends'
      && !$this->friendshipManager->areFriendsWith($other)) {
      return self::FRIENDS_ONLY;
    }
    return self::OPEN;
  }

  /** The other member of a one-to-one conversation, or NULL for any other thread. */
  public function otherMember(PrivateMessageThreadInterface $thread): ?int {
    $others = array_values(array_filter(
      array_map('intval', $thread->getMembersId()),
      fn(int $id) => $id !== (int) $this->currentUser->id(),
    ));
    return count($others) === 1 ? $others[0] : NULL;
  }

  /** The pending friendship request with the other member: 'outgoing', 'incoming', or NULL. */
  public function pendingRequest(int $other): ?string {
    $relationship = $this->friendshipManager->getRelationshipWith($other);
    if ($relationship === NULL || $relationship->getStatus() !== FriendshipStatus::Pending->value) {
      return NULL;
    }
    return $relationship->getRequesterId() === (int) $this->currentUser->id() ? 'outgoing' : 'incoming';
  }

  /** Staff keep sending despite the friends-only preference, as in the send validation. */
  public function isStaff(): bool {
    return $this->currentUser->hasRole('administrator') || $this->currentUser->hasRole('content_editor');
  }

}
