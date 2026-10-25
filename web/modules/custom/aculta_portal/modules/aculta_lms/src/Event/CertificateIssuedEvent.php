<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * A successfully persisted certificate issue.
 */
final readonly class CertificateIssuedEvent {

  public const NAME = self::class;

  public function __construct(
    public string $certificateUuid,
    public string $accountId,
  ) {
    if (trim($certificateUuid) === '' || !ctype_digit($accountId) || (int) $accountId < 1) {
      throw new \InvalidArgumentException('A certificate event requires a persisted certificate and account identifier.');
    }
  }

}
