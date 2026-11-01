<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Learning;

use Drupal\Core\Url;

/**
 * Authorized learner-facing data for one course.
 */
final readonly class StudentLearningCourse {

  public function __construct(
    public string $id,
    public string $label,
    public ?Url $continueUrl,
    public int $completedLessons,
    public int $totalLessons,
    public int $progressPercent,
    public bool $pendingEvaluation,
    public bool $started,
    public bool $finished,
    public ?string $currentLessonLabel,
    public bool $currentLessonFinished,
  ) {}

}
