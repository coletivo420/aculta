<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Política de retry: só para erro temporário, com atraso crescente.
 *
 * Tentativa 1 é imediata. Depois: +1 min, +5 min, +30 min, +2 h. Esgotadas as tentativas, a mensagem falha.
 * Erro permanente (destinatário inválido, hard bounce, supressão, bloqueio) nunca é reenviado automaticamente.
 */
final class RetrySchedule {

  public const MAX_ATTEMPTS = 5;

  /**
   * Atraso em segundos antes de cada tentativa, indexado pelo número da tentativa (2 a 5).
   */
  private const DELAYS = [
    2 => 60,
    3 => 300,
    4 => 1800,
    5 => 7200,
  ];

  /**
   * Atraso em segundos antes da tentativa informada, ou NULL quando não há nova tentativa.
   *
   * @param int $nextAttempt
   *   Número da próxima tentativa (2 a 5).
   * @param bool $permanent
   *   TRUE para erro permanente conhecido.
   */
  public function delayBeforeAttempt(int $nextAttempt, bool $permanent): ?int {
    if ($permanent || $nextAttempt < 2 || $nextAttempt > self::MAX_ATTEMPTS) {
      return NULL;
    }
    return self::DELAYS[$nextAttempt];
  }

}
