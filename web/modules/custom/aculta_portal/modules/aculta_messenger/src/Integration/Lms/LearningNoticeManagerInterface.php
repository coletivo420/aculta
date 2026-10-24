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
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void;

  /**
   * Counts the recipient's currently accessible unread notices.
   */
  public function unreadCount(int $uid): int;

  /**
   * Marks the owned message as read.
   */
  public function markRead(MessageInterface $message): void;

  /**
   * Sets the read state after revalidating ownership and LMS access.
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

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
   * @return array<int, array{tipo: string, titulo: string, quantidade: int, delivery_id: int}>
   *   Rows ready for the shared email.
   */
  public function digestRowsFor(int $uid): array;

  /**
   * Marks only included digest entries as delivered.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int[] $deliveryIds
   *   Message IDs returned by digestRowsFor() for this recipient.
   */
  public function markDigestSent(int $uid, array $deliveryIds): void;

  /**
   * Discards pending email entries after opt-out.
   */
  public function discardDigest(int $uid): void;

}
