<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Learning\CourseActivity;

/**
 * Scores an ACULTA activity answer without depending on LMS contrib entities. */
interface ObjectiveAnswerPluginInterface {

  /**
   * Scores one answer against the correct answer data for the activity.
   *
   * @param mixed $answer
   *   Student answer in the type-specific ACULTA format.
   * @param mixed $correct
   *   Correct answer data in the type-specific ACULTA format.
   */
  public function score(CourseActivity $activity, mixed $answer, mixed $correct): float;

  /**
   * Returns the stable CourseActivity type handled by this plugin. */
  public function activityType(): string;

}
