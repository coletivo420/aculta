<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\LessonInterface;

/** Legacy adapter retained until the end of ADR-042 Phase A. */
final class ContribCourseLessonAdapter implements CourseLessonReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** {@inheritdoc} */
  public function forCourse(int $courseId): array {
    $course = $this->entityTypeManager->getStorage('group')->load($courseId);
    if (!$course instanceof CourseInterface) {
      return [];
    }

    $lessons = [];
    foreach ($course->get(CourseInterface::LESSONS) as $weight => $item) {
      $lesson = $item->entity;
      if ($lesson instanceof LessonInterface) {
        $lessons[] = self::fromContrib($lesson, $courseId, (int) $weight);
      }
    }
    return $lessons;
  }

  /** Converts one contrib lesson using its owning course reference position. */
  public static function fromContrib(LessonInterface $lesson, int $courseId, int $weight): CourseLesson {
    $status = $lesson->get('status')->first();
    return new CourseLesson(
      id: (int) $lesson->id(),
      courseId: $courseId,
      title: (string) $lesson->label(),
      published: (bool) ($status?->getValue()['value'] ?? FALSE),
      weight: $weight,
    );
  }

}
