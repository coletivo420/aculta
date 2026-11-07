<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Learning\CourseActivity;

/**
 * Resolves the ACULTA objective-answer plugin from CourseActivity's stable type. */
final class ObjectiveAnswerPluginResolver {

  /**
   * @var array<string, \Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginInterface> */
  private readonly array $plugins;

  public function __construct(
    TrueFalseAnswerPlugin $trueFalse,
    SelectAnswerPlugin $select,
    FillBlanksAnswerPlugin $fillBlanks,
  ) {
    $this->plugins = [
      $trueFalse->activityType() => $trueFalse,
      $select->activityType() => $select,
      $fillBlanks->activityType() => $fillBlanks,
    ];
  }

  /**
   *
   */
  public function forActivity(CourseActivity $activity): ObjectiveAnswerPluginInterface {
    if (!isset($this->plugins[$activity->type])) {
      throw new \UnexpectedValueException(sprintf('Não há plugin de resposta objetiva para o tipo %s.', $activity->type));
    }
    return $this->plugins[$activity->type];
  }

  /**
   *
   */
  public function score(CourseActivity $activity, mixed $answer, mixed $correct): float {
    return $this->forActivity($activity)->score($activity, $answer, $correct);
  }

}
