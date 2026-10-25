<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_aviso_test\Plugin\Mail;

use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\Plugin\Mail\TestMailCollector;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test transport that records accepted attempts across independent processes.
 */
#[Mail(
  id: 'aculta_digest_crash_test',
  label: new TranslatableMarkup('ACULTA digest crash recovery test transport'),
  description: new TranslatableMarkup('Records test deliveries in a shared marker file.'),
)]
final class DigestCrashTestMailer extends TestMailCollector {

  /**
   * {@inheritdoc}
   */
  public function mail(array $message): bool {
    $marker = getenv('ACULTA_DIGEST_CRASH_COUNT_MARKER');
    if (!is_string($marker) || $marker === '') {
      throw new \RuntimeException('The digest crash transport marker is missing.');
    }
    if (file_put_contents($marker, "sent\n", FILE_APPEND | LOCK_EX) === FALSE) {
      throw new \RuntimeException('Could not record the test transport acceptance.');
    }
    return parent::mail($message);
  }

}
