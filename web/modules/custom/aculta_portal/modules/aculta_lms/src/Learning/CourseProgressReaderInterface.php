<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Lê o progresso atual de um aluno em um curso (ADR-042, decisão 6).
 *
 * O contrato não expõe classes do LMS contrib. Quem implementa decide a fonte.
 */
interface CourseProgressReaderInterface {

  /**
   * Progresso atual, ou NULL quando não há registro para o aluno.
   */
  public function current(GroupInterface $course, AccountInterface $account): ?CourseProgress;

}
