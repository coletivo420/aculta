<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Resultado da consulta de identidade fiscal.
 *
 * Nunca carrega o CPF/CNPJ completo: só a representação mascarada.
 */
final readonly class FiscalIdentityCheck {

  /**
   * Cria o resultado.
   *
   * @param \Drupal\aculta_portal\Checkout\FiscalIdentityStatus $status
   *   Situação do titular.
   * @param string|null $masked
   *   Representação mascarada, quando há documento válido.
   */
  public function __construct(
    public FiscalIdentityStatus $status,
    public ?string $masked = NULL,
  ) {}

  /**
   * Indica se a exigência de identidade fiscal está atendida.
   */
  public function isSatisfied(): bool {
    return $this->status === FiscalIdentityStatus::Valid;
  }

}
