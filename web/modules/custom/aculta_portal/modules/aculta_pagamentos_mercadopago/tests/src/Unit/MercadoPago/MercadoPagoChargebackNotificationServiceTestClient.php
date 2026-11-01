<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use MercadoPago\Client\Common\RequestOptions;

/**
 * Test double for authenticated seller and chargeback SDK clients.
 */
final class MercadoPagoChargebackNotificationServiceTestClient {

  /**
   * Creates a client with a canned response.
   */
  public function __construct(private readonly object $response) {}

  /**
   * Returns the seller identity or checks chargeback request options.
   */
  public function get(?string $id = NULL, ?RequestOptions $options = NULL): object {
    if ($id !== NULL && ($id !== '987654' || $options?->getCustomHeaders() !== ['X-Caller-Id' => '12345'])) {
      throw new \RuntimeException('Chargeback lookup omitted its signed ID or seller header.');
    }
    if ($this->response instanceof \Throwable) {
      throw $this->response;
    }
    return $this->response;
  }

}
