<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\aculta_lms\Entity\ProgressEntity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/** Reads a learner's course progress from the ACULTA-owned entity. */
final class EntityCourseProgressReader implements CourseProgressReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** {@inheritdoc} */
  public function current(GroupInterface $course, AccountInterface $account): ?CourseProgress {
    $matches = $this->entityTypeManager
      ->getStorage('aculta_lms_progress')
      ->loadByProperties([
        'course_id' => (int) $course->id(),
        'uid' => (int) $account->id(),
      ]);
    $progress = reset($matches);
    if ($progress === FALSE) {
      return NULL;
    }

    $status = (string) self::fieldValue($progress, 'status');
    ProgressEntity::assertValidStatus($status);
    $score = self::fieldValue($progress, 'score');

    return new CourseProgress(
      status: $status,
      finished: (bool) self::fieldValue($progress, 'finished'),
      score: is_numeric($score) ? (float) $score : NULL,
      cacheTags: $progress->getCacheTags(),
    );
  }

  /** Returns the first scalar value of an entity field. */
  private static function fieldValue(\Drupal\Core\Entity\ContentEntityInterface $entity, string $field): mixed {
    $values = $entity->get($field)->getValue();
    return $values[0]['value'] ?? NULL;
  }

}
