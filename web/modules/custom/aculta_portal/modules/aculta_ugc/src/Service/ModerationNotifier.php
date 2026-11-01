<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_ugc\Mail\UgcReportNoticeMailType;
use Psr\Log\LoggerInterface;

/**
 * Notificador de moderação com proteção de privacidade (D-03, ARQ-06B).
 *
 * Integra com a plataforma de e-mail transacional `aculta_mailer` quando
 * disponível. Preserva a privacidade estrita do denunciante: nenhuma
 * informação de identidade ou UID é exposta em mensagens ou logs.
 */
final class ModerationNotifier {

  /**
   * Log em memória para validação em testes.
   *
   * @var array<int, array{purpose: string, target_type: string, target_id: int, motivo: string, recipients: string[]}>
   */
  private array $dispatchedNotifications = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
    private readonly ?AcultaMailerInterface $mailer = NULL,
  ) {}

  /**
   * Envia aviso aos moderadores responsáveis pelo purpose da denúncia.
   *
   * Preserva a privacidade estrita: nenhum dado do denunciante é transmitido.
   */
  public function notifyNewReport(UgcReport $report): void {
    $purpose = (string) $report->get('purpose')->value;
    $targetType = (string) $report->get('target_type')->value;
    $targetId = (int) $report->get('target_id')->value;
    $motivo = (string) $report->get('motivo')->value;
    $reportUuid = (string) $report->uuid();

    $recipients = $this->getRecipientsForPurpose($purpose);

    // Registra envio mantendo zero dados sobre o autor da denúncia (D-03).
    $this->dispatchedNotifications[] = [
      'purpose' => $purpose,
      'target_type' => $targetType,
      'target_id' => $targetId,
      'motivo' => $motivo,
      'recipients' => $recipients,
    ];

    if (empty($recipients)) {
      $this->logger->warning('Nenhum moderador configurado para o purpose @purpose; aviso de denúncia suprimido.', [
        '@purpose' => $purpose,
      ]);
      return;
    }

    // Se o serviço do aculta_mailer estiver disponível, despacha as mensagens.
    if ($this->mailer !== NULL && class_exists(MailMessage::class)) {
      foreach ($recipients as $email) {
        $this->sendNoticeEmail($reportUuid, $email, $purpose, $targetType, $targetId, $motivo);
      }
    }
  }

  /**
   * Despacha e-mail para um destinatário com idempotência e proteção de privacidade.
   */
  private function sendNoticeEmail(
    string $reportUuid,
    string $email,
    string $purpose,
    string $targetType,
    int $targetId,
    string $motivo,
  ): void {
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
      $this->logger->warning('Destinatário de moderação inválido: @email', ['@email' => $email]);
      return;
    }

    $idempotencyKey = sprintf('ugc_report:%s:%s', $reportUuid, strtolower($email));
    $correlationId = class_exists(MessageIdFactory::class)
      ? MessageIdFactory::correlationIdFor($idempotencyKey)
      : $this->generateCorrelationId($idempotencyKey);

    $context = [
      'purpose' => $purpose,
      'tipo_entidade' => $targetType,
      'id_entidade' => (string) $targetId,
      'motivo' => $motivo,
      'referencia' => $reportUuid,
    ];

    try {
      $message = new MailMessage(
        UgcReportNoticeMailType::ID,
        $email,
        'pt-br',
        $context,
        $correlationId,
      );
      $result = $this->mailer->deliver($message);
      if (!$result->isAccepted()) {
        $this->logger->error('Falha de entrega do aviso de denúncia (@correlation).', [
          '@correlation' => $correlationId,
        ]);
      }
    }
    catch (\Throwable $e) {
      $this->logger->error('Exceção ao enviar aviso de moderação (@correlation): @message', [
        '@correlation' => $correlationId,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Gera UUID v5 compatível com RFC 4122 para correlação idempotente.
   */
  private function generateCorrelationId(string $idempotencyKey): string {
    $namespace = pack('H*', str_replace('-', '', 'e2198ec4-3f89-5f0e-8c7d-5f937abe12c1'));
    $bytes = substr(sha1($namespace . $idempotencyKey, TRUE), 0, 16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x50);
    $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
      '%s-%s-%s-%s-%s',
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12),
    );
  }

  /**
   * Retorna os e-mails dos moderadores configurados para o purpose.
   *
   * Não inventa endereços fictícios de destinatários (ARQ-06B).
   *
   * @param string $purpose
   *   O purpose a consultar.
   *
   * @return string[]
   *   Lista de endereços válidos de moderadores.
   */
  public function getRecipientsForPurpose(string $purpose): array {
    $configStorage = $this->entityTypeManager->getStorage('aculta_ugc_recipients');
    /** @var \Drupal\aculta_ugc\Entity\UgcRecipientConfig|null $config */
    $config = $configStorage->load($purpose);

    if ($config !== NULL && !empty($config->getEmails())) {
      return array_values(array_filter($config->getEmails(), static fn (string $mail): bool => (bool) filter_var($mail, FILTER_VALIDATE_EMAIL)));
    }

    // Fallback padrão homologado se configurado como 'default'.
    /** @var \Drupal\aculta_ugc\Entity\UgcRecipientConfig|null $defaultConfig */
    $defaultConfig = $configStorage->load('default');
    if ($defaultConfig !== NULL && !empty($defaultConfig->getEmails())) {
      return array_values(array_filter($defaultConfig->getEmails(), static fn (string $mail): bool => (bool) filter_var($mail, FILTER_VALIDATE_EMAIL)));
    }

    return [];
  }

  /**
   * Retorna os avisos disparados (usado em asserções de teste).
   *
   * @return array<int, array{purpose: string, target_type: string, target_id: int, motivo: string, recipients: string[]}>
   *   Lista dos avisos em memória.
   */
  public function getDispatchedNotifications(): array {
    return $this->dispatchedNotifications;
  }

}
