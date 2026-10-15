<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * The rules that depend on the kind of a conversation: how many people it may have and how long it is kept.
 *
 * One place for these rules, so the new conversation form, the retention cron and the future integrations (forum,
 * LMS, wiki) read the same numbers. See docs/portal/MESSENGER-CONVERSATION-KINDS.md for the decisions behind them.
 *
 * Only DIRECT and GROUP are detected today (from the member count). FORUM and COURSE are declared so the rules exist
 * before their integrations: a thread gets its kind from the integration that creates it, which is not built yet.
 */
final class ConversationPolicy {

  public const KIND_DIRECT = 'direct';
  public const KIND_GROUP = 'group';
  public const KIND_FORUM = 'forum';
  public const KIND_COURSE = 'course';

  /** Other members a common member may add to a group, besides the author. */
  public const GROUP_MAX_RECIPIENTS = 10;

  /** Months of inactivity after which a conversation is removed (not applied to COURSE). */
  public const RETENTION_MONTHS = 6;

  /**
   * The kind of a thread. Detected from the members: two members is a direct conversation, more is a group.
   */
  public function kindOf(PrivateMessageThreadInterface $thread): string {
    return count($thread->getMembers()) > 2 ? self::KIND_GROUP : self::KIND_DIRECT;
  }

  /**
   * How many other members the account may write to in a new conversation of this kind.
   */
  public function maxRecipients(string $kind, AccountInterface $account): int {
    return match ($kind) {
      self::KIND_DIRECT => 1,
      // Common members: up to GROUP_MAX_RECIPIENTS others. Educators and administrators are decided with the LMS.
      self::KIND_GROUP => self::GROUP_MAX_RECIPIENTS,
      default => 1,
    };
  }

  /**
   * Months of inactivity before removal, or NULL when the kind is kept (LMS course chats are persistent).
   */
  public function retentionMonths(string $kind): ?int {
    return match ($kind) {
      self::KIND_COURSE => NULL,
      default => self::RETENTION_MONTHS,
    };
  }

}
