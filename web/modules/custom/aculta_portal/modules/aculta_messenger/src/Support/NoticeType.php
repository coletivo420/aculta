<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Support;

/**
 * The types of notice and the colour each one has in the drawer. A persistent notice chooses its type in the admin form
 * (field_aculta_notice_type); a site notice takes it from its severity (low, medium, high).
 */
final class NoticeType {

  public const INFO = 'info';
  public const WARNING = 'warning';
  public const DANGER = 'danger';

  /** The type of a notice that has none: the warning colour, as the persistent notices always had. */
  public const DEFAULT = self::WARNING;

  /** @return array<string, string> */
  public static function options(): array {
    return [
      self::INFO => 'Informação',
      self::WARNING => 'Atenção',
      self::DANGER => 'Urgente',
    ];
  }

  /** The type of a site notice, from its severity. */
  public static function fromSeverity(?string $severity): string {
    return match ($severity) {
      'low' => self::INFO,
      'high' => self::DANGER,
      default => self::WARNING,
    };
  }

  /** The type of a notice from its stored value, falling back to the default. */
  public static function normalize(?string $type): string {
    return array_key_exists((string) $type, self::options()) ? (string) $type : self::DEFAULT;
  }

}
