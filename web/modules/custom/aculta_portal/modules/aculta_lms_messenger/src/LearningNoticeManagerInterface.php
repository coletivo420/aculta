<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_messenger;

use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Contract for LMS avisos backed by ACULTA Messenger.
 */
interface LearningNoticeManagerInterface {

  /**
   * Records a notice once per source and recipient.
   */
  public function record(string $sourceUuid, string $type, int $uid, bool $sendEmail): ?MessageInterface;

  /**
   * Resolves the current target only when access is still valid.
   */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Adds current personal notices to the Messenger list.
   */
  public function addPersonalNotices(array &$items, AccountInterface $account): void;

  /**
   * Counts the recipient's currently accessible unread notices.
   */
  public function unreadCount(int $uid): int;

  /**
   * Marks the owned message as read.
   */
  public function markRead(MessageInterface $message): void;

}
