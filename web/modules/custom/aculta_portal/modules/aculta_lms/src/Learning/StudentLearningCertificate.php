<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Url;

/**
 * Public-safe certificate details for the student's dashboard.
 */
final readonly class StudentLearningCertificate {

  public function __construct(
    public string $courseTitle,
    public string $state,
    public int $issuedAt,
    public Url $verificationUrl,
  ) {}

}
