<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Hook;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Retenção dos registros de envio: apaga registros mais antigos que o prazo, em lotes.
 */
final class MailLogRetentionHooks {

  /**
   * Prazo de retenção, em dias. Configurável em fase própria (ver docs/LOGGING-PRIVACY.md).
   */
  public const RETENTION_DAYS = 90;

  /**
   * Lote por execução de cron, para não sobrecarregar a requisição.
   */
  public const BATCH_SIZE = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $limite = $this->time->getRequestTime() - self::RETENTION_DAYS * 86400;
    $storage = $this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('created', $limite, '<')
      ->range(0, self::BATCH_SIZE)
      ->execute();
    if ($ids !== []) {
      $storage->delete($storage->loadMultiple($ids));
    }
  }

}
