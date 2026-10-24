<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms;

use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Contract for LMS avisos backed by ACULTA Messenger.
 */
interface LearningNoticeManagerInterface {

  /**
   * Records a notice once per source and recipient.
   */
  public function record(string $sourceUuid, string $type, int $uid, bool $sendEmail, string $eventVersion = '1', bool $includeInDigest = TRUE): ?MessageInterface;

  /**
   * Publishes pending-answer events for current course graders.
   */
  public function publishSubmissionNoticesForEducators(string $answerUuid, string $eventVersion): void;

  /**
   * Publishes course-content release events for current learners.
   */
  public function publishContentReleaseNoticesForLearners(string $contentUuid, string $contentEntityType, string $eventVersion): void;

  /**
   * Publishes an educator-feedback event for the learner who owns the answer.
   */
  public function publishEducatorFeedbackNoticeForLearner(string $commentUuid, string $eventVersion): void;

  /**
   * Publishes a due-date event for current learners in the source class.
   */
  public function publishActivityDeadlineNoticesForLearners(string $deadlineUuid, string $eventVersion): void;

  /**
   * Publishes an upcoming-deadline reminder event for current class learners.
   */
  public function publishActivityDeadlineReminderForLearners(string $deadlineUuid, string $eventVersion): void;

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
