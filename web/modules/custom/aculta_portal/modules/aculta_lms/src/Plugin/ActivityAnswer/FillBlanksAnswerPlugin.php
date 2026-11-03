<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Learning\CourseActivity;

/** Normalized blank-answer scorer for the stable fill_blanks activity type. */
final class FillBlanksAnswerPlugin extends ObjectiveAnswerPluginBase {

  public const ACTIVITY_TYPE = 'fill_blanks';

  public function score(CourseActivity $activity, mixed $answer, mixed $correct): float {
    $this->assertActivityType($activity);
    $answers = $this->stringMap($answer ?? [], 'Respostas das lacunas');
    $accepted = $this->stringLists($correct, 'Gabarito das lacunas');
    return $this->scorer->fillInBlanks($answers, $accepted);
  }

  /** @return array<int|string, string> */
  private function stringMap(mixed $value, string $label): array {
    if (!is_array($value)) {
      throw new \InvalidArgumentException(sprintf('%s deve ser um mapa de textos.', $label));
    }
    foreach ($value as $item) {
      if (!is_string($item)) {
        throw new \InvalidArgumentException(sprintf('%s deve conter apenas textos.', $label));
      }
    }
    return $value;
  }

  /** @return array<int|string, string[]> */
  private function stringLists(mixed $value, string $label): array {
    if (!is_array($value)) {
      throw new \InvalidArgumentException(sprintf('%s deve ser um mapa de listas.', $label));
    }
    foreach ($value as $options) {
      $this->stringList($options, $label);
    }
    return $value;
  }

}
