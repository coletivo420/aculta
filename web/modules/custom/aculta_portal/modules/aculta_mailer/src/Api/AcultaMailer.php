<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Implementação de AcultaMailerInterface sobre o plugin.manager.mail do Core.
 *
 * O Core renderiza o e-mail pelo hook `mail` deste módulo (ver Hook\MailHooks), que consulta o tipo registrado.
 */
final class AcultaMailer implements AcultaMailerInterface {

  public const MODULE = 'aculta_mailer';

  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly MailTypeRegistry $types,
    private readonly LoggerChannelInterface $logger,
    private readonly MailLogRecorder $log,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function send(MailMessage $message): bool {
    if (!$this->types->has($message->type)) {
      $this->logger->error('Tipo de e-mail não registrado: @type (correlação @id).', [
        '@type' => $message->type,
        '@id' => $message->correlationId,
      ]);
      return FALSE;
    }

    $langcode = $message->langcode !== '' ? $message->langcode : LanguageInterface::LANGCODE_DEFAULT;
    $result = $this->mailManager->mail(self::MODULE, $message->type, $message->to, $langcode, [
      'context' => $message->context,
      'correlation_id' => $message->correlationId,
    ], NULL, TRUE);

    $sent = (bool) ($result['result'] ?? FALSE);
    $this->log->record($message, $sent ? MailStatus::Sent : MailStatus::Failed, 1, 'default', $sent ? NULL : 'send_refused');
    if (!$sent) {
      // Sem conteúdo do e-mail nem destinatário no log: só tipo e correlação.
      $this->logger->error('O sistema de e-mail não aceitou a mensagem @type (correlação @id).', [
        '@type' => $message->type,
        '@id' => $message->correlationId,
      ]);
    }
    return $sent;
  }

}
