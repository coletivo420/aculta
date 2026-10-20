<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

use Drupal\aculta_mailer\Message\MailStatus;

/**
 * Resultado de uma falha de entrega: reagendar (com atraso e transporte) ou encerrar em `failed`.
 */
final readonly class DeliveryDecision {

  public function __construct(
    public MailStatus $status,
    public ?string $transport,
    public ?int $delaySeconds,
    public int $nextAttempt,
  ) {}

  /**
   * Indica se a mensagem deve ser reagendada.
   */
  public function shouldRetry(): bool {
    return $this->status === MailStatus::Deferred;
  }

}
