<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Failover entre o transporte padrão e o de contingência.
 *
 * Decisão do responsável (2026-10-20): padrão Hostinger, com fallback SMTP2GO. Só erro temporário troca de transporte;
 * erro permanente nunca é reenviado por outro provedor, para não multiplicar um destinatário inválido.
 */
final class TransportFailover {

  public function __construct(
    private readonly string $primary,
    private readonly ?string $fallback,
  ) {
    if ($primary === '') {
      throw new \InvalidArgumentException('O transporte padrão é obrigatório.');
    }
    if ($fallback === $primary) {
      throw new \InvalidArgumentException('O transporte de contingência deve ser diferente do padrão.');
    }
  }

  /**
   * Transporte para a próxima tentativa, ou NULL quando não há troca.
   *
   * @param string $failedTransport
   *   Transporte que acabou de falhar.
   * @param \Drupal\aculta_mailer\Delivery\DeliveryError $error
   *   Classe do erro.
   */
  public function nextTransport(string $failedTransport, DeliveryError $error): ?string {
    if ($error === DeliveryError::Permanent || $this->fallback === NULL) {
      return NULL;
    }
    return $failedTransport === $this->primary ? $this->fallback : NULL;
  }

}
