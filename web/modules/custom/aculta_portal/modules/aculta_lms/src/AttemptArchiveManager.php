<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonInterface;
use Drupal\user\UserInterface;
use Drupal\comment\CommentInterface;
use Drupal\group\Entity\GroupInterface;

/** Archives overwritten LMS answers and exposes access-checked history. */
final class AttemptArchiveManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Archives the answer before LMS removes it from an earlier attempt.
   */
  public function archiveAnswer(AnswerInterface $answer): void {
    $this->storeSnapshot($answer, 'deleted');
  }

  /** Archives the persisted values before a student's or educator's update. */
  public function archivePreviousAnswer(AnswerInterface $answer): void {
    if ($answer->isNew()) {
      return;
    }
    $original = $this->entityTypeManager->getStorage('lms_answer')->loadUnchanged($answer->id());
    if (!$original instanceof AnswerInterface) {
      return;
    }
    $answer_changed = $original->getData() !== $answer->getData();
    $evaluation_changed = $original->getScore() !== $answer->getScore()
      || $original->isEvaluated() !== $answer->isEvaluated();
    if (!$answer_changed && !$evaluation_changed) {
      return;
    }

    $this->storeSnapshot($original, $answer_changed ? 'answer_changed' : 'evaluation_changed');
  }

  /** Stores an immutable copy of the Answer state that is about to disappear. */
  private function storeSnapshot(AnswerInterface $answer, string $event_type): void {
    $context = $this->getAnswerContext($answer);
    if ($context === NULL) {
      // LMS lifecycle cleanup may remove the referenced statuses first.
      return;
    }
    [
      'lesson_status' => $lesson_status,
      'course_status' => $course_status,
      'course' => $course,
      'lesson' => $lesson,
      'activity' => $activity,
    ] = $context;
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');

    $archive = $storage->create([
      'uid' => $answer->getUserId(),
      'group_id' => $course->id(),
      'course_status_id' => (string) $course_status->id(),
      'lesson_status_id' => (string) $lesson_status->id(),
      'lesson_id' => $lesson_status->getLessonId(),
      'lesson_label' => $lesson->label(),
      'activity_id' => (string) $activity->id(),
      'activity_label' => $activity->label(),
      'answer_uuid' => $answer->uuid(),
      'event_type' => $event_type,
      'answer_data' => \Drupal\Component\Serialization\Json::encode($answer->getData()),
      'score' => $answer->getScore(),
      'max_score' => $this->getActivityMaxScore($lesson, $activity),
      'started' => $lesson_status->getCreatedTime(),
      'finished' => $lesson_status->getFinishedTime(),
      'answered' => $answer->getChangedTime(),
      'archived' => $this->time->getRequestTime(),
      'evaluated' => $answer->isEvaluated(),
      'answer_comments' => $this->getAnswerComments($answer),
    ]);
    $archive->save();
  }

  /**
   * Returns prior attempts for one student/course/lesson/activity, after access.
   *
   * @return \Drupal\aculta_lms\Entity\AttemptArchive[]
   *   Visible attempt records in chronological order.
   */
  public function loadPreviousAttempts(AnswerInterface $answer, CacheableMetadata $cacheability): array {
    $context = $this->getAnswerContext($answer);
    if ($context === NULL) {
      return [];
    }
    [
      'lesson_status' => $lesson_status,
      'course_status' => $course_status,
      'course' => $course,
      'activity' => $activity,
    ] = $context;
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $answer->getUserId())
      ->condition('group_id', $course->id())
      ->condition('course_status_id', (string) $course_status->id())
      ->condition('lesson_id', $lesson_status->getLessonId())
      ->condition('activity_id', (string) $activity->id())
      ->sort('archived', 'ASC');
    $records = $storage->loadMultiple($query->execute());
    $visible_records = [];
    foreach ($records as $record) {
      $access = $record->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $visible_records[] = $record;
      }
    }
    return $visible_records;
  }

  /**
   * Resolves the current response context through nullable entity references.
   *
   * @return array{lesson_status: \Drupal\lms\Entity\LessonStatusInterface, course_status: \Drupal\lms\Entity\CourseStatusInterface, course: \Drupal\lms\Entity\Bundle\CourseInterface, lesson: \Drupal\lms\Entity\LessonInterface, activity: \Drupal\lms\Entity\ActivityInterface}|null
   *   The related entities, or NULL when LMS cleanup has removed a reference.
   */
  private function getAnswerContext(AnswerInterface $answer): ?array {
    $lesson_status = $this->referencedEntity($answer, 'lesson_status');
    if (!$lesson_status instanceof \Drupal\lms\Entity\LessonStatusInterface) {
      return NULL;
    }
    $course_status = $this->referencedEntity($lesson_status, 'course_status');
    if (!$course_status instanceof CourseStatusInterface) {
      return NULL;
    }
    $course = $this->referencedEntity($course_status, CourseStatusInterface::COURSE_FIELD);
    $lesson = $this->referencedEntity($lesson_status, \Drupal\lms\Entity\LessonStatusInterface::LESSON_FIELD);
    $activity = $this->referencedEntity($answer, AnswerInterface::ACTIVITY_FIELD);
    if (!$course instanceof \Drupal\lms\Entity\Bundle\CourseInterface
      || !$lesson instanceof LessonInterface || !$activity instanceof ActivityInterface) {
      return NULL;
    }
    return [
      'lesson_status' => $lesson_status,
      'course_status' => $course_status,
      'course' => $course,
      'lesson' => $lesson,
      'activity' => $activity,
    ];
  }

  /** Returns the entity referenced by a field, or NULL if it is unavailable. */
  private function referencedEntity(object $entity, string $field_name): ?object {
    if (!method_exists($entity, 'hasField') || !$entity->hasField($field_name)) {
      return NULL;
    }
    $field = $entity->get($field_name);
    return $field instanceof FieldItemListInterface ? $field->entity : NULL;
  }

  /** Purges records attached to a course-status lifecycle. */
  public function purgeByCourseStatus(CourseStatusInterface $course_status): void {
    $this->purge($this->entityTypeManager->getStorage('aculta_lms_attempt_archive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('course_status_id', (string) $course_status->id()));
  }

  /** Purges records when a course Group is deleted. */
  public function purgeByGroup(GroupInterface $group): void {
    $this->purge($this->entityTypeManager->getStorage('aculta_lms_attempt_archive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('group_id', $group->id()));
  }

  /** Purges records when the student account is deleted. */
  public function purgeByUser(UserInterface $user): void {
    $this->purge($this->entityTypeManager->getStorage('aculta_lms_attempt_archive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $user->id()));
  }

  /**
   * Loads educator comments before the LMS answer and its comments are deleted.
   */
  private function getAnswerComments(AnswerInterface $answer): string {
    if (!$answer->hasField('comments') || $answer->get('comments')->isEmpty()) {
      return '';
    }

    $comments = [];
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $comment_ids = $comment_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'lms_answer')
      ->condition('entity_id', $answer->id())
      ->condition('field_name', 'comments')
      ->condition('status', CommentInterface::PUBLISHED)
      ->sort('created', 'ASC')
      ->execute();
    foreach ($comment_storage->loadMultiple($comment_ids) as $comment) {
      if (!$comment instanceof CommentInterface) {
        continue;
      }
      $author = $comment->getOwner();
      $author_name = $author?->label() ?? $comment->getAuthorName();
      $comments[] = [
        'author' => $author_name !== '' ? $author_name : 'Conta removida',
        'author_type' => (string) $comment->getOwnerId() === $answer->getUserId() ? 'student' : 'educator',
        'created' => $comment->getCreatedTime(),
        'body' => $comment->get('comment_body')->value,
      ];
    }

    return \Drupal\Component\Serialization\Json::encode($comments);
  }

  /** Mirrors the LMS max-score lookup from its native lesson reference field. */
  private function getActivityMaxScore(LessonInterface $lesson, ActivityInterface $activity): int {
    foreach ($lesson->get(LessonInterface::ACTIVITIES) as $item) {
      if ($item->target_id === $activity->id()) {
        return $item->getMaxScore();
      }
    }
    return 0;
  }

  /** Deletes the explicitly selected archive records. */
  private function purge(QueryInterface $query): void {
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    $records = $storage->loadMultiple($query->execute());
    foreach ($records as $record) {
      $record->delete();
    }
  }

}
