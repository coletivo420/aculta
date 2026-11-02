<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

/**
 * Validade do token de tentativa, lida da configuração administrativa (ADR-041).
 *
 * Função pura. A configuração guarda a validade em segundos. Valor ausente,
 * não inteiro ou fora de [MIN_TTL, MAX_TTL] cai no padrão, para o endpoint
 * nunca emitir token com validade maior que o teto nem zero.
 */
final class XapiTokenSettings {

  /**
   * Nome da configuração no Drupal.
   */
  public const CONFIG_NAME = 'aculta_lms.xapi';

  /**
   * Chave da validade, em segundos.
   */
  public const KEY_TTL = 'attempt_token_ttl';

  /**
   * Validade padrão: 30 minutos.
   */
  public const DEFAULT_TTL = 1800;

  /**
   * Menor validade aceita: 1 minuto.
   */
  public const MIN_TTL = 60;

  /**
   * Maior validade aceita: o teto do token, 2 horas.
   */
  public const MAX_TTL = XapiAttemptToken::MAX_TTL;

  /**
   * Validade efetiva a partir do valor guardado.
   */
  public static function ttlFrom(mixed $value): int {
    if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
      return self::DEFAULT_TTL;
    }
    $ttl = (int) $value;
    return $ttl < self::MIN_TTL || $ttl > self::MAX_TTL ? self::DEFAULT_TTL : $ttl;
  }

}
