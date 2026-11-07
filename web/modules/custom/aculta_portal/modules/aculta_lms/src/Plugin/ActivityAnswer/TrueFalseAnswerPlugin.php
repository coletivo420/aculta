<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Learning\CourseActivity;

/**
 * Answer scorer for the stable true_false activity type. */
final class TrueFalseAnswerPlugin extends ObjectiveAnswerPluginBase {

  public const ACTIVITY_TYPE = 'true_false';

  /**
   *
   */
  public function score(CourseActivity $activity, mixed $answer, mixed $correct): float {
    $this->assertActivityType($activity);
    if ($answer === NULL) {
      return 0.0;
    }
    if (!is_bool($answer) || !is_bool($correct)) {
      throw new \InvalidArgumentException('Resposta e gabarito verdadeiro/falso devem ser booleanos.');
    }
    return $this->scorer->trueFalse($answer, $correct);
  }

}
