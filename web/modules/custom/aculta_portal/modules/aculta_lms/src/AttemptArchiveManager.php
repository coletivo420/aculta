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
    $score = self::numericValue($score_value) ?? 0.0;
    $evaluated = $this->fieldValue($answer, 'state') === CourseAnswer::STATE_GRADED;
    $created = self::integerValue($this->fieldValue($answer, 'created'));
    $rawAnswerData = self::stringValue($this->fieldValue($answer, 'answer_data'), '{}');
    $answer_data = Json::encode(Json::decode($rawAnswerData));
    $courseId = self::entityIdString($course->id());
    $lessonId = self::entityIdString($lesson->id());
    $activityId = self::entityIdString($activity->id());
    if ($courseId === '' || $lessonId === '' || $activityId === '') {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    $archive = $storage->create([
      'uid' => self::integerValue($this->fieldValue($answer, 'uid')),
      'group_id' => $courseId,
      // P2 stores course-level progress only; there is no separate course or
      // lesson attempt-status entity in the ACULTA model.
      'course_status_id' => '',
      'lesson_status_id' => '',
      'lesson_id' => $lessonId,
      'lesson_label' => self::stringValue($lesson->get('title')->value),
      'activity_id' => $activityId,
      'activity_label' => self::stringValue($activity->get('type')->value),
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
    $userId = self::stringValue($this->fieldValue($answer, 'uid'));
    $courseId = self::entityIdString($course->id());
    $activityId = self::entityIdString($activity->id());
    if ($userId === '' || $courseId === '' || $activityId === '') {
      return [];
    }
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $userId)
      ->condition('group_id', $courseId)
      ->condition('activity_id', $activityId)
      ->sort('archived', 'ASC');
    $records = $storage->loadMultiple(self::entityIds($query->execute()));
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
    $activityId = self::storageId($this->fieldValue($answer, 'activity_id'));
    if ($activityId === NULL) {
      return NULL;
    }
    $activity = $this->entityTypeManager->getStorage('aculta_lms_activity')->load($activityId);
    if (!$activity instanceof ContentEntityInterface) {
      return NULL;
    }
    $lessonId = self::storageId($this->fieldValue($activity, 'lesson_id'));
    if ($lessonId === NULL) {
      return NULL;
    }
    $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->load($lessonId);
    if (!$lesson instanceof ContentEntityInterface) {
      return NULL;
    }
    $courseId = self::storageId($this->fieldValue($lesson, 'course_id'));
    if ($courseId === NULL) {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')->load($courseId);
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
   * Reads a storage ID only when the value has an accepted scalar shape.
   */
  private static function storageId(mixed $value): int|string|null {
    if (is_int($value)) {
      return $value > 0 ? $value : NULL;
    }
    return is_string($value) && $value !== '' ? $value : NULL;
  }

  /**
   * Converts a validated storage ID to its persisted string representation.
   */
  private static function entityIdString(mixed $value): string {
    $id = self::storageId($value);
    return $id === NULL ? '' : (string) $id;
  }

  /**
   * Converts a numeric field value to a finite float when possible.
   */
  private static function numericValue(mixed $value): ?float {
    if (!is_int($value) && !is_float($value) && !is_string($value)) {
      return NULL;
    }
    if (!is_numeric($value)) {
      return NULL;
    }
    $number = (float) $value;
    return is_finite($number) ? $number : NULL;
  }

  /**
   * Converts an integer field value, defaulting malformed values to zero.
   */
  private static function integerValue(mixed $value): int {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value)) {
      $integer = filter_var($value, FILTER_VALIDATE_INT);
      return $integer === FALSE ? 0 : $integer;
    }
    return 0;
  }

  /**
   * Converts scalar field values to text without stringifying structures.
   */
  private static function stringValue(mixed $value, string $default = ''): string {
    if (is_scalar($value)) {
      return (string) $value;
    }
    return $value instanceof \Stringable ? (string) $value : $default;
  }

  /**
   * Narrows query execution results to loadable entity IDs.
   *
   * @return list<int|string>
   *   Valid integer and non-empty string IDs.
   */
  private static function entityIds(mixed $ids): array {
    if (!is_array($ids)) {
      return [];
    }
    $validIds = [];
    foreach ($ids as $id) {
      if (is_int($id) && $id > 0) {
        $validIds[] = $id;
      }
      elseif (is_string($id) && $id !== '') {
        $validIds[] = $id;
      }
    }
    return $validIds;
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
    foreach ($comment_storage->loadMultiple(self::entityIds($comment_ids)) as $comment) {
      $author = $comment->getOwner();
      $author_name = self::stringValue($author->label());
      $ownerId = self::storageId($comment->getOwnerId());
      $studentId = self::storageId($this->fieldValue($answer, 'uid'));
      $comments[] = [
        'author' => $author_name !== '' ? $author_name : 'Conta removida',
        'author_type' => $ownerId !== NULL && $studentId !== NULL && (string) $ownerId === (string) $studentId ? 'student' : 'educator',
        'created' => $comment->getCreatedTime(),
        'body' => self::stringValue($comment->get('comment_body')->value),
      ];
    }

    return Json::encode($comments);
  }

  /**
   * Deletes the explicitly selected archive records. */
  private function purge(QueryInterface $query): void {
    $storage = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive');
    foreach ($storage->loadMultiple(self::entityIds($query->execute())) as $record) {
      if ($record instanceof AttemptArchive) {
        $record->delete();
      }
    }
  }

}
