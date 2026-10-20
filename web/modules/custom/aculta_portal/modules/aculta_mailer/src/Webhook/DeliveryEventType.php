<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Webhook;

use Drupal\aculta_mailer\Message\MailStatus;

/**
 * Evento de entrega informado por um provedor. Cada um corresponde a um estado do registro.
 */
enum DeliveryEventType: string {

  case Delivered = 'delivered';
  case Deferred = 'deferred';
  case Bounced = 'bounced';
  case Complained = 'complained';

  /**
   * Estado do registro que este evento produz.
   */
  public function status(): MailStatus {
    return match ($this) {
      self::Delivered => MailStatus::Delivered,
      self::Deferred => MailStatus::Deferred,
      self::Bounced => MailStatus::Bounced,
      self::Complained => MailStatus::Complained,
    };
  }

}
