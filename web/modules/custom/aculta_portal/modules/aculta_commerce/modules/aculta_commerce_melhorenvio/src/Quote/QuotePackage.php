<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

/**
 * Pacote de uma remessa para cotação: dimensões em centímetros, peso em quilos e valor segurado em reais.
 */
final class QuotePackage {

  /**
   * Cria o pacote. Os valores são normalizados pelo construtor do payload.
   */
  public function __construct(
    public readonly float $widthCm,
    public readonly float $heightCm,
    public readonly float $lengthCm,
    public readonly float $weightKg,
    public readonly float $insuranceValue,
  ) {}

}
