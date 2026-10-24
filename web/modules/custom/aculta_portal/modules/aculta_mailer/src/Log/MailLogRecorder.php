<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Log;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

/**
 * Grava ou atualiza o registro de um envio, identificado pelo correlation ID.
 *
 * Guarda estado, tentativas, transporte e código de erro. Não guarda corpo, subject, token nem o endereço em claro.
 */
final class MailLogRecorder {

  public const ENTITY_TYPE = 'aculta_mail_log';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Settings $settings,
    private readonly TimeInterface $time,
    private readonly MailLogSettings $logSettings,
  ) {}

  /**
   * Registra o estado atual do envio.
   *
   * @param \Drupal\aculta_mailer\Message\MailMessage $message
   *   Mensagem enviada ou em tentativa.
   * @param \Drupal\aculta_mailer\Message\MailStatus $status
   *   Estado a registrar.
   * @param int $attempt
   *   Número da tentativa.
   * @param string $transport
   *   Nome do transporte usado nesta tentativa.
   * @param string|null $errorCode
   *   Código curto e controlado pelo módulo.
   * @param string|null $errorMessage
   *   Texto completo da falha do transporte, para o relatório do admin (ver LOGGING-PRIVACY.md).
   * @param string|null $providerMessageId
   *   ID que o provedor deu à mensagem aceita; sem valor, o registro não é alterado.
   * @param string|null $payloadCipher
   *   Conteúdo cifrado para reenvio (ver MailPayloadCipher); sem valor, o campo não muda.
   * @param int|null $payloadExpires
   *   Instante em que o conteúdo cifrado deve ser apagado (link de uso único); NULL: segue a retenção do registro.
   */
  public function record(MailMessage $message, MailStatus $status, int $attempt, string $transport, ?string $errorCode = NULL, ?string $errorMessage = NULL, ?string $providerMessageId = NULL, ?string $payloadCipher = NULL, ?int $payloadExpires = NULL): void {
    if (!$this->logSettings->enabled()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
    $found = $storage->loadByProperties(['correlation_id' => $message->correlationId]);
    $log = $found === [] ? $storage->create([
      'correlation_id' => $message->correlationId,
      'type' => $message->type,
      'recipient_hash' => $this->hashRecipient($message->to),
      'langcode' => $message->langcode,
    ]) : reset($found);

    $log->set('status', $status->value);
    $log->set('attempts', $attempt);
    $log->set('transport', $transport);
    $log->set('error_code', $errorCode);
    $log->set('error_message', $errorMessage);
    if ($providerMessageId !== NULL && $providerMessageId !== '') {
      $log->set('provider_message_id', $providerMessageId);
    }
    // Conteúdo para reenvio só existe enquanto a mensagem não foi aceita: ao ser aceita, o campo é apagado.
    if ($payloadCipher !== NULL) {
      $log->set('payload_cipher', $payloadCipher);
      $log->set('payload_expires', $payloadExpires);
    }
    elseif ($status === MailStatus::Sent) {
      $log->set('payload_cipher', NULL);
      $log->set('payload_expires', NULL);
    }
    if ($status === MailStatus::Sent) {
      $log->set('sent', $this->time->getRequestTime());
    }
    $log->save();
  }

  /**
   * Checks whether this exact logical delivery was already accepted.
   *
   * @param \Drupal\aculta_mailer\Message\MailMessage $message
   *   Message identity to check.
   *
   * @return bool
   *   TRUE when a successful log matches correlation, type and recipient.
   */
  public function wasAccepted(MailMessage $message): bool {
    if (!$this->logSettings->enabled()) {
      return FALSE;
    }
    $found = $this->entityTypeManager->getStorage(self::ENTITY_TYPE)->loadByProperties([
      'correlation_id' => $message->correlationId,
      'type' => $message->type,
      'recipient_hash' => $this->hashRecipient($message->to),
      'status' => MailStatus::Sent->value,
    ]);
    return $found !== [];
  }

  /**
   * Indicates whether accepted idempotent sends can be recognized on retry.
   */
  public function idempotencyAvailable(): bool {
    return $this->logSettings->enabled();
  }

  /**
   * Cancela o conteúdo guardado para reenvio das mensagens anteriores do mesmo destinatário e tipo.
   *
   * Usado quando o usuário pede um novo link de uso único: o link antigo não é reaproveitado. Exceção: a retentativa
   * automática da mesma mensagem (fila e failover) mantém o conteúdo e o link, por isso a própria correlação fica de fora. O registro anterior
   * continua, sem conteúdo, e o novo pedido gera outra cifra com conteúdo novo.
   *
   * @return int
   *   Quantidade de conteúdos cancelados.
   */
  public function cancelPayloads(string $recipientHash, string $type, string $exceptCorrelationId): int {
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('recipient_hash', $recipientHash)
      ->condition('type', $type)
      // A própria mensagem (retentativa automática da fila) não se cancela: é a mesma solicitação.
      ->condition('correlation_id', $exceptCorrelationId, '<>')
      ->condition('payload_cipher', NULL, 'IS NOT NULL')
      ->execute();
    $count = 0;
    foreach ($storage->loadMultiple($ids) as $log) {
      $log->set('payload_cipher', NULL);
      $log->set('payload_expires', NULL);
      $log->save();
      $count++;
    }
    return $count;
  }

  /**
   * HMAC do endereço normalizado, com o hash_salt do ambiente. Permite correlacionar sem expor o endereço.
   */
  public function hashRecipient(string $to): string {
    return hash_hmac('sha256', mb_strtolower(trim($to)), (string) $this->settings->get('hash_salt', ''));
  }

  /**
   * Indica se o hash_salt existe. Sem ele o HMAC do destinatário seria calculado com chave vazia (previsível).
   */
  public function recipientKeyAvailable(): bool {
    return (string) $this->settings->get('hash_salt', '') !== '';
  }

}
