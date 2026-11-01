<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

use Drupal\aculta_mailer\Message\MailStatus;

/**
 * Decide o destino de uma falha de entrega combinando retry e failover.
 *
 * Erro permanente encerra em `failed`. Erro temporário reagenda a próxima tentativa com o atraso de RetrySchedule e,
 * quando houver, o transporte de contingência de TransportFailover. Esgotadas as tentativas, encerra em `failed`.
 */
final class DeliveryPlanner {

  public function __construct(
    private readonly RetrySchedule $retry,
    private readonly TransportFailover $failover,
  ) {}

  /**
   * Decisão após uma falha na tentativa informada.
   *
   * @param int $attempt
   *   Número da tentativa que falhou (1 a MAX_ATTEMPTS).
   * @param string $transport
   *   Transporte usado na tentativa que falhou.
   * @param \Drupal\aculta_mailer\Delivery\DeliveryError $error
   *   Classe do erro.
   */
  public function afterFailure(int $attempt, string $transport, DeliveryError $error): DeliveryDecision {
    $nextAttempt = $attempt + 1;
    $delay = $this->retry->delayBeforeAttempt($nextAttempt, $error === DeliveryError::Permanent);

    if ($delay === NULL) {
      return new DeliveryDecision(MailStatus::Failed, NULL, NULL, $nextAttempt);
    }

    $next = $this->failover->nextTransport($transport, $error) ?? $transport;
    return new DeliveryDecision(MailStatus::Deferred, $next, $delay, $nextAttempt);
  }

}
