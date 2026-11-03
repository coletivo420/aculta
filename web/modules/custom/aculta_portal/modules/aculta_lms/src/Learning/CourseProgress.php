<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Progresso atual de um aluno em um curso, no vocabulário ACULTA (ADR-042, decisão 6).
 *
 * Os valores de status mantêm o contrato ACULTA durante a convivência com o
 * contrib; a leitura ativa usa a entidade própria `aculta_lms_progress`.
 */
final class CourseProgress {

  public const STATUS_PROGRESS = 'in progress';
  public const STATUS_PASSED = 'passed';
  public const STATUS_FAILED = 'failed';
  public const STATUS_NEEDS_EVALUATION = 'evaluation';

  /**
   * @param string[] $cacheTags
   * @param int|null $changedTime
   *   Timestamp of the most recent progress update, when available.
   */
  public function __construct(
    public readonly string $status,
    public readonly bool $finished,
    public readonly ?float $score,
    public readonly array $cacheTags = [],
    public readonly ?int $changedTime = NULL,
  ) {}

}
