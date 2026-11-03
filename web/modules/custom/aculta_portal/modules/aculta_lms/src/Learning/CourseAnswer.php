<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Resposta de um aluno a uma atividade, no vocabulário ACULTA (ADR-042, P1).
 *
 * Valor imutável. A pontuação é opcional: atividades sem correção automática
 * ficam sem nota até o educador avaliar.
 */
final class CourseAnswer {

  public const STATE_SUBMITTED = 'submitted';
  public const STATE_GRADED = 'graded';

  /**
   * @param int $id
   *   Identificador da resposta na fonte atual.
   * @param int $activityId
   *   Atividade respondida.
   * @param int $uid
   *   Aluno que respondeu.
   * @param string $state
   *   STATE_SUBMITTED ou STATE_GRADED.
   * @param float|null $score
   *   Nota de 0 a 100, ou NULL quando ainda não há nota.
   */
  public function __construct(
    public readonly int $id,
    public readonly int $activityId,
    public readonly int $uid,
    public readonly string $state,
    public readonly ?float $score,
  ) {
    if ($id <= 0 || $activityId <= 0 || $uid <= 0) {
      throw new \InvalidArgumentException('Identificadores de resposta, atividade e aluno devem ser positivos.');
    }
    if (!in_array($state, [self::STATE_SUBMITTED, self::STATE_GRADED], TRUE)) {
      throw new \InvalidArgumentException('Estado de resposta desconhecido.');
    }
    if ($score !== NULL && ($score < 0.0 || $score > 100.0)) {
      throw new \InvalidArgumentException('Nota fora da faixa de 0 a 100.');
    }
    if ($state === self::STATE_GRADED && $score === NULL) {
      throw new \InvalidArgumentException('Resposta avaliada exige nota.');
    }
  }

}
