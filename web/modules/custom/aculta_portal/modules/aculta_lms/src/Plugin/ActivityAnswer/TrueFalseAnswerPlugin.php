<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Learning\CourseActivity;

/**
 * Answer scorer for the stable true_false activity type. */
final class TrueFalseAnswerPlugin extends ObjectiveAnswerPluginBase {

  public const ACTIVITY_TYPE = 'true_false';

  /**
   * Calcula a nota da resposta verdadeiro/falso.
   *
   * @param \Drupal\aculta_lms\Learning\CourseActivity $activity
   *   Atividade respondida.
   * @param mixed $answer
   *   Resposta enviada pelo aluno.
   * @param mixed $correct
   *   Gabarito da atividade.
   *
   * @return float
   *   Nota calculada para a resposta.
   *
   * @throws \InvalidArgumentException
   *   Quando resposta e gabarito não são booleanos.
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
