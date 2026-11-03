<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Notice;

/**
 * LMS producer contract for notices published through Messenger.
 */
interface LearningNoticeRecipientsInterface {

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

}
