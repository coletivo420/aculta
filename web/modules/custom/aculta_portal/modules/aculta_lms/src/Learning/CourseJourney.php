<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;

/**
 * Authorized course structure and progress for the Ganjalab experience.
 *
 * The journey is a presentation contract owned by ACULTA Studio. A learning
 * provider (currently the Drupal LMS adapter in aculta_lms) resolves access,
 * curriculum and progress and returns this value object. Consumers render it
 * without querying LMS entities, Group grants or training services.
 */
final readonly class CourseJourney {

  /**
   * Builds one course journey view model.
   *
   * @param bool $access
   *   Whether the account may take the course.
   * @param string $courseId
   *   Course identifier owned by the learning provider.
   * @param string $courseLabel
   *   Human-readable course label.
   * @param array<string, mixed> $description
   *   Render array of the course description, already cacheable.
   * @param bool $started
   *   Whether the account has a learning status for the course.
   * @param bool $finished
   *   Whether the provider records the course as finished.
   * @param bool $pendingEvaluation
   *   Whether the course awaits educator evaluation.
   * @param string|null $currentLessonId
   *   Identifier of the resumable lesson, when one exists.
   * @param string|null $currentLessonLabel
   *   Label of the resumable lesson, when one exists.
   * @param int|null $currentLessonIndex
   *   One-based position of the resumable lesson.
   * @param int $totalLessons
   *   Number of published lessons in the curriculum.
   * @param int $completedLessons
   *   Number of published lessons marked finished.
   * @param int $progressPercent
   *   Progress derived from the completed and total lessons.
   * @param array<int, \Drupal\aculta_lms\Learning\CourseJourneyLesson> $lessons
   *   Ordered, authorized lessons.
   * @param \Drupal\Core\Url|null $continueUrl
   *   Canonical "continue" destination, when the provider offers one.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cache metadata accumulated while resolving the journey.
   */
  public function __construct(
    public bool $access,
    public string $courseId,
    public string $courseLabel,
    public array $description,
    public bool $started,
    public bool $finished,
    public bool $pendingEvaluation,
    public ?string $currentLessonId,
    public ?string $currentLessonLabel,
    public ?int $currentLessonIndex,
    public int $totalLessons,
    public int $completedLessons,
    public int $progressPercent,
    public array $lessons,
    public ?Url $continueUrl,
    public CacheableMetadata $cache,
  ) {}

  /**
   * Tells whether the account may take this course.
   */
  public function hasAccess(): bool {
    return $this->access;
  }

  /**
   * Finds one published lesson by its curriculum delta.
   */
  public function lessonByDelta(int $delta): ?CourseJourneyLesson {
    foreach ($this->lessons as $lesson) {
      if ($lesson->delta === $delta) {
        return $lesson;
      }
    }
    return NULL;
  }

  /**
   * Returns the one-based position of a lesson in the published curriculum.
   */
  public function lessonPosition(int $delta): ?int {
    $position = 0;
    foreach ($this->lessons as $lesson) {
      $position++;
      if ($lesson->delta === $delta) {
        return $position;
      }
    }
    return NULL;
  }

}
