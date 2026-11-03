<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Assessment;

/**
 * Pontuação dos tipos objetivos de atividade, sem dependência do LMS contrib (ADR-042, P3).
 *
 * Função pura. Devolve a nota de 0 a 100, na mesma escala da AttemptRule:
 * - verdadeiro/falso: 100 se acertou, 0 se errou;
 * - seleção: 100 só com o conjunto exato de opções corretas (sem crédito parcial);
 * - lacunas: percentual de lacunas corretas. Texto é comparado sem diferença de caixa,
 *   sem espaços nas pontas e com espaços internos colapsados. Cada lacuna aceita uma lista
 *   de respostas. Lacuna ausente conta como errada.
 */
final class ObjectiveAnswerScorer {

  /**
   * Nota máxima da escala.
   */
  public const MAX = 100.0;

  /**
   * Verdadeiro/falso.
   */
  public function trueFalse(bool $answer, bool $correct): float {
    return $answer === $correct ? self::MAX : 0.0;
  }

  /**
   * Seleção (simples ou múltipla): conjunto exato de opções.
   *
   * @param string[] $selected
   *   Opções marcadas pelo aluno.
   * @param string[] $correct
   *   Opções corretas da atividade.
   */
  public function select(array $selected, array $correct): float {
    $selected = array_values(array_unique($selected));
    $correct = array_values(array_unique($correct));
    if ($correct === []) {
      return 0.0;
    }
    sort($selected);
    sort($correct);
    return $selected === $correct ? self::MAX : 0.0;
  }

  /**
   * Lacunas: percentual de lacunas com resposta aceita.
   *
   * @param array<int|string, string> $answers
   *   Texto respondido, por índice da lacuna.
   * @param array<int|string, string[]> $accepted
   *   Respostas aceitas por índice da lacuna.
   */
  public function fillInBlanks(array $answers, array $accepted): float {
    if ($accepted === []) {
      return 0.0;
    }
    $hits = 0;
    foreach ($accepted as $index => $options) {
      $given = $this->normalise((string) ($answers[$index] ?? ''));
      if ($given === '') {
        continue;
      }
      foreach ($options as $option) {
        if ($this->normalise($option) === $given) {
          $hits++;
          break;
        }
      }
    }
    return self::MAX * $hits / count($accepted);
  }

  /**
   * Texto em forma comparável.
   */
  private function normalise(string $text): string {
    return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
  }

}
