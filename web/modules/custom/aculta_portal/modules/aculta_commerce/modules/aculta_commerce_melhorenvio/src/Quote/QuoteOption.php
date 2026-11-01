<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

/**
 * Um serviço de entrega cotado pelo Melhor Envio (por exemplo, PAC dos Correios).
 */
final class QuoteOption {

  /**
   * Cria a opção.
   *
   * @param string $serviceId
   *   ID do serviço no Melhor Envio, como texto (por exemplo, "1" para PAC).
   * @param string $name
   *   Nome do serviço.
   * @param string $company
   *   Nome da transportadora.
   * @param string $amount
   *   Preço em reais, decimal com ponto (por exemplo, "28.33").
   * @param int|null $deliveryDays
   *   Prazo em dias úteis, quando informado.
   */
  public function __construct(
    public readonly string $serviceId,
    public readonly string $name,
    public readonly string $company,
    public readonly string $amount,
    public readonly ?int $deliveryDays,
  ) {}

  /**
   * Rótulo para o cliente: serviço e transportadora.
   */
  public function label(): string {
    return $this->company !== '' && $this->company !== $this->name
      ? $this->name . ' (' . $this->company . ')'
      : $this->name;
  }

}
