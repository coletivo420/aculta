<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    $lesson_status = $answer->getLessonStatus();
    $course_status = $lesson_status->getCourseStatus();
    $course = $course_status->getCourse();
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');

    $activity = $answer->getActivity();
    $archive = $storage->create([
      'uid' => $answer->getUserId(),
      'group_id' => $course->id(),
      'course_status_id' => (string) $course_status->id(),
      'lesson_status_id' => (string) $lesson_status->id(),
      'lesson_id' => $lesson_status->getLessonId(),
      'lesson_label' => $lesson_status->getLesson()->label(),
      'activity_id' => (string) $activity->id(),
      'activity_label' => $activity->label(),
      'answer_uuid' => $answer->uuid(),
      'event_type' => $event_type,
      'answer_data' => \Drupal\Component\Serialization\Json::encode($answer->getData()),
      'score' => $answer->getScore(),
      'max_score' => $this->getActivityMaxScore($lesson_status->getLesson(), $activity),
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
    $lesson_status = $answer->getLessonStatus();
    $course_status = $lesson_status->getCourseStatus();
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $answer->getUserId())
      ->condition('group_id', $course_status->getCourseId())
      ->condition('course_status_id', (string) $course_status->id())
      ->condition('lesson_id', $lesson_status->getLessonId())
      ->condition('activity_id', (string) $answer->getActivity()->id())
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
    foreach ($answer->get('comments')->referencedEntities() as $comment) {
      if (!$comment instanceof CommentInterface) {
        continue;
      }
      $author = $comment->getOwner();
      $comments[] = [
        'author' => $author?->label() ?? 'Conta removida',
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
