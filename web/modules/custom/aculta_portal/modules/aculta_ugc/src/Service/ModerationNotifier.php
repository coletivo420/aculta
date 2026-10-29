<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_ugc\Entity\UgcReport;

/**
 * Notificador de moderação com proteção de privacidade (D-03).
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

    $recipients = $this->getRecipientsForPurpose($purpose);

    // Registra envio mantendo zero dados sobre o autor da denúncia (D-03).
    $this->dispatchedNotifications[] = [
      'purpose' => $purpose,
      'target_type' => $targetType,
      'target_id' => $targetId,
      'motivo' => $motivo,
      'recipients' => $recipients,
    ];
  }

  /**
   * Retorna os e-mails dos moderadores configurados para o purpose.
   *
   * @return string[]
   */
  public function getRecipientsForPurpose(string $purpose): array {
    $configStorage = $this->entityTypeManager->getStorage('aculta_ugc_recipients');
    /** @var \Drupal\aculta_ugc\Entity\UgcRecipientConfig|null $config */
    $config = $configStorage->load($purpose);

    if ($config !== NULL && !empty($config->getEmails())) {
      return $config->getEmails();
    }

    // Fallback padrão se configurado.
    /** @var \Drupal\aculta_ugc\Entity\UgcRecipientConfig|null $defaultConfig */
    $defaultConfig = $configStorage->load('default');
    return $defaultConfig !== NULL ? $defaultConfig->getEmails() : ['moderacao@aculta.org'];
  }

  /**
   * Retorna os avisos disparados (usado em asserções de teste).
   *
   * @return array<int, array{purpose: string, target_type: string, target_id: int, motivo: string, recipients: string[]}>
   */
  public function getDispatchedNotifications(): array {
    return $this->dispatchedNotifications;
  }

}