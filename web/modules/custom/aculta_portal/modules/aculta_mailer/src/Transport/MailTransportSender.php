<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Transport;

use Drupal\aculta_mailer\Entity\MailTransport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

/**
 * Envia um e-mail pelo transporte da entidade e devolve o resultado.
 *
 * Na F14 não é o caminho de produção: o envio do site segue pelo módulo de envio atual até a F15.
 */
final class MailTransportSender {

  public function __construct(
    private readonly MailTransportFactory $factory,
  ) {}

  /**
   * Resultado do envio. A mensagem vai para o administrador; não contém credenciais.
   *
   * @return array{sent: bool, message: string, code: int|null}
   *   Se foi aceito, a mensagem de erro e o código SMTP da resposta, quando houver.
   */
  public function send(MailTransport $transport, Email $email): array {
    try {
      $this->factory->create($transport)->send($email);
      return ['sent' => TRUE, 'message' => '', 'code' => NULL];
    }
    catch (TransportExceptionInterface | \RuntimeException $error) {
      $message = $error->getMessage();
      // Respostas do servidor citam o código: "...got code \"552\"..." ou "552 texto".
      $code = preg_match('/got code "?(\d{3})"?|^(\d{3})\b/', $message, $match) === 1 ? (int) ($match[1] ?: $match[2]) : NULL;
      return ['sent' => FALSE, 'message' => $message, 'code' => $code];
    }
  }

}
