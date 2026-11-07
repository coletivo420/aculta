<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

/**
 * Outcome of attempting to request a registration confirmation email. */
enum EmailConfirmationRequestResult {

  // O MailManager aceitou o pedido; a entrega final na caixa é desconhecida.
  case ACCEPTED;

  // A conta já usou a cota de pedidos de confirmação.
  case RATE_LIMITED;

  // Outro pedido desta conta está segurando o bloqueio de serialização.
  case BUSY;

  // A conta informada não pode ter um pedido de confirmação.
  case INVALID_ACCOUNT;

  // A persistência ou o backend de e-mail não aceitou o pedido.
  case FAILED;

}
