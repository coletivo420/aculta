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
  public function record(string $sourceUuid, string $type, int $uid, bool $sendEmail, string $eventVersion = '1'): ?MessageInterface;

  /**
   * Records a pending answer notice for current course graders.
   */
  public function recordSubmissionForEducators(string $answerUuid, string $eventVersion): void;

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

  /**
   * Returns user IDs with pending digest entries.
   *
   * @return int[]
   *   User IDs.
   */
  public function pendingRecipientIds(): array;

  /**
   * Returns validated daily digest rows.
   *
   * @return array<int, array{tipo: string, titulo: string, quantidade: int}>
   *   Rows ready for the shared email.
   */
  public function digestRowsFor(int $uid): array;

  /**
   * Marks this recipient's pending digest entries as delivered.
   */
  public function markDigestSent(int $uid): void;

  /**
   * Discards pending email entries after opt-out.
   */
  public function discardDigest(int $uid): void;

}
