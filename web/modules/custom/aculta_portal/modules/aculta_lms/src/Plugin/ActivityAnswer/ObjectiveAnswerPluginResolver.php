<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Learning\CourseActivity;

/**
 * Resolves the ACULTA objective-answer plugin from CourseActivity's stable type. */
final class ObjectiveAnswerPluginResolver {

  /**
   * Plugins de resposta objetiva indexados pelo tipo de atividade.
   *
   * @var array<string, \Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginInterface>
   */
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
   * Retorna o plugin de resposta objetiva do tipo da atividade.
   *
   * @param \Drupal\aculta_lms\Learning\CourseActivity $activity
   *   Atividade cujo tipo define o plugin.
   *
   * @return \Drupal\aculta_lms\Plugin\ActivityAnswer\ObjectiveAnswerPluginInterface
   *   Plugin de resposta objetiva correspondente.
   */
  public function forActivity(CourseActivity $activity): ObjectiveAnswerPluginInterface {
    if (!isset($this->plugins[$activity->type])) {
      throw new \UnexpectedValueException(sprintf('Não há plugin de resposta objetiva para o tipo %s.', $activity->type));
    }
    return $this->plugins[$activity->type];
  }

  /**
   * Calcula a nota da resposta usando o plugin do tipo da atividade.
   *
   * @param \Drupal\aculta_lms\Learning\CourseActivity $activity
   *   Atividade respondida.
   * @param mixed $answer
   *   Resposta enviada pela pessoa.
   * @param mixed $correct
   *   Gabarito da atividade, quando houver.
   *
   * @return float
   *   Nota calculada para a resposta.
   */
  public function score(CourseActivity $activity, mixed $answer, mixed $correct): float {
    return $this->forActivity($activity)->score($activity, $answer, $correct);
  }

}
