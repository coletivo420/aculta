<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Email;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Lê o `aculta_mail_log` de um dia UTC e devolve o funil de e-mails (somente leitura).
 *
 * O registro é a fonte única: webhook do Brevo, monitor IMAP e envio gravam nele pelo
 * `correlation_id`, então cada envio aparece uma vez. Nada é gravado aqui.
 */
final class EmailProvider implements EmailProviderInterface {

  private const DAY_SECONDS = 86400;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function countsForDay(int $day): array {
    $start = $day * self::DAY_SECONDS;
    $storage = $this->entityTypeManager->getStorage(MailLogRecorder::ENTITY_TYPE);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('created', [$start, $start + self::DAY_SECONDS - 1], 'BETWEEN')
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $log) {
      $rows[] = [
        'type' => (string) $log->get('type')->value,
        'status' => (string) $log->get('status')->value,
        'transport' => (string) $log->get('transport')->value,
        'error_code' => (string) $log->get('error_code')->value,
        'accepted' => !empty($log->get('sent')->value),
      ];
    }
    return EmailFunnel::count($rows);
  }

}
