<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Log;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Retenção dos registros de envio: apaga registros mais antigos que o prazo configurado, em lotes.
 */
final class MailLogRetention {

  /**
   * Lote por execução de cron, para não sobrecarregar a requisição.
   */
  public const BATCH_SIZE = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly MailLogSettings $settings,
  ) {}

  /**
   * Apaga o conteúdo cifrado para reenvio cujo prazo passou (link de uso único, 24 horas). O registro continua.
   *
   * @return int
   *   Quantidade de conteúdos apagados nesta execução.
   */
  public function purgeExpiredPayloads(): int {
    $storage = $this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('payload_expires', $this->time->getRequestTime(), '<=')
      ->range(0, self::BATCH_SIZE)
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
   * Apaga um lote de registros mais antigos que o prazo de retenção.
   */
  public function cron(): void {
    $limite = $this->time->getRequestTime() - $this->settings->retentionDays() * 86400;
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
