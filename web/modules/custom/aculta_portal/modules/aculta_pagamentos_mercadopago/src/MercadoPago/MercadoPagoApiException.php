<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

/**
 * Falha de uma chamada à API do Mercado Pago.
 *
 * A mensagem é segura para exibir ao usuário. O corpo e a mensagem da resposta
 * do Mercado Pago podem conter dados do pagador e não são incluídos no log.
 */
final class MercadoPagoApiException extends \RuntimeException {

  public function __construct(string $mensagemSegura, private readonly int $codigoHttp = 0, ?\Throwable $anterior = NULL) {
    parent::__construct($mensagemSegura, 0, $anterior);
  }

  /**
   * Código HTTP devolvido pelo Mercado Pago (0 quando não houve resposta).
   */
  public function codigoHttp(): int {
    return $this->codigoHttp;
  }

}
