<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

/**
 * Falha de uma chamada à API do Mercado Pago.
 *
 * A mensagem é segura para exibir ao usuário. O corpo da resposta do Mercado Pago não entra aqui: ele
 * pode conter dados do pagador, e fica só no log de depuração do canal aculta_portal.
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
