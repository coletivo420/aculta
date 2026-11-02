<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Leitura das aulas de um curso pelo contrato ACULTA (ADR-042, P1).
 *
 * Implementações: o adaptador do LMS contrib, até o P2. Quem consome só
 * conhece este contrato.
 */
interface CourseLessonReaderInterface {

  /**
   * Devolve as aulas do curso em ordem de posição.
   *
   * @return \Drupal\aculta_lms\Learning\CourseLesson[]
   */
  public function forCourse(int $courseId): array;

}
