<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Aula de um curso, no vocabulário ACULTA (ADR-042, decisão 1 e P1).
 *
 * Valor imutável. Não carrega entidade nem tipo do LMS contrib.
 */
final class CourseLesson {

  /**
   * Constrói a aula com seus identificadores e dados de exibição.
   *
   * @param int $id
   *   Identificador da aula na fonte atual.
   * @param int $courseId
   *   Identificador do curso dono da aula.
   * @param string $title
   *   Título exibido.
   * @param bool $published
   *   Verdadeiro quando a aula está liberada para os alunos.
   * @param int $weight
   *   Posição da aula dentro do curso.
   */
  public function __construct(
    public readonly int $id,
    public readonly int $courseId,
    public readonly string $title,
    public readonly bool $published,
    public readonly int $weight,
  ) {
    if ($id <= 0 || $courseId <= 0) {
      throw new \InvalidArgumentException('Identificadores de aula e curso devem ser positivos.');
    }
    if ($weight < 0) {
      throw new \InvalidArgumentException('Posição da aula não pode ser negativa.');
    }
  }

}
