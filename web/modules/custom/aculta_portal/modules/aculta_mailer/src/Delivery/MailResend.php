<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

use Drupal\aculta_mailer\Entity\MailTransport;
use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Log\MailPayloadCipher;
use Drupal\aculta_mailer\Log\SingleUseLinkMail;
use Drupal\aculta_mailer\Mail\LegacyMessageConverter;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Transport\MailTransportSender;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Reenvio de uma mensagem com falha, a partir do conteúdo cifrado guardado no registro.
 *
 * Usa o mesmo caminho de envio do módulo (transporte, cabeçalhos e registro) e respeita a chave geral de envio e a lista
 * de supressão. O registro é atualizado no lugar: o número de tentativas sobe, e o conteúdo cifrado só é apagado quando
 * a mensagem é aceita.
 */
final class MailResend {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly MailTransportSender $sender,
    private readonly LegacyMessageConverter $converter,
    private readonly MailPayloadCipher $cipher,
    private readonly MailLogRecorder $log,
    private readonly MailSuppression $suppression,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Pode reenviar: a mensagem está com falha, tem conteúdo cifrado guardado e não é link de uso único.
   */
  public function canResend(string $logId): bool {
    $log = $this->load($logId);
    if ($log === NULL || (string) $log->get('status')->value !== MailStatus::Failed->value || (string) $log->get('payload_cipher')->value === '') {
      return FALSE;
    }
    // Link de uso único não é reenviado com o conteúdo antigo: o pedido precisa ser refeito, e o link novo é gerado então.
    return !SingleUseLinkMail::applies('aculta_mailer', (string) $log->get('type')->value);
  }

  /**
   * Reenvia pelo transporte informado. Retorna o resultado do envio.
   */
  public function resend(string $logId, string $transportName): SendResult {
    $log = $this->load($logId);
    if (!$this->canResend($logId) || $log === NULL) {
      return SendResult::failed(DeliveryError::Permanent, NULL);
    }
    if ($this->configFactory->get(DeliverySettings::CONFIG)->get('sending_enabled') === FALSE) {
      return SendResult::cancelled();
    }
    $payload = $this->cipher->decrypt((string) $log->get('payload_cipher')->value);
    if ($payload === NULL || !isset($payload['to'])) {
      $this->logger->error('Reenvio recusado: conteúdo cifrado ilegível ou chave diferente (registro @id).', ['@id' => $logId]);
      return SendResult::failed(DeliveryError::Permanent, NULL);
    }
    if ($this->suppression->has($this->log->hashRecipient((string) $payload['to']))) {
      return SendResult::cancelled();
    }
    $transport = $this->entityTypes->getStorage(MailTransport::ENTITY_TYPE)->load($this->converter->transportId(['headers' => ['X-Transport' => $transportName]]));
    if (!$transport instanceof MailTransport) {
      return SendResult::failed(DeliveryError::Permanent, NULL);
    }
    $email = $this->converter->toEmail($payload, (string) ($payload['from'] ?? ''), isset($payload['html']) ? (string) $payload['html'] : NULL);
    $result = $this->sender->send($transport, $email);
    $message = new MailMessage((string) $log->get('type')->value, (string) $payload['to'], (string) $log->get('langcode')->value, [], (string) $log->get('correlation_id')->value);
    $attempt = (int) $log->get('attempts')->value + 1;
    if ($result['sent']) {
      $this->log->record($message, MailStatus::Sent, $attempt, $transportName, NULL, NULL, $result['provider_id'], NULL);
      return SendResult::sent();
    }
    // Falha de novo: o registro mostra a nova tentativa e mantém o conteúdo cifrado para outra tentativa.
    $code = $result['code'];
    $errorCode = $code !== NULL ? 'smtp_' . $code : 'sem_codigo_temporaria';
    $this->log->record($message, MailStatus::Failed, $attempt, $transportName, $errorCode, $result['message']);
    return SendResult::failed(DeliveryError::Temporary, $code);
  }

  /**
   * Registro do envio pelo ID, ou NULL.
   */
  private function load(string $logId): ?object {
    return $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE)->load($logId);
  }

}
