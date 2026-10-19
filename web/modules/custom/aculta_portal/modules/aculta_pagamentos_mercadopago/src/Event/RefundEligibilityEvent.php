<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\Event;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Pergunta se o estorno de um pagamento do Checkout Pro é permitido.
 *
 * O gateway é neutro: não conhece doações, cursos nem outras regras do Portal. Um assinante marca a
 * decisão com deny(). Sem assinante, o estorno segue a regra padrão do Commerce.
 */
final class RefundEligibilityEvent extends Event {

  /**
   * Nome do evento disparado antes de oferecer ou executar o estorno.
   */
  public const NAME = 'aculta_pagamentos_mercadopago.refund_eligibility';

  /**
   * Indica se o estorno foi negado por algum assinante.
   */
  private bool $denied = FALSE;

  /**
   * Motivo informado pelo assinante que negou o estorno.
   */
  private string $reason = '';

  /**
   * Constructs the event.
   */
  public function __construct(private readonly PaymentInterface $payment) {}

  /**
   * Pagamento cujo estorno está sendo avaliado.
   */
  public function getPayment(): PaymentInterface {
    return $this->payment;
  }

  /**
   * Nega o estorno, com motivo para a mensagem ao operador.
   */
  public function deny(string $reason): void {
    $this->denied = TRUE;
    $this->reason = $reason;
  }

  /**
   * Indica se o estorno é permitido.
   */
  public function isAllowed(): bool {
    return !$this->denied;
  }

  /**
   * Motivo da negativa, ou string vazia.
   */
  public function getReason(): string {
    return $this->reason;
  }

}
