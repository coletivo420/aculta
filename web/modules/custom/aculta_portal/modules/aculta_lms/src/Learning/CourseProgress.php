<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Progresso atual de um aluno em um curso, no vocabulário ACULTA (ADR-042, decisão 6).
 *
 * Os valores de status são os mesmos strings que o LMS contrib usa hoje, para que a troca de fonte
 * não mude comportamento. Quando a fonte própria do P2 entrar, só o adaptador muda.
 */
final class CourseProgress {

  public const STATUS_PROGRESS = 'in progress';
  public const STATUS_PASSED = 'passed';
  public const STATUS_FAILED = 'failed';
  public const STATUS_NEEDS_EVALUATION = 'evaluation';

  /**
   * @param string[] $cacheTags
   */
  public function __construct(
    public readonly string $status,
    public readonly bool $finished,
    public readonly ?float $score,
    public readonly array $cacheTags = [],
  ) {}

}
