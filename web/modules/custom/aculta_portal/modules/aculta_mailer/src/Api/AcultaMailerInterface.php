<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

use Drupal\aculta_mailer\Message\MailMessage;

/**
 * Ponto público de envio de e-mail transacional do ACULTA.
 *
 * Consumidores dependem somente deste contrato. A implementação (F2 em diante) usa o Symfony Mailer por meio do Core
 * e não é exposta a quem chama. Ver docs/ARCHITECTURE.md.
 */
interface AcultaMailerInterface {

  /**
   * Enfileira ou envia a mensagem conforme a política do tipo.
   *
   * @return string
   *   Correlation ID da mensagem, para rastreio em logs e suporte.
   */
  public function send(MailMessage $message): string;

}
