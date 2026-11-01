<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts friendly LMS slugs in route parameters to entities and positions.
 *
 * Parameters are converted in route order, so `lesson_delta` and
 * `activity_delta` receive the already-converted course and lesson.
 */
final class LmsFriendlySlugConverter implements ParamConverterInterface {

  public const TYPE_COURSE = 'aculta_portal.lms_course_slug';
  public const TYPE_LESSON = 'aculta_portal.lms_lesson_slug';
  public const TYPE_ACTIVITY = 'aculta_portal.lms_activity_slug';

  public function __construct(private readonly LmsFriendlySlugs $slugs) {}

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return in_array($definition['type'] ?? NULL, [self::TYPE_COURSE, self::TYPE_LESSON, self::TYPE_ACTIVITY], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults): mixed {
    // Route defaults (activity_delta: 0) reach here as integers; they are already positions.
    if (!is_string($value)) {
      return $value;
    }
    if ($value === '') {
      return NULL;
    }
    return match ($definition['type']) {
      self::TYPE_COURSE => $this->slugs->courseFromSlug($value),
      self::TYPE_LESSON => $this->lessonDelta($defaults['group'] ?? NULL, $value),
      self::TYPE_ACTIVITY => $this->activityDelta($defaults['group'] ?? NULL, $defaults['lesson_delta'] ?? NULL, $value),
      default => NULL,
    };
  }

  private function lessonDelta(mixed $course, string $slug): ?int {
    return $course instanceof CourseInterface ? $this->slugs->lessonDeltaFromSlug($course, $slug) : NULL;
  }

  private function activityDelta(mixed $course, mixed $lessonDelta, string $slug): ?int {
    if (!$course instanceof CourseInterface || !is_int($lessonDelta)) {
      return NULL;
    }
    $lesson = $this->slugs->lessonAt($course, $lessonDelta);
    return $lesson === NULL ? NULL : $this->slugs->activityDeltaFromSlug($lesson, $slug);
  }

}
