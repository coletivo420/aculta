<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;

use Drupal\aculta_mailer\Alert\MailAlerts;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Delivery\MailSuppression;
use Drupal\aculta_mailer\Delivery\MailPayloadRegistry;
use Drupal\aculta_mailer\Delivery\ProviderMessageRegistry;
use Drupal\aculta_mailer\Log\MailPayloadCipher;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Delivery\SmtpFailureRegistry;
use Drupal\aculta_mailer\Delivery\SmtpReplyClassifier;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Delivery\TransportChoice;
use Drupal\aculta_mailer\Delivery\TransportFailover;
use Drupal\aculta_mailer\Policy\MailPolicyResolver;
use Drupal\aculta_mailer\Policy\QuotaSignal;
use Drupal\aculta_mailer\Policy\QuotaSignatures;
use Drupal\aculta_mailer\Policy\TransportQuota;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Config\ConfigFactoryInterface;
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
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MailAlerts $alerts,
    private readonly QuotaSignal $quotaSignal,
    private readonly MailSuppression $suppression,
    private readonly ProviderMessageRegistry $providerMessages,
    private readonly MailPayloadRegistry $payloads,
    private readonly MailPayloadCipher $cipher,
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

    // Chave geral desligada: nada é enviado, e o registro mostra a mensagem como suspensa.
    if ($this->configFactory->get(DeliverySettings::CONFIG)->get('sending_enabled') === FALSE) {
      $this->log->record($message, MailStatus::Cancelled, 1, $transport ?? self::DEFAULT_TRANSPORT, 'envio_suspenso');
      return SendResult::cancelled();
    }

    // Destinatário na lista de supressão (devolução permanente, endereço inválido ou spam).
    if ($this->suppression->has($this->log->hashRecipient($message->to))) {
      $this->log->record($message, MailStatus::Cancelled, 1, $transport ?? self::DEFAULT_TRANSPORT, 'suprimido');
      return SendResult::cancelled();
    }

    $policy = $this->policies->resolve($message->type);
    if ($policy->skip) {
      $this->log->record($message, MailStatus::Cancelled, 1, $transport ?? self::DEFAULT_TRANSPORT, 'policy_skip');
      return SendResult::cancelled();
    }

    $langcode = $message->langcode !== '' ? $message->langcode : LanguageInterface::LANGCODE_DEFAULT;
    $transport ??= $policy->transport;
    // Transporte esgotado (cota) ou desligado é ignorado, inclusive a contingência (ver TransportChoice).
    $names = $this->failover->names();
    $transport = TransportChoice::choose($transport, $names['primary'], $names['fallback'], function (string $name): bool {
      return !$this->quota->exhausted($name)
        && !($name === 'brevo' && $this->configFactory->get(DeliverySettings::CONFIG)->get('brevo_enabled') === FALSE);
    });
    $effective = $transport ?? $this->failover->names()['primary'];
    $result = $this->mailManager->mail(self::MODULE, $message->type, $message->to, $langcode, [
      'context' => $message->context,
      'correlation_id' => $message->correlationId,
      'transport' => $transport,
      'priority' => $policy->priority,
    ], $policy->replyTo, TRUE);

    $transportUsed = $effective;
    if (!empty($result['result'])) {
      $this->payloads->take($message->correlationId);
      $this->log->record($message, MailStatus::Sent, 1, $transportUsed, NULL, NULL, $this->providerMessages->take($message->correlationId));
      return SendResult::sent();
    }

    // Falha: o código SMTP, se o transporte informou, chega pelo subscriber de FailedMessageEvent.
    $failure = $this->failures->take($message->correlationId);
    $code = $failure['code'] ?? NULL;
    // Cota ou limite do provedor é temporário para o failover, mesmo com código 5xx (ex.: 552 de cota mensal).
    $error = QuotaSignatures::matches((string) ($failure['message'] ?? ''))
      ? DeliveryError::Temporary
      : $this->classifier->classify($code);
    // O texto completo vai só para o registro (relatório do admin); o usuário vê a mensagem genérica.
    // Falha: o conteúdo vai ao registro cifrado, para o reenvio. Sem chave válida, nada é guardado.
    $payload = $this->payloads->take($message->correlationId);
    $cipher = $payload !== NULL ? $this->cipher->encrypt($payload) : NULL;
    $this->log->record($message, MailStatus::Failed, 1, $transportUsed, $this->errorCode($code, $error), $failure['message'] ?? NULL, NULL, $cipher);
    $this->alerts->failure($message->type, $message->correlationId, $transportUsed, $this->errorCode($code, $error), $failure['message'] ?? NULL);
    // Texto de cota no erro: o transporte sai de uso pela janela, e o envio seguinte já segue pela contingência.
    $this->quotaSignal->failure($transportUsed, (string) ($failure['message'] ?? ''));
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
