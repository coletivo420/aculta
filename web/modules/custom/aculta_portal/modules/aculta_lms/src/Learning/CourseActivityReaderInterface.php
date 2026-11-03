<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Leitura das atividades de uma aula pelo contrato ACULTA (ADR-042, P1).
 * A implementação ativa lê `aculta_lms_activity`.
 */
interface CourseActivityReaderInterface {

  /**
   * @return \Drupal\aculta_lms\Learning\CourseActivity[]
   */
  public function forLesson(int $lessonId): array;

}
