<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Delivery\SmtpFailureRegistry;
use Drupal\aculta_mailer\Delivery\SmtpReplyClassifier;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Delivery\TransportFailover;
use Drupal\aculta_mailer\Policy\MailPolicyResolver;
use Drupal\aculta_mailer\Policy\TransportQuota;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Implementação de AcultaMailerInterface sobre o plugin.manager.mail do Core.
 *
 * O Core renderiza o e-mail pelo hook `mail` deste módulo (ver Hook\MailHooks). O transporte é escolhido por mensagem
 * pelo cabeçalho `X-Transport`, lido pelo MultiTransport do mailer_transport; sem transporte, vale o padrão configurado.
 */
final class AcultaMailer implements AcultaMailerInterface {

  public const MODULE = 'aculta_mailer';

  /**
   * Transporte usado quando o chamador não escolhe (padrão configurado no mailer_transport).
   */
  public const DEFAULT_TRANSPORT = 'default';

  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly MailTypeRegistry $types,
    private readonly LoggerChannelInterface $logger,
    private readonly MailLogRecorder $log,
    private readonly SmtpFailureRegistry $failures,
    private readonly SmtpReplyClassifier $classifier,
    private readonly MailPolicyResolver $policies,
    private readonly TransportQuota $quota,
    private readonly TransportFailover $failover,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function send(MailMessage $message): bool {
    return $this->deliver($message)->sent;
  }

  /**
   * {@inheritdoc}
   */
  public function deliver(MailMessage $message, ?string $transport = NULL): SendResult {
    if (!$this->types->has($message->type)) {
      $this->logger->error('Tipo de e-mail não registrado: @type (correlação @id).', [
        '@type' => $message->type,
        '@id' => $message->correlationId,
      ]);
      return SendResult::failed(DeliveryError::Permanent, NULL);
    }

    $policy = $this->policies->resolve($message->type);
    if ($policy->skip) {
      $this->log->record($message, MailStatus::Cancelled, 1, $transport ?? self::DEFAULT_TRANSPORT, 'policy_skip');
      return SendResult::cancelled();
    }

    $langcode = $message->langcode !== '' ? $message->langcode : LanguageInterface::LANGCODE_DEFAULT;
    $transport ??= $policy->transport;
    // Cota diária atingida: a mensagem segue pelo transporte de contingência, antes de a falha acontecer.
    $fallback = $this->failover->names()['fallback'];
    if ($transport !== NULL && $transport !== $fallback && $this->quota->exhausted($transport)) {
      $this->logger->warning('Cota diária do transporte @t atingida; usando @f.', [
        '@t' => $transport,
        '@f' => $fallback,
      ]);
      $transport = $fallback;
    }
    $result = $this->mailManager->mail(self::MODULE, $message->type, $message->to, $langcode, [
      'context' => $message->context,
      'correlation_id' => $message->correlationId,
      'transport' => $transport,
      'priority' => $policy->priority,
    ], $policy->replyTo, TRUE);

    $transportUsed = $transport ?? self::DEFAULT_TRANSPORT;
    if (!empty($result['result'])) {
      $this->log->record($message, MailStatus::Sent, 1, $transportUsed);
      return SendResult::sent();
    }

    // Falha: o código SMTP, se o transporte informou, chega pelo subscriber de FailedMessageEvent.
    $failure = $this->failures->take($message->correlationId);
    $code = $failure['code'] ?? NULL;
    $error = $this->classifier->classify($code);
    // O texto completo vai só para o registro (relatório do admin); o usuário vê a mensagem genérica.
    $this->log->record($message, MailStatus::Failed, 1, $transportUsed, $this->errorCode($code, $error), $failure['message'] ?? NULL);
    // Sem conteúdo do e-mail nem destinatário no log: só tipo, correlação e código.
    $this->logger->error('O sistema de e-mail não aceitou a mensagem @type (correlação @id, código @code).', [
      '@type' => $message->type,
      '@id' => $message->correlationId,
      '@code' => $code ?? 'sem código',
    ]);
    return SendResult::failed($error, $code);
  }

  /**
   * Código curto para o registro: o código SMTP quando conhecido, ou a classe do erro.
   */
  private function errorCode(?int $code, DeliveryError $error): string {
    return $code !== NULL ? 'smtp_' . $code : 'sem_codigo_' . $error->value;
  }

}
