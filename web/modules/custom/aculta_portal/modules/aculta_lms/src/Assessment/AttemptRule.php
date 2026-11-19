<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Assessment;

/**
 * Regras puras de tentativa de avaliação: status e escolha do resultado mantido.
 *
 * Sem dependência do Drupal, para testar sem bootstrap. Ainda não está ligada à
 * avaliação do LMS: a aplicação depende de decisão de produto (fase posterior).
 * Notas estão na escala de 0 a 100.
 */
final class AttemptRule {

  /**
   * Mantém a tentativa finalizada mais recentemente.
   */
  public const KEEP_LATEST = 'latest';

  /**
   * Mantém a tentativa de maior nota (empate: a mais recente).
   */
  public const KEEP_BEST = 'best';

  /**
   * Status: ainda há atividade sem avaliação, ou nota ausente.
   */
  public const PENDING = 'pending';

  /**
   * Status: todas as atividades avaliadas e nota igual ou acima do mínimo.
   */
  public const PASSED = 'passed';

  /**
   * Status: todas as atividades avaliadas e nota abaixo do mínimo.
   */
  public const FAILED = 'failed';

  /**
   * Status da tentativa.
   *
   * Aprovação exige todas as atividades avaliadas e nota igual ou acima do mínimo.
   *
   * @throws \InvalidArgumentException
   *   Quando a contagem pendente é negativa ou a nota mínima está fora de 0 a 100.
   */
  public static function status(int $pendingActivities, ?float $score, float $requiredScore): string {
    if ($pendingActivities < 0) {
      throw new \InvalidArgumentException('A contagem de atividades pendentes não pode ser negativa.');
    }
    if ($requiredScore < 0 || $requiredScore > 100) {
      throw new \InvalidArgumentException('A nota mínima deve estar entre 0 e 100.');
    }
    if ($pendingActivities > 0 || $score === NULL) {
      return self::PENDING;
    }
    return $score >= $requiredScore ? self::PASSED : self::FAILED;
  }

  /**
   * Resultado mantido entre as tentativas, ou NULL quando não há tentativa.
   *
   * @param array<int, array{score: float, finished: int}> $attempts
   *   Tentativas na ordem em que ocorreram. `finished` é o timestamp de término.
   * @param string $policy
   *   Política de escolha da tentativa mantida.
   *
   * @return array{score: float, finished: int}|null
   *   A tentativa escolhida.
   *
   * @throws \InvalidArgumentException
   *   Quando a política é desconhecida.
   */
  public static function keep(array $attempts, string $policy): ?array {
    if ($policy !== self::KEEP_LATEST && $policy !== self::KEEP_BEST) {
      throw new \InvalidArgumentException('Política de resultado desconhecida.');
    }
    $kept = NULL;
    foreach ($attempts as $attempt) {
      if ($kept === NULL) {
        $kept = $attempt;
        continue;
      }
      // Em empate de nota, a política "melhor" fica com a tentativa terminada mais recentemente.
      $better = $policy === self::KEEP_LATEST
        ? $attempt['finished'] >= $kept['finished']
        : ($attempt['score'] > $kept['score']
          || ($attempt['score'] === $kept['score'] && $attempt['finished'] >= $kept['finished']));
      if ($better) {
        $kept = $attempt;
      }
    }
    return $kept;
  }

}
