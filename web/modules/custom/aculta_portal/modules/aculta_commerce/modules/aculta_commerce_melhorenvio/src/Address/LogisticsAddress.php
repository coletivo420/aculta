<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Address;

/**
 * Endereço logístico validado, pronto para a API do Melhor Envio. Imutável.
 *
 * Quando $errors não está vazio, os demais campos não devem ser usados para operação.
 */
final class LogisticsAddress {

  /**
   * @param list<string> $errors
   */
  public function __construct(
    public readonly string $postalCode,
    public readonly string $street,
    public readonly string $number,
    public readonly bool $sn,
    public readonly string $complement,
    public readonly string $district,
    public readonly string $city,
    public readonly string $stateAbbr,
    public readonly string $countryId,
    public readonly array $errors = [],
  ) {}

  public function isValid(): bool {
    return $this->errors === [];
  }

  /** Payload mínimo da cotação: só CEP. Número e demais campos não são exigidos pelo endpoint. */
  public function toQuoteArray(): array {
    return ['postal_code' => $this->postalCode];
  }

  /**
   * Payload do endereço para o carrinho (contratação) e a etiqueta. Chaves como na referência oficial.
   *
   * @return array<string, string>
   */
  public function toShipmentArray(): array {
    return [
      'postal_code' => $this->postalCode,
      'address' => $this->street,
      'number' => $this->number,
      'complement' => $this->complement,
      'district' => $this->district,
      'city' => $this->city,
      'state_abbr' => $this->stateAbbr,
      'country_id' => $this->countryId,
    ];
  }

}
