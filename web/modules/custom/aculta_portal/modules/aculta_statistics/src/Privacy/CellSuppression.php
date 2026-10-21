<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Privacy;

/**
 * Supressão de células pequenas em painéis públicos.
 *
 * Agregado de turma com poucos alunos pode identificar uma pessoa. Decisão do responsável:
 * 5 alunos. Aplica-se a painéis de leitura pública; relatórios internos seguem sem limite.
 */
final class CellSuppression {

  /**
   * Número mínimo de alunos para exibir um agregado de turma publicamente.
   */
  public const MINIMUM_PUBLIC_COUNT = 5;

  /**
   * Valor a exibir: o próprio número, ou NULL quando a célula é pequena demais.
   */
  public static function publicCount(int $count): ?int {
    return $count >= self::MINIMUM_PUBLIC_COUNT ? $count : NULL;
  }

}
