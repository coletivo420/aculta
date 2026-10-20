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
   * @return array{sent: bool, message: string, code: int|null, provider_id: string|null}
   *   Se foi aceito, a mensagem de erro, o código SMTP da resposta (quando houver) e o ID que o provedor deu à mensagem.
   */
  public function send(MailTransport $transport, Email $email): array {
    try {
      $sent = $this->factory->create($transport)->send($email);
      // O Brevo devolve o messageId na resposta da API; o Symfony o guarda no SentMessage. Sem ID, NULL.
      $providerId = $sent?->getMessageId();
      return [
        'sent' => TRUE,
        'message' => '',
        'code' => NULL,
        'provider_id' => $providerId !== NULL && $providerId !== '' ? trim($providerId, '<> ') : NULL,
      ];
    }
    catch (TransportExceptionInterface | \RuntimeException $error) {
      $message = $error->getMessage();
      // Respostas do servidor citam o código: "...got code \"552\"..." ou "552 texto".
      $code = preg_match('/got code "?(\d{3})"?|^(\d{3})\b/', $message, $match) === 1 ? (int) ($match[1] ?: $match[2]) : NULL;
      return ['sent' => FALSE, 'message' => $message, 'code' => $code, 'provider_id' => NULL];
    }
  }

}
