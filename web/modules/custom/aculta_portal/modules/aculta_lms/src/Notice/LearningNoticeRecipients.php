<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Notice;

use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Drupal\aculta_lms\EducatorFeedbackPolicy;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\user\UserInterface;

/**
 * Resolves LMS recipients and publishes only through the Messenger contract.
 *
 * Persistence, reading, counters, preferences and delivery stay in Messenger.
 * This service owns the educational facts and who is eligible to receive them.
 */
final class LearningNoticeRecipients implements LearningNoticeRecipientsInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LearningEligibilityInterface $eligibility,
    private readonly MessengerNoticePublisherInterface $publisher,
  ) {}

  /**
   * Publishes one pending answer for current course graders.
   */
  public function publishSubmissionNoticesForEducators(string $answerUuid, string $eventVersion): void {
    $answers = $this->entityTypeManager->getStorage('aculta_lms_answer')->loadByProperties(['uuid' => $answerUuid]);
    $answer = reset($answers);
    if (!$answer instanceof EntityInterface || $answer->getEntityTypeId() !== 'aculta_lms_answer'
      || (string) $answer->get('state')->value !== 'submitted') {
      return;
    }
    $course = $this->eligibility->courseForAnswer($answer);
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return;
    }
    $student = $this->entityTypeManager->getStorage('user')->loadUnchanged((int) $answer->get('uid')->value);
    if (!$student instanceof UserInterface || !$student->isActive()
      || !$this->eligibility->hasCurrentEnrollment($course, $student)) {
      return;
    }
    foreach (GroupMembership::loadByGroup($course) as $membership) {
      if (!$membership->hasPermission('grade students')) {
        continue;
      }
      $educator = $membership->getEntity();
      if (!$educator instanceof UserInterface || !$educator->isActive()
        || (int) $educator->id() === (int) $answer->get('uid')->value
        || !$course->hasPermission('grade students', $educator)) {
        continue;
      }
      $this->publishNotice('submission', 'aculta_lms_answer', $answerUuid, (int) $educator->id(), $eventVersion);
    }
  }

  /**
   * Publishes course-content release events for currently enrolled learners.
   */
  public function publishContentReleaseNoticesForLearners(string $contentUuid, string $contentEntityType, string $eventVersion): void {
    if (!isset(CourseContentReleasedEvent::NOTICE_TYPES[$contentEntityType])) {
      return;
    }
    $contentEntities = $this->entityTypeManager->getStorage($contentEntityType)->loadByProperties(['uuid' => $contentUuid]);
    $content = reset($contentEntities);
    if (!$content instanceof EntityInterface || !$this->eligibility->isContentAvailable($content)) {
      return;
    }
    $courses = $this->eligibility->coursesForContent($content);
    foreach ($courses as $course) {
      if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course' || !$course->isPublished()) {
        continue;
      }
      foreach ($course->getRelationships('lms_classes') as $relationship) {
        if (!$relationship instanceof GroupRelationshipInterface) {
          continue;
        }
        $class = $relationship->getEntity();
        if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class' || !$class->isPublished()) {
          continue;
        }
        foreach (GroupMembership::loadByGroup($class) as $membership) {
          if ($membership->getPluginId() !== 'group_membership') {
            continue;
          }
          $learner = $membership->getEntity();
          if (!$learner instanceof UserInterface || !$learner->isActive()
            || !$course->hasPermission('take course', $learner)
            || !$course->access('view', $learner, TRUE)->isAllowed()) {
            continue;
          }
          $this->publishNotice(
            CourseContentReleasedEvent::NOTICE_TYPES[$contentEntityType],
            $contentEntityType,
            $contentUuid,
            (int) $learner->id(),
            $eventVersion,
          );
        }
      }
    }
  }

  /**
   * Publishes educator-feedback events for the answer owner.
   */
  public function publishEducatorFeedbackNoticeForLearner(string $commentUuid, string $eventVersion): void {
    $comments = $this->entityTypeManager->getStorage('comment')->loadByProperties([
      'uuid' => $commentUuid,
      'comment_type' => EducatorFeedbackPolicy::COMMENT_TYPE,
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment = reset($comments);
    if (!$comment instanceof CommentInterface) {
      return;
    }
    $answer = $comment->getCommentedEntity();
    if (!$answer instanceof EntityInterface || $answer->getEntityTypeId() !== 'aculta_lms_answer') {
      return;
    }
    $course = $this->eligibility->courseForAnswer($answer);
    $learner = $this->entityTypeManager->getStorage('user')->loadUnchanged((int) $answer->get('uid')->value);
    $educator = $comment->getOwner();
    if (!$course instanceof GroupInterface || !$learner instanceof UserInterface || !$learner->isActive()
      || !$educator || (int) $educator->id() === (int) $learner->id()
      || !$course->hasPermission('grade students', $educator)
      || !$this->eligibility->hasCurrentEnrollment($course, $learner)
      || !$comment->access('view', $learner, TRUE)->isAllowed()) {
      return;
    }
    $this->publishNotice('feedback', 'comment', $commentUuid, (int) $learner->id(), $eventVersion);
  }

  /**
   * Records a new or changed activity deadline for current class learners.
   */
  public function publishActivityDeadlineNoticesForLearners(string $deadlineUuid, string $eventVersion): void {
    $this->recordActivityDeadlineNotice($deadlineUuid, $eventVersion, 'deadline');
  }

  /**
   * Records a due-date reminder when the deadline remains in the future.
   */
  public function publishActivityDeadlineReminderForLearners(string $deadlineUuid, string $eventVersion): void {
    $this->recordActivityDeadlineNotice($deadlineUuid, $eventVersion, 'deadline_reminder');
  }

  /**
   * Creates a class deadline notice for current learners.
   */
  private function recordActivityDeadlineNotice(string $deadlineUuid, string $eventVersion, string $type): void {
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'uuid' => $deadlineUuid,
      'plugin_id' => 'lms_class_activity_due',
    ]);
    $deadline = reset($relationships);
    if (!$deadline instanceof GroupRelationshipInterface || $deadline->get('field_activity_due_at')->isEmpty()) {
      return;
    }
    $currentVersion = ActivityDeadlineAssignedEvent::versionFor(
      (string) $deadline->uuid(),
      (string) $deadline->get('field_activity_due_at')->value,
      (int) $deadline->getChangedTime(),
    );
    $timestamp = strtotime((string) $deadline->get('field_activity_due_at')->value . ' UTC');
    if (!hash_equals($currentVersion, $eventVersion) || $timestamp === FALSE || $timestamp <= time()) {
      return;
    }
    $class = $deadline->getGroup();
    $activity = $deadline->getEntity();
    $course = $class instanceof GroupInterface ? $this->eligibility->courseForClass($class) : NULL;
    if (!$class instanceof GroupInterface || !$class->isPublished()
      || !$activity instanceof EntityInterface || $activity->getEntityTypeId() !== 'aculta_lms_activity'
      || !$course instanceof GroupInterface || !$course->isPublished()
      || !$this->eligibility->activityBelongsToCourse($activity, $course)) {
      return;
    }
    foreach (GroupMembership::loadByGroup($class) as $membership) {
      if ($membership->getPluginId() !== 'group_membership') {
        continue;
      }
      $learner = $membership->getEntity();
      if (!$learner instanceof UserInterface || !$learner->isActive()
        || !$course->hasPermission('take course', $learner)
        || !$course->access('view', $learner, TRUE)->isAllowed()) {
        continue;
      }
      $this->publishNotice($type, 'group_relationship', $deadlineUuid, (int) $learner->id(), $eventVersion);
    }
  }

  /**
   * Publishes one eligible LMS recipient through the shared Messenger API.
   */
  private function publishNotice(string $type, string $sourceEntityType, string $sourceUuid, int $uid, string $version): void {
    $eventType = 'lms.' . $type;
    $this->publisher->publish(new MessengerNoticeEvent(
      $eventType,
      'aculta_lms',
      $sourceEntityType,
      $sourceUuid,
      $version,
      $uid,
      'account',
      new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
      implode(':', [$eventType, $sourceUuid, $version, $uid]),
      ['notice_type' => $type, 'mail_policy' => 'user_preference'],
    ));
  }

}
