<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Leitura das respostas de um aluno a uma atividade pelo contrato ACULTA (ADR-042, P1).
 * A implementação ativa lê `aculta_lms_answer`.
 */
interface CourseAnswerReaderInterface {

  /**
   * @return \Drupal\aculta_lms\Learning\CourseAnswer[]
   */
  public function forStudent(int $activityId, int $uid): array;

}
