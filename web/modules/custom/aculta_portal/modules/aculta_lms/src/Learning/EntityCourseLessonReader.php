<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Reads course lessons from ACULTA-owned entities. */
final class EntityCourseLessonReader implements CourseLessonReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** {@inheritdoc} */
  public function forCourse(int $courseId): array {
    $entities = $this->entityTypeManager
      ->getStorage('aculta_lms_lesson')
      ->loadByProperties(['course_id' => $courseId]);
    $lessons = [];
    foreach ($entities as $entity) {
      $lessons[] = new CourseLesson(
        id: (int) $entity->id(),
        courseId: (int) self::fieldValue($entity, 'course_id'),
        title: (string) self::fieldValue($entity, 'title'),
        published: (bool) self::fieldValue($entity, 'published'),
        weight: (int) self::fieldValue($entity, 'weight'),
      );
    }
    usort($lessons, static fn(CourseLesson $a, CourseLesson $b): int => [$a->weight, $a->id] <=> [$b->weight, $b->id]);

    return $lessons;
  }

  private static function fieldValue(ContentEntityInterface $entity, string $field): mixed {
    $values = $entity->get($field)->getValue();
    return $values[0]['value'] ?? NULL;
  }

}
