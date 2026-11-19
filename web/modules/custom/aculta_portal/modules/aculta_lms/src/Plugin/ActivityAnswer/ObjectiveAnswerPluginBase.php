<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_lms\Assessment\ObjectiveAnswerScorer;
use Drupal\aculta_lms\Learning\CourseActivity;

/**
 * Shared scorer dependency and stable-type check for objective plugins. */
abstract class ObjectiveAnswerPluginBase implements ObjectiveAnswerPluginInterface {

  /**
   * Stable type identifier overridden by each concrete answer plugin. */
  protected const ACTIVITY_TYPE = '';

  public function __construct(
    protected readonly ObjectiveAnswerScorer $scorer,
  ) {}

  /**
   * Devolve o identificador de tipo estável do plugin.
   *
   * @return string
   *   Identificador do tipo de atividade.
   */
  public function activityType(): string {
    return static::ACTIVITY_TYPE;
  }

  /**
   * Garante que a atividade é do tipo aceito pelo plugin.
   *
   * @param \Drupal\aculta_lms\Learning\CourseActivity $activity
   *   Atividade a validar.
   *
   * @throws \UnexpectedValueException
   *   Quando o tipo da atividade não corresponde ao do plugin.
   */
  protected function assertActivityType(CourseActivity $activity): void {
    if ($activity->type !== static::ACTIVITY_TYPE) {
      throw new \UnexpectedValueException(sprintf('Plugin %s não aceita atividade do tipo %s.', static::ACTIVITY_TYPE, $activity->type));
    }
  }

  /**
   * Valida e converte um valor em lista de textos.
   *
   * @param mixed $value
   *   Valor recebido.
   * @param string $label
   *   Rótulo usado nas mensagens de erro.
   *
   * @return string[]
   *   Lista de textos validada.
   *
   * @throws \InvalidArgumentException
   *   Quando o valor não é uma lista de textos.
   */
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
