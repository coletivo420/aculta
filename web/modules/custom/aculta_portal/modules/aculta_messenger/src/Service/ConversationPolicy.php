<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Shared limits for direct and voluntary group conversations.
 *
 * The settings form, creation workflow, retention task and send validator read
 * the values from this policy service. Architecture is documented in the
 * Messenger module's ARCHITECTURE.md.
 */
final class ConversationPolicy {

  public const KIND_DIRECT = 'direct';
  public const KIND_GROUP = 'group';

  /**
   * Default group size when configuration has no value.
   */
  public const DEFAULT_GROUP_MAX_MEMBERS = 10;
  public const DEFAULT_RETENTION_MONTHS = 6;
  public const DEFAULT_CLEANUP_BATCH = 20;
  public const DEFAULT_SEND_LIMIT_PER_HOUR = 10;

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Returns direct or group based on the thread member count.
   */
  public function kindOf(PrivateMessageThreadInterface $thread): string {
    return count($thread->getMembers()) > 2 ? self::KIND_GROUP : self::KIND_DIRECT;
  }

  /**
   * Returns how many other members may join a new conversation.
   */
  public function maxRecipients(string $kind, AccountInterface $account): int {
    return match ($kind) {
      self::KIND_GROUP => $this->groupCreationEnabled() ? $this->groupMaxMembers() : 1,
      default => 1,
    };
  }

  /**
   * Whether members may start group conversations.
   */
  public function groupCreationEnabled(): bool {
    return (bool) $this->get('group_creation_enabled', TRUE);
  }

  /**
   * Returns the maximum number of other members allowed in a group.
   */
  public function groupMaxMembers(): int {
    return $this->clamp((int) $this->get('group_max_members', self::DEFAULT_GROUP_MAX_MEMBERS), 2, 50);
  }

  /**
   * Returns months of inactivity before a member conversation is removed.
   */
  public function retentionMonths(): int {
    return $this->clamp((int) $this->get('retention_months', self::DEFAULT_RETENTION_MONTHS), 1, 36);
  }

  /**
   * Returns the maximum threads removed by one cron run.
   */
  public function cleanupBatch(): int {
    return $this->clamp((int) $this->get('cleanup_batch', self::DEFAULT_CLEANUP_BATCH), 1, 500);
  }

  /**
   * Returns the maximum messages one account may send in an hour.
   */
  public function sendLimitPerHour(): int {
    return $this->clamp((int) $this->get('send_limit_per_hour', self::DEFAULT_SEND_LIMIT_PER_HOUR), 1, 1000);
  }

  /**
   * Reads a configuration value or its default.
   */
  private function get(string $key, mixed $default): mixed {
    $value = $this->configFactory->get('aculta_messenger.settings')->get($key);
    return $value ?? $default;
  }

  /**
   * Limits a value to the configured range.
   */
  private function clamp(int $value, int $min, int $max): int {
    return min($max, max($min, $value));
  }

}
