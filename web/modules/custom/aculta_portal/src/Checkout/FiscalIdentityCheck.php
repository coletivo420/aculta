<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Checkout;

/**
 * Resultado da consulta de identidade fiscal. Nunca carrega o CPF/CNPJ completo: só a representação mascarada.
 */
final readonly class FiscalIdentityCheck {

  public function __construct(
    public FiscalIdentityStatus $status,
    public ?string $masked = NULL,
  ) {}

  /**
   *
   */
  public function isSatisfied(): bool {
    return $this->status === FiscalIdentityStatus::Valid;
  }

}
