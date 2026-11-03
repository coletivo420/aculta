<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

/**
 * Retenção das declarações xAPI: prazo configurável, padrão de 6 meses (D-68, ADR-041).
 *
 * Função pura. Dá o prazo efetivo a partir do valor guardado e o instante de
 * corte, em UTC. Declarações recebidas antes do corte vencem e entram na
 * remoção mensal. Valor ausente, não inteiro ou fora de 1–24 meses cai no padrão.
 */
final class XapiRetention {

  /**
   * Chave da retenção, em meses, na configuração aculta_lms.xapi.
   */
  public const KEY_MONTHS = 'retention_months';

  /**
   * Padrão: 6 meses.
   */
  public const DEFAULT_MONTHS = 6;

  /**
   * Menor prazo aceito: 1 mês.
   */
  public const MIN_MONTHS = 1;

  /**
   * Maior prazo aceito: 24 meses.
   */
  public const MAX_MONTHS = 24;

  /**
   * Prazo efetivo, em meses, a partir do valor guardado.
   */
  public static function monthsFrom(mixed $value): int {
    if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
      return self::DEFAULT_MONTHS;
    }
    $months = (int) $value;
    return $months < self::MIN_MONTHS || $months > self::MAX_MONTHS ? self::DEFAULT_MONTHS : $months;
  }

  /**
   * Instante, em Unix time, antes do qual as declarações estão vencidas.
   */
  public static function cutoff(int $now, int $months): int {
    $months = self::monthsFrom($months);
    $date = (new \DateTimeImmutable('@' . $now))->modify('-' . $months . ' months');
    return $date->getTimestamp();
  }

}
