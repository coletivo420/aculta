<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Learning\CourseActivity;

/**
 * Exact-set answer scorer for the stable select activity type. */
final class SelectAnswerPlugin extends ObjectiveAnswerPluginBase {

  public const ACTIVITY_TYPE = 'select';

  /**
   * Calcula a nota da resposta de seleção exata.
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
   */
  public function score(CourseActivity $activity, mixed $answer, mixed $correct): float {
    $this->assertActivityType($activity);
    $selected = $this->stringList($answer ?? [], 'Resposta selecionada');
    $expected = $this->stringList($correct, 'Gabarito de seleção');
    return $this->scorer->select($selected, $expected);
  }

}
