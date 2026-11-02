<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

use Drupal\aculta_lms\Assessment\AttemptRule;

/**
 * Deriva conclusão, sucesso e nota a partir de declarações xAPI já validadas (F2).
 *
 * Função pura. Agrupa as declarações por tentativa (context.registration; sem
 * registration, todas caem na mesma tentativa) e usa AttemptRule para o status
 * e a escolha da nota. Não lê nem grava nada.
 *
 * Regras, de acordo com a especificação e com o uso dos verbos da ADL:
 * - conclusão: result.completion verdadeiro, ou verbo `completed`, `passed` ou `failed`
 *   (o sentido vem do IRI do verbo);
 * - sucesso: o último result.success ou verbo `passed` (verdadeiro) ou `failed`
 *   (falso) decide;
 * - nota: result.score.scaled vira max(0, scaled) × 100; sem scaled, raw com min e max
 *   vira percentual de (raw − min) ÷ (max − min) × 100, limitado a 0–100; sem nenhum
 *   dos dois, não há nota. A última declaração com nota decide.
 *
 * Pontuação final e status vêm de AttemptRule, na escala de 0 a 100. O sucesso
 * informado não substitui a nota: o status segue a regra do LMS.
 */
final class XapiProgressDeriver {

  private const VERB_COMPLETED = 'http://adlnet.gov/expapi/verbs/completed';
  private const VERB_PASSED = 'http://adlnet.gov/expapi/verbs/passed';
  private const VERB_FAILED = 'http://adlnet.gov/expapi/verbs/failed';

  /**
   * Agrupa declarações por tentativa e deriva o estado de cada uma.
   *
   * @param list<array<string, mixed>> $statements
   *   Declarações já validadas pelo XapiStatementValidator, na ordem recebida.
   *
   * @return list<array{registration: string, completed: bool, success: ?bool, score: ?float, finished: int}>
   */
  public function attempts(array $statements): array {
    $groups = [];
    foreach ($statements as $index => $statement) {
      $registration = (string) ($statement['context']['registration'] ?? '');
      $groups[$registration][] = [$index, $statement];
    }

    $attempts = [];
    foreach ($groups as $registration => $items) {
      $attempts[] = $this->attempt((string) $registration, $items);
    }
    return $attempts;
  }

  /**
   * Resultado final da atividade: tentativas, nota mantida e status.
   *
   * @param list<array<string, mixed>> $statements
   * @param string $policy
   *   AttemptRule::KEEP_LATEST ou AttemptRule::KEEP_BEST.
   * @param float $requiredScore
   *   Nota mínima para aprovação, de 0 a 100.
   *
   * @return array{status: string, score: ?float, attempts: list<array<string, mixed>>}
   */
  public function result(array $statements, string $policy, float $requiredScore): array {
    $attempts = $this->attempts($statements);
    $scored = array_values(array_filter(
      $attempts,
      static fn (array $attempt): bool => $attempt['completed'] && $attempt['score'] !== NULL,
    ));
    $shaped = array_map(
      static fn (array $attempt): array => ['score' => $attempt['score'], 'finished' => $attempt['finished']],
      $scored,
    );
    $kept = AttemptRule::keep($shaped, $policy);

    $score = $kept['score'] ?? NULL;
    // Uma atividade sem conclusão, ou sem nota, continua pendente.
    $status = AttemptRule::status($kept === NULL ? 1 : 0, $score, $requiredScore);

    return ['status' => $status, 'score' => $score, 'attempts' => $attempts];
  }

  /**
   * Deriva uma tentativa a partir das declarações do mesmo registration.
   *
   * @param string $registration
   * @param list<array{0: int, 1: array<string, mixed>}> $items
   *
   * @return array{registration: string, completed: bool, success: ?bool, score: ?float, finished: int}
   */
  private function attempt(string $registration, array $items): array {
    $completed = FALSE;
    $success = NULL;
    $score = NULL;
    $finished = 0;

    // Ordena pelo timestamp; sem timestamp, mantém a ordem recebida.
    usort($items, function (array $a, array $b): int {
      $ta = $this->time($a[1]);
      $tb = $this->time($b[1]);
      return $ta !== $tb ? $ta <=> $tb : $a[0] <=> $b[0];
    });

    foreach ($items as [, $statement]) {
      $verb = (string) ($statement['verb']['id'] ?? '');
      $result = $statement['result'] ?? [];
      $time = $this->time($statement);

      if (($result['completion'] ?? FALSE) === TRUE || in_array($verb, [self::VERB_COMPLETED, self::VERB_PASSED, self::VERB_FAILED], TRUE)) {
        $completed = TRUE;
        $finished = max($finished, $time);
      }
      if (isset($result['success']) && is_bool($result['success'])) {
        $success = $result['success'];
      }
      elseif ($verb === self::VERB_PASSED) {
        $success = TRUE;
      }
      elseif ($verb === self::VERB_FAILED) {
        $success = FALSE;
      }
      $value = $this->score($result);
      if ($value !== NULL) {
        $score = $value;
      }
    }

    return [
      'registration' => $registration,
      'completed' => $completed,
      'success' => $success,
      'score' => $score,
      'finished' => $finished,
    ];
  }

  /**
   * Converte a pontuação do result para a escala de 0 a 100, ou NULL.
   *
   * @param array<string, mixed> $result
   */
  private function score(array $result): ?float {
    $score = $result['score'] ?? NULL;
    if (!is_array($score)) {
      return NULL;
    }
    if (isset($score['scaled'])) {
      return max(0.0, (float) $score['scaled']) * 100.0;
    }
    if (isset($score['raw'], $score['min'], $score['max'])) {
      $range = (float) $score['max'] - (float) $score['min'];
      if ($range <= 0) {
        return NULL;
      }
      $percent = ((float) $score['raw'] - (float) $score['min']) / $range * 100.0;
      return max(0.0, min(100.0, $percent));
    }
    return NULL;
  }

  /**
   * Timestamp da declaração como inteiro, ou 0 quando ausente.
   *
   * @param array<string, mixed> $statement
   */
  private function time(array $statement): int {
    $timestamp = $statement['timestamp'] ?? NULL;
    if (!is_string($timestamp)) {
      return 0;
    }
    $time = strtotime($timestamp);
    return $time === FALSE ? 0 : $time;
  }

}
