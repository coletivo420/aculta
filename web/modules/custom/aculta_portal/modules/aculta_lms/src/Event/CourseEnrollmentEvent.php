<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * A persisted learner membership in a course class.
 */
final readonly class CourseEnrollmentEvent {

  public const NAME = self::class;

  public function __construct(
    public string $membershipUuid,
    public string $courseId,
    public string $classId,
    public string $accountId,
  ) {
    if (trim($membershipUuid) === '' || !ctype_digit($courseId) || !ctype_digit($classId)
      || !ctype_digit($accountId) || (int) $accountId < 1) {
      throw new \InvalidArgumentException('A course enrollment event requires persisted membership, course, class, and account identifiers.');
    }
  }

}
