<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * The rules of the chat: how many people a conversation may have, how long it is kept and how much is sent.
 *
 * The numbers are the administrator's (aculta_messenger.settings, the form at /painel-administrativo/aculta/mensagens).
 * This class is the one place that reads them: the new conversation form, the retention cron and the send validator
 * all ask here. See docs/portal/MESSENGER-CONVERSATION-KINDS.md.
 *
 * The LMS course chat uses this same chat and the global notices; it is persistent (no retention).
 */
final class ConversationPolicy {

  public const KIND_DIRECT = 'direct';
  public const KIND_GROUP = 'group';
  public const KIND_FORUM = 'forum';
  public const KIND_COURSE = 'course';

  /** Defaults, used when the configuration has no value (config/install/aculta_messenger.settings.yml). */
  public const DEFAULT_GROUP_MAX_MEMBERS = 10;
  public const DEFAULT_RETENTION_MONTHS = 6;
  public const DEFAULT_CLEANUP_BATCH = 20;
  public const DEFAULT_SEND_LIMIT_PER_HOUR = 10;

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * The kind of a thread, from its members: two is a direct conversation, more is a group.
   */
  public function kindOf(PrivateMessageThreadInterface $thread): string {
    return count($thread->getMembers()) > 2 ? self::KIND_GROUP : self::KIND_DIRECT;
  }

  /**
   * How many other members the account may add in a new conversation of this kind.
   */
  public function maxRecipients(string $kind, AccountInterface $account): int {
    return match ($kind) {
      self::KIND_GROUP => $this->groupCreationEnabled() ? $this->groupMaxMembers() : 1,
      default => 1,
    };
  }

  /** Whether common members may start a group at all. Off, the new conversation accepts one person. */
  public function groupCreationEnabled(): bool {
    return (bool) $this->get('group_creation_enabled', TRUE);
  }

  /** The most other members in a group (the administrator's value, between 2 and 50). */
  public function groupMaxMembers(): int {
    return $this->clamp((int) $this->get('group_max_members', self::DEFAULT_GROUP_MAX_MEMBERS), 2, 50);
  }

  /**
   * Months of inactivity before a conversation is removed (between 1 and 36), or NULL when the kind is kept: the LMS
   * course chat is persistent.
   */
  public function retentionMonths(string $kind = self::KIND_DIRECT): ?int {
    if ($kind === self::KIND_COURSE) {
      return NULL;
    }
    return $this->clamp((int) $this->get('retention_months', self::DEFAULT_RETENTION_MONTHS), 1, 36);
  }

  /** Conversations removed by one run of the cron (between 1 and 500). */
  public function cleanupBatch(): int {
    return $this->clamp((int) $this->get('cleanup_batch', self::DEFAULT_CLEANUP_BATCH), 1, 500);
  }

  /** Messages one account may send in one hour (between 1 and 1000). */
  public function sendLimitPerHour(): int {
    return $this->clamp((int) $this->get('send_limit_per_hour', self::DEFAULT_SEND_LIMIT_PER_HOUR), 1, 1000);
  }

  private function get(string $key, mixed $default): mixed {
    $value = $this->configFactory->get('aculta_messenger.settings')->get($key);
    return $value ?? $default;
  }

  private function clamp(int $value, int $min, int $max): int {
    return min($max, max($min, $value));
  }

}
