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

  /**
   * Soma de transições qualificadas por dia; pode ser somada no período.
   */
  public const TYPE_COUNTER = 'counter';

  /**
   * Fotografia do dia; nunca somar entre dias.
   */
  public const TYPE_SNAPSHOT = 'snapshot';

  /**
   * Identidades únicas numa janela fixa; nunca somar contagens diárias.
   */
  public const TYPE_DISTINCT_WINDOW = 'distinct-window';

  /**
   * Numerador e denominador do mesmo recorte; recalcular ao filtrar.
   */
  public const TYPE_RATIO = 'ratio';

  /**
   * Mediana e percentis; não substituir por média.
   */
  public const TYPE_DISTRIBUTION = 'distribution';

  /**
   * Estado corrente de fila ou subsistema operacional.
   */
  public const TYPE_OPERATIONAL = 'operational';

  /**
   * Tipos aceitos pelo catálogo.
   */
  public const TYPES = [
    self::TYPE_COUNTER,
    self::TYPE_SNAPSHOT,
    self::TYPE_DISTINCT_WINDOW,
    self::TYPE_RATIO,
    self::TYPE_DISTRIBUTION,
    self::TYPE_OPERATIONAL,
  ];

  public function __construct(
    public string $id,
    public string $label,
    public string $unit,
    public string $sensitivity,
    public ?int $defaultRetentionDays,
    public string $type = self::TYPE_COUNTER,
  ) {
    if (!in_array($sensitivity, [self::SENSITIVITY_PUBLIC, self::SENSITIVITY_INTERNAL, self::SENSITIVITY_RESTRICTED], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Sensibilidade inválida para %s.', $id));
    }
    if (!in_array($type, self::TYPES, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Tipo de métrica inválido para %s.', $id));
    }
  }

  /**
   * Indica métrica somável no período.
   */
  public function isAdditive(): bool {
    return $this->type === self::TYPE_COUNTER;
  }

  /**
   * Indica métrica calculada a partir da fonte, não por soma de dias.
   */
  public function isDerivedFromSource(): bool {
    return in_array($this->type, [
      self::TYPE_SNAPSHOT,
      self::TYPE_DISTINCT_WINDOW,
      self::TYPE_RATIO,
      self::TYPE_DISTRIBUTION,
      self::TYPE_OPERATIONAL,
    ], TRUE);
  }

}
