<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Assessment\ObjectiveAnswerScorer;
use Drupal\aculta_lms\Learning\CourseActivity;

/** Shared scorer dependency and stable-type check for objective plugins. */
abstract class ObjectiveAnswerPluginBase implements ObjectiveAnswerPluginInterface {

  public function __construct(
    protected readonly ObjectiveAnswerScorer $scorer,
  ) {}

  public function activityType(): string {
    return static::ACTIVITY_TYPE;
  }

  protected function assertActivityType(CourseActivity $activity): void {
    if ($activity->type !== static::ACTIVITY_TYPE) {
      throw new \UnexpectedValueException(sprintf('Plugin %s não aceita atividade do tipo %s.', static::ACTIVITY_TYPE, $activity->type));
    }
  }

  /** @return string[] */
  protected function stringList(mixed $value, string $label): array {
    if (!is_array($value)) {
      throw new \InvalidArgumentException(sprintf('%s deve ser uma lista de textos.', $label));
    }
    foreach ($value as $item) {
      if (!is_string($item)) {
        throw new \InvalidArgumentException(sprintf('%s deve conter apenas textos.', $label));
      }
    }
    return array_values($value);
  }

}
