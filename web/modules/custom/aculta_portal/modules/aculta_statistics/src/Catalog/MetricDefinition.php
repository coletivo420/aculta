<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Catalog;

/**
 * Definição de uma métrica do catálogo central.
 */
final readonly class MetricDefinition {

  /**
   * Sensibilidade pública: agregado sem dado de pessoa.
   */
  public const SENSITIVITY_PUBLIC = 'P';

  /**
   * Sensibilidade interna: visível à equipe.
   */
  public const SENSITIVITY_INTERNAL = 'I';

  /**
   * Sensibilidade restrita: nunca aparece em painel global.
   */
  public const SENSITIVITY_RESTRICTED = 'R';

  public function __construct(
    public string $id,
    public string $label,
    public string $unit,
    public string $sensitivity,
    public ?int $defaultRetentionDays,
  ) {
    if (!in_array($sensitivity, [self::SENSITIVITY_PUBLIC, self::SENSITIVITY_INTERNAL, self::SENSITIVITY_RESTRICTED], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Sensibilidade inválida para %s.', $id));
    }
  }

}
