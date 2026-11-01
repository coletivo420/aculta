<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Message;

/**
 * Estados de uma mensagem. `sent` (o transporte aceitou) não equivale a `delivered` (o destinatário recebeu).
 */
enum MailStatus: string {

  case Queued = 'queued';
  case Processing = 'processing';
  case Sent = 'sent';
  case Delivered = 'delivered';
  case Deferred = 'deferred';
  case Bounced = 'bounced';
  case Complained = 'complained';
  case Failed = 'failed';
  case Cancelled = 'cancelled';

  /**
   * Estados de destino permitidos a partir deste estado.
   *
   * @return list<self>
   *   Estados alcançáveis a partir deste.
   */
  public function allowedTransitions(): array {
    return match ($this) {
      self::Queued => [self::Processing, self::Cancelled],
      self::Processing => [self::Sent, self::Deferred, self::Failed],
      self::Deferred => [self::Queued, self::Failed, self::Cancelled],
      self::Sent => [self::Delivered, self::Bounced, self::Complained],
      self::Delivered => [self::Complained],
      self::Bounced, self::Complained, self::Failed, self::Cancelled => [],
    };
  }

  /**
   * Indica se a transição para o próximo estado é permitida.
   */
  public function canTransitionTo(self $next): bool {
    return in_array($next, $this->allowedTransitions(), TRUE);
  }

  /**
   * Indica se o estado não tem transições de saída.
   */
  public function isTerminal(): bool {
    return $this->allowedTransitions() === [];
  }

}
