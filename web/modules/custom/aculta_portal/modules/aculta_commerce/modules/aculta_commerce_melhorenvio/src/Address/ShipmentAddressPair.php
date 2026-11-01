<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Address;

/**
 * Origem e destino de uma operação logística, já mapeados. Imutável.
 */
final class ShipmentAddressPair {

  public function __construct(
    public readonly LogisticsAddress $from,
    public readonly LogisticsAddress $to,
  ) {}

  public function isValid(): bool {
    return $this->from->isValid() && $this->to->isValid();
  }

  /**
   * Erros por lado, para mensagem administrativa: ['origin' => [...], 'destination' => [...]].
   *
   * @return array{origin: list<string>, destination: list<string>}
   */
  public function errors(): array {
    return ['origin' => $this->from->errors, 'destination' => $this->to->errors];
  }

}
