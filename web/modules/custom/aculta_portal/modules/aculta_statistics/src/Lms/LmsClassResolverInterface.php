<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Lms;

/**
 * Turma de uma conta dentro de um curso, quando há exatamente uma.
 */
interface LmsClassResolverInterface {

  /**
   * Identificador da turma da conta no curso, ou NULL quando não é único.
   */
  public function classIdFor(int $courseId, int $accountId): ?int;

}
