<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Atividade de uma aula, no vocabulário ACULTA (ADR-042, P1).
 *
 * Valor imutável. O tipo é uma string estável do ACULTA (por exemplo
 * "true_false", "select", "fill_blanks", "essay", "file_upload"), não um bundle do contrib.
 */
final class CourseActivity {

  /**
   * Constrói a atividade com seus identificadores e dados de exibição.
   *
   * @param int $id
   *   Identificador da atividade na fonte atual.
   * @param int $lessonId
   *   Identificador da aula dona da atividade.
   * @param string $type
   *   Tipo estável da atividade.
   * @param bool $graded
   *   Verdadeiro quando a atividade entra na nota do curso.
   */
  public function __construct(
    public readonly int $id,
    public readonly int $lessonId,
    public readonly string $type,
    public readonly bool $graded,
  ) {
    if ($id <= 0 || $lessonId <= 0) {
      throw new \InvalidArgumentException('Identificadores de atividade e aula devem ser positivos.');
    }
    if ($type === '' || preg_match('/^[a-z_]+$/', $type) !== 1) {
      throw new \InvalidArgumentException('Tipo de atividade inválido.');
    }
  }

}
