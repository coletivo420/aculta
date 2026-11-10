<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms\Entity\AttemptArchive;
use Drupal\aculta_lms\Learning\CourseAnswer;
use Drupal\comment\CommentInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

/**
 * Archives previous ACULTA answer states and exposes access-checked history. */
final class AttemptArchiveManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Archives an immutable ACULTA attempt before it is explicitly deleted.
   */
  public function archiveAnswer(ContentEntityInterface $answer): void {
    $this->storeSnapshot($answer, 'deleted');
  }

  /**
   * Archives the persisted grade before an ACULTA answer is reevaluated.
   */
  public function archivePreviousAnswer(ContentEntityInterface $answer): void {
    if ($answer->isNew()) {
      return;
    }
    $original = $this->entityTypeManager->getStorage('aculta_lms_answer')->loadUnchanged($answer->id());
    if (!$original instanceof ContentEntityInterface) {
      return;
    }
    $original_score = $this->fieldValue($original, 'score');
    $new_score = $this->fieldValue($answer, 'score');
    $original_state = $this->fieldValue($original, 'state');
    $new_state = $this->fieldValue($answer, 'state');
    if ($original_score === $new_score && $original_state === $new_state) {
      return;
    }

    $this->storeSnapshot($original, 'evaluation_changed');
  }

  /**
   * Stores an immutable copy of an ACULTA AnswerEntity's prior state.
   */
  private function storeSnapshot(ContentEntityInterface $answer, string $event_type): void {
    $context = $this->getAnswerContext($answer);
    if ($context === NULL) {
      return;
    }
    ['course' => $course, 'lesson' => $lesson, 'activity' => $activity] = $context;

    $score_value = $this->fieldValue($answer, 'score');
    $score = $score_value === NULL ? 0.0 : (float) $score_value;
    $evaluated = $this->fieldValue($answer, 'state') === CourseAnswer::STATE_GRADED;
    $created = (int) ($this->fieldValue($answer, 'created') ?? 0);
    $answer_data = Json::encode(Json::decode((string) ($this->fieldValue($answer, 'answer_data') ?: '{}')));
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    $archive = $storage->create([
      'uid' => (int) $this->fieldValue($answer, 'uid'),
      'group_id' => (string) $course->id(),
      // P2 stores course-level progress only; there is no separate course or
      // lesson attempt-status entity in the ACULTA model.
      'course_status_id' => '',
      'lesson_status_id' => '',
      'lesson_id' => (string) $lesson->id(),
      'lesson_label' => (string) $lesson->get('title')->value,
      'activity_id' => (string) $activity->id(),
      'activity_label' => (string) $activity->get('type')->value,
      'answer_uuid' => $answer->uuid(),
      'event_type' => $event_type,
      'answer_data' => $answer_data,
      // Keep the original integer field compatible with existing archive rows.
      'score' => (int) round($score),
      'score_precise' => $score,
      'max_score' => $evaluated ? 100 : 0,
      'started' => 0,
      'finished' => 0,
      'answered' => $created,
      'archived' => $this->time->getRequestTime(),
      'evaluated' => $evaluated,
      'answer_comments' => $this->getAnswerComments($answer),
    ]);
    $archive->save();
  }

  /**
   * Returns previous attempts for one student/course/activity after access.
   *
   * @return \Drupal\aculta_lms\Entity\AttemptArchive[]
   *   Visible attempt records in chronological order.
   */
  public function loadPreviousAttempts(ContentEntityInterface $answer, CacheableMetadata $cacheability): array {
    $context = $this->getAnswerContext($answer);
    if ($context === NULL) {
      return [];
    }
    ['course' => $course, 'activity' => $activity] = $context;
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', (string) $this->fieldValue($answer, 'uid'))
      ->condition('group_id', (string) $course->id())
      ->condition('activity_id', (string) $activity->id())
      ->sort('archived', 'ASC');
    $records = $storage->loadMultiple($query->execute());
    $visible_records = [];
    foreach ($records as $record) {
      if (!$record instanceof AttemptArchive) {
        continue;
      }
      $access = $record->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $visible_records[] = $record;
      }
    }
    return $visible_records;
  }

  /**
   * Resolves ACULTA answer → activity → lesson → Group course references.
   *
   * @return array{course: GroupInterface, lesson: ContentEntityInterface, activity: ContentEntityInterface}|null
   *   The related entities, or NULL when a reference is unavailable.
   */
  private function getAnswerContext(ContentEntityInterface $answer): ?array {
    $activity = $this->entityTypeManager->getStorage('aculta_lms_activity')
      ->load($this->fieldValue($answer, 'activity_id'));
    if (!$activity instanceof ContentEntityInterface) {
      return NULL;
    }
    $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')
      ->load($this->fieldValue($activity, 'lesson_id'));
    if (!$lesson instanceof ContentEntityInterface) {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')
      ->load($this->fieldValue($lesson, 'course_id'));
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      return NULL;
    }
    return ['course' => $course, 'lesson' => $lesson, 'activity' => $activity];
  }

  /**
   * Reads a scalar field value from a content entity, if present. */
  private function fieldValue(ContentEntityInterface $entity, string $field_name): mixed {
    if (!$entity->hasField($field_name)) {
      return NULL;
    }
    $field = $entity->get($field_name);
    return !$field->isEmpty() ? $field->value : NULL;
  }

  /**
   * Purges attempt records attached to a course Group.
   */
  public function purgeByGroup(GroupInterface $group): void {
    $this->purge($this->entityTypeManager->getStorage('aculta_lms_attempt_archive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('group_id', (string) $group->id()));
  }

  /**
   * Purges attempt records when the student account is deleted.
   */
  public function purgeByUser(UserInterface $user): void {
    $this->purge($this->entityTypeManager->getStorage('aculta_lms_attempt_archive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', (string) $user->id()));
  }

  /**
   * Loads published Core comments attached to an ACULTA answer. */
  private function getAnswerComments(ContentEntityInterface $answer): string {
    if (!$answer->hasField('comments') || $answer->get('comments')->isEmpty()) {
      return '';
    }

    $comments = [];
    $comment_storage = $this->entityTypeManager->getStorage('comment');
    $comment_ids = $comment_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'aculta_lms_answer')
      ->condition('entity_id', $answer->id())
      ->condition('field_name', 'comments')
      ->condition('status', CommentInterface::PUBLISHED)
      ->sort('created', 'ASC')
      ->execute();
    foreach ($comment_storage->loadMultiple($comment_ids) as $comment) {
      $author = $comment->getOwner();
      $author_name = $author->label();
      $comments[] = [
        'author' => $author_name !== '' ? $author_name : 'Conta removida',
        'author_type' => (string) $comment->getOwnerId() === (string) $this->fieldValue($answer, 'uid') ? 'student' : 'educator',
        'created' => $comment->getCreatedTime(),
        'body' => $comment->get('comment_body')->value,
      ];
    }

    return Json::encode($comments);
  }

  /**
   * Deletes the explicitly selected archive records. */
  private function purge(QueryInterface $query): void {
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    foreach ($storage->loadMultiple($query->execute()) as $record) {
      $record->delete();
    }
  }

}
