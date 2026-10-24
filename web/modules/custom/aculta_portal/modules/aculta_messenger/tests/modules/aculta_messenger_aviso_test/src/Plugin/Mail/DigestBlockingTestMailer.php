<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_aviso_test\Plugin\Mail;

use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\Plugin\Mail\TestMailCollector;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test transport that opens a barrier while another Drupal worker competes.
 */
#[Mail(
  id: 'aculta_digest_blocking_test',
  label: new TranslatableMarkup('ACULTA digest concurrency test transport'),
  description: new TranslatableMarkup('Holds delivery until a competing Drupal worker has completed.'),
)]
final class DigestBlockingTestMailer extends TestMailCollector {

  /**
   * {@inheritdoc}
   */
  public function mail(array $message): bool {
    $barrierDirectory = getenv('ACULTA_DIGEST_BARRIER_DIR');
    if (!is_string($barrierDirectory) || $barrierDirectory === '') {
      throw new \RuntimeException('Digest concurrency test barrier is not configured.');
    }

    $started = $barrierDirectory . '/transport-started';
    $competitor = $barrierDirectory . '/competitor-done';
    if (file_put_contents($started, 'started', LOCK_EX) === FALSE) {
      throw new \RuntimeException('Could not signal the digest transport start.');
    }

    $deadline = microtime(TRUE) + 35;
    while (!is_file($competitor)) {
      if (microtime(TRUE) >= $deadline) {
        throw new \RuntimeException('Timed out waiting for the competing Drupal digest worker.');
      }
      usleep(10_000);
    }

    if (trim((string) file_get_contents($competitor)) !== 'locked') {
      return FALSE;
    }
    return parent::mail($message);
  }

}
