<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Learning;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Authorized learning summary without provider entities or render arrays.
 */
final readonly class StudentLearningSummary {

  /**
   * @param array<int, \Drupal\aculta_learning_contracts\Learning\StudentLearningCourse> $courses
   *   Courses visible to this account.
   * @param array<int, \Drupal\aculta_learning_contracts\Learning\StudentLearningCertificate> $certificates
   *   Certificates visible to this account.
   */
  public function __construct(
    public array $courses,
    public ?StudentLearningCourse $continueCourse,
    public array $certificates,
    public CacheableMetadata $cache,
  ) {}

}
