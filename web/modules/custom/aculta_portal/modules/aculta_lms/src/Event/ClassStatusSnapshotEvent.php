<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * Instantâneo diário da situação dos alunos por curso e turma, emitido pelo cron do LMS.
 *
 * Só contagens agregadas: nenhum aluno, nota, resposta ou progresso individual. Cada linha traz
 * `course` e `class` (identificadores), `situation` (students, not_started, in_progress,
 * pending_evaluation, passed, failed) e `count`. O consumidor substitui o dia inteiro, então rodar
 * de novo o cron no mesmo dia não duplica.
 */
final readonly class ClassStatusSnapshotEvent {

  public const NAME = 'aculta_lms.class_status_snapshot';

  /**
   * @param list<array{course: int, class: int, situation: string, count: int}> $rows
   *   Uma linha por turma e situação.
   */
  public function __construct(
    public array $rows,
  ) {}

}
