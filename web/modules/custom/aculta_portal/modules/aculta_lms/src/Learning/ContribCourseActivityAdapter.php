<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\LessonInterface;

/** Legacy adapter retained until the end of ADR-042 Phase A. */
final class ContribCourseActivityAdapter implements CourseActivityReaderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** {@inheritdoc} */
  public function forLesson(int $lessonId): array {
    $lesson = $this->entityTypeManager->getStorage('lms_lesson')->load($lessonId);
    if (!$lesson instanceof LessonInterface) {
      return [];
    }

    $activities = [];
    foreach ($lesson->get(LessonInterface::ACTIVITIES) as $item) {
      $activity = $item->entity;
      if ($activity instanceof ActivityInterface) {
        $activities[] = self::fromContrib($activity, $lessonId, $item->getMaxScore());
      }
    }
    return $activities;
  }

  /** Converts one contrib activity and its lesson-reference grading weight. */
  public static function fromContrib(ActivityInterface $activity, int $lessonId, int $maxScore): CourseActivity {
    $bundle = trim($activity->bundle());
    $type = strtolower((string) preg_replace('/[^a-z]+/i', '_', $bundle));
    $type = trim($type, '_');
    if ($type === '') {
      throw new \UnexpectedValueException('A atividade do LMS contrib não possui tipo.');
    }

    return new CourseActivity(
      id: (int) $activity->id(),
      lessonId: $lessonId,
      type: $type,
      graded: $maxScore > 0,
    );
  }

}
