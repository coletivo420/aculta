<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * Situação atual de cada aluno por curso, emitida no cron do LMS para a série por aluno.
 *
 * Só entram alunos que aceitaram o aceite único (Termos e Política). Cada linha traz `course`, `uid`, `situation`
 * (not_started, in_progress, pending_evaluation, passed, failed). Esse evento carrega dado pessoal (`uid`):
 * o consumidor só pode guardá-lo com a finalidade descrita na política, e apagá-lo quando a conta é excluída.
 */
final readonly class StudentStatusSnapshotEvent {

  public const NAME = 'aculta_lms.student_status_snapshot';

  /**
   * @param list<array{course: int, uid: int, situation: string}> $rows
   *   Uma linha por aluno e curso.
   */
  public function __construct(
    public array $rows,
  ) {}

}
