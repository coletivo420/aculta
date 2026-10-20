<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Classe de erro de entrega: decide se há retry ou failover.
 */
enum DeliveryError: string {

  // Falha transitória (4xx SMTP, timeout, conexão recusada). Pode repetir e trocar de transporte.
  case Temporary = 'temporary';

  // Falha definitiva (5xx SMTP, destinatário inválido, suprimido). Não repete nem troca de transporte.
  case Permanent = 'permanent';

}
