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
   * @return array{sent: bool, message: string}
   *   Se foi aceito e a mensagem de erro, se não foi.
   */
  public function send(MailTransport $transport, Email $email): array {
    try {
      $this->factory->create($transport)->send($email);
      return ['sent' => TRUE, 'message' => ''];
    }
    catch (TransportExceptionInterface | \RuntimeException $error) {
      return ['sent' => FALSE, 'message' => $error->getMessage()];
    }
  }

}
