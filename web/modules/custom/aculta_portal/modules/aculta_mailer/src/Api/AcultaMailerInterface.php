<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

use Drupal\aculta_mailer\Delivery\SendResult;
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

  /**
   * Tenta o envio pelo transporte escolhido e devolve a classe da falha, quando houver.
   *
   * @param \Drupal\aculta_mailer\Message\MailMessage $message
   *   Mensagem a enviar.
   * @param string|null $transport
   *   ID do transporte (`smtp`, `smtp2go`); NULL usa o padrão configurado.
   *
   * @return \Drupal\aculta_mailer\Delivery\SendResult
   *   Resultado com a classe da falha e o código SMTP, quando conhecidos.
   */
  public function deliver(MailMessage $message, ?string $transport = NULL): SendResult;

}
