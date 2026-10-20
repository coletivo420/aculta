<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Mail;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Converte a mensagem do Core (array de hook_mail) em e-mail do Symfony, para a camada de envio E-Mails ACULTA (F15).
 *
 * Cabeçalhos de controle do módulo não são repassados ao servidor: X-Transport escolhe o transporte e X-Priority vai
 * como cabeçalho próprio. Os de conteúdo ficam por conta do Symfony.
 */
final class LegacyMessageConverter {

  /**
   * Transporte padrão quando a mensagem não escolhe.
   */
  public const DEFAULT_TRANSPORT = 'smtp_hostinger';

  /**
   * Nomes de transporte usados pelas políticas, e a entidade aculta_mailer_transport correspondente.
   */
  public const TRANSPORT_IDS = [
    'smtp' => 'smtp_hostinger',
    'brevo' => 'brevo',
    'smtp2go' => 'smtp2go',
  ];

  /**
   * Cabeçalhos que não são repassados: o Symfony monta ou o módulo consome.
   */
  private const SKIPPED = [
    'content-type',
    'content-transfer-encoding',
    'mime-version',
    'date',
    'message-id',
    'to',
    'from',
    'subject',
    'return-path',
    'reply-to',
    'x-transport',
    'x-priority',
  ];

  /**
   * E-mail do Symfony a partir da mensagem do Core. O corpo já vem formatado (ver format()).
   */
  public function toEmail(array $message, string $fallbackFrom, ?string $html = NULL): Email {
    $headers = $message['headers'] ?? [];
    $email = (new Email())
      ->from(Address::create($message['from'] ?? $fallbackFrom))
      ->to(Address::create((string) $message['to']))
      ->subject((string) ($message['subject'] ?? ''))
      ->text((string) ($message['body'] ?? ''));
    if ($html !== NULL) {
      $email->html($html);
    }
    $this->attach($email, $message['params']['attachments'] ?? []);

    // O Core põe o Reply-To em 'reply-to', fora de 'headers'.
    $replyTo = $message['reply-to'] ?? $headers['Reply-to'] ?? $headers['Reply-To'] ?? NULL;
    if ($replyTo !== NULL && $replyTo !== '') {
      $email->replyTo(Address::create((string) $replyTo));
    }
    if (isset($headers['X-Priority'])) {
      $email->getHeaders()->addTextHeader('X-Priority', (string) $headers['X-Priority']);
    }
    foreach ($headers as $name => $value) {
      if (in_array(strtolower((string) $name), self::SKIPPED, TRUE) || !is_scalar($value)) {
        continue;
      }
      $email->getHeaders()->addTextHeader((string) $name, (string) $value);
    }
    return $email;
  }

  /**
   * Anexos do Core: conteúdo em memória (filecontent) ou caminho local legível (filepath).
   */
  private function attach(Email $email, array $attachments): void {
    foreach ($attachments as $attachment) {
      $name = (string) ($attachment['filename'] ?? 'anexo');
      $mime = (string) ($attachment['filemime'] ?? 'application/octet-stream');
      if (isset($attachment['filecontent'])) {
        $email->attach((string) $attachment['filecontent'], $name, $mime);
      }
      elseif (!empty($attachment['filepath']) && is_readable((string) $attachment['filepath'])) {
        $email->attachFromPath((string) $attachment['filepath'], $name, $mime);
      }
    }
  }

  /**
   * Entidade de transporte para a mensagem: o X-Transport informado, convertido; padrão quando ausente ou desconhecido.
   */
  public function transportId(array $message): string {
    $name = strtolower(trim((string) ($message['headers']['X-Transport'] ?? '')));
    return self::TRANSPORT_IDS[$name] ?? self::DEFAULT_TRANSPORT;
  }

}
