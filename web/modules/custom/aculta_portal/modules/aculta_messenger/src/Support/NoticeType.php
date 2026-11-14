<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Support;

/**
 * Tipos de aviso e a cor de cada um na gaveta.
 *
 * Um aviso persistente escolhe o tipo no formulário administrativo
 * (field_aculta_notice_type); um aviso do site o obtém da severidade (low,
 * medium, high).
 */
final class NoticeType {

  public const INFO = 'info';
  public const WARNING = 'warning';
  public const DANGER = 'danger';

  /**
   * The type of a notice that has none: the warning colour, as the persistent notices always had. */
  public const DEFAULT = self::WARNING;

  /**
   * Opções de tipo de aviso, com o rótulo de cada um.
   *
   * @return array<string, string>
   *   Tipos indexados pelo identificador e seus rótulos.
   */
  public static function options(): array {
    return [
      self::INFO => 'Informação',
      self::WARNING => 'Atenção',
      self::DANGER => 'Urgente',
    ];
  }

  /**
   * The type of a site notice, from its severity. */
  public static function fromSeverity(?string $severity): string {
    return match ($severity) {
      'low' => self::INFO,
      'high' => self::DANGER,
      default => self::WARNING,
    };
  }

  /**
   * The type of a notice from its stored value, falling back to the default. */
  public static function normalize(?string $type): string {
    return array_key_exists((string) $type, self::options()) ? (string) $type : self::DEFAULT;
  }

}
