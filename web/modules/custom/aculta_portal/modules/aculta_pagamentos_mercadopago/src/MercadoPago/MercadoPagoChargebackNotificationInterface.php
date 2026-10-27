<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

/**
 * Handles a Mercado Pago chargeback case identified by a signed webhook.
 */
interface MercadoPagoChargebackNotificationInterface {

  /**
   * Fetches and reconciles a provider chargeback case.
   */
  public function processar(string $caseId): void;

}
