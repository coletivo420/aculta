<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

use Drupal\aculta_mailer\Message\MailMessage;

/**
 * Ponto público de envio de e-mail transacional do ACULTA.
 *
 * Consumidores dependem somente deste contrato. A implementação usa o Core (plugin.manager.mail) e não é exposta
 * a quem chama. Ver docs/ARCHITECTURE.md.
 */
interface AcultaMailerInterface {

  /**
   * Envia a mensagem pelo transporte da política do tipo.
   *
   * O correlation ID da mensagem é o mesmo informado em MailMessage, para rastreio em logs e cabeçalhos.
   *
   * @return bool
   *   TRUE quando o sistema de e-mail aceitou a mensagem (status `sent`).
   */
  public function send(MailMessage $message): bool;

}
