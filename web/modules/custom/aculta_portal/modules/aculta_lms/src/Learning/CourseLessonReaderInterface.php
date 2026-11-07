<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Leitura das aulas de um curso pelo contrato ACULTA (ADR-042, P1).
 *
 * A implementação ativa lê a entidade própria `aculta_lms_lesson`. Quem
 * consome só conhece este contrato.
 */
interface CourseLessonReaderInterface {

  /**
   * Devolve as aulas do curso em ordem de posição.
   *
   * @return \Drupal\aculta_lms\Learning\CourseLesson[]
   *   Aulas do curso em ordem de posição.
   */
  public function forCourse(int $courseId): array;

}
