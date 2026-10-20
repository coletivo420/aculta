<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Transport;

use Drupal\aculta_mailer\Entity\MailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * Monta o transporte do Symfony a partir de uma entidade aculta_mailer_transport (F14).
 *
 * A senha sai do arquivo de segredos pelo nome guardado na entidade. Sem a credencial, falha fechado: nenhum envio
 * sem autenticação quando o transporte pede autenticação.
 */
final class MailTransportFactory {

  public function __construct(
    private readonly ?SecretProviderInterface $secrets,
    private readonly BrevoSecretTransportFactory $brevo,
  ) {}

  /**
   * Transporte do Symfony para a entidade.
   *
   * @throws \RuntimeException
   *   Credencial ausente ou tipo desconhecido.
   */
  public function create(MailTransport $transport): TransportInterface {
    if ($transport->plugin() === 'smtp') {
      return $this->smtp($transport);
    }
    if ($transport->plugin() === 'brevo') {
      return $this->brevo->create(Dsn::fromString('aculta-brevo://default'));
    }
    throw new \RuntimeException('Tipo de transporte desconhecido: ' . $transport->plugin());
  }

  /**
   * Transporte SMTP. Porta 465 usa TLS implícito; as demais, STARTTLS quando o servidor oferecer.
   */
  private function smtp(MailTransport $transport): EsmtpTransport {
    $smtp = new EsmtpTransport($transport->host(), $transport->port(), $transport->port() === 465);
    if ($transport->user() !== '') {
      $secret = $transport->passwordSecret();
      $password = $secret !== '' ? $this->secrets?->value($secret) : NULL;
      if ($password === NULL) {
        throw new \RuntimeException('Credencial da senha SMTP ausente no arquivo de segredos.');
      }
      $smtp->setUsername($transport->user());
      $smtp->setPassword($password);
    }
    $stream = $smtp->getStream();
    if ($stream instanceof SocketStream) {
      $stream->setStreamOptions([
        'ssl' => [
          'verify_peer' => $transport->verifyPeer(),
          'verify_peer_name' => $transport->verifyPeer(),
        ],
      ]);
    }
    return $smtp;
  }

}
