<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A passed course status recorded by Drupal LMS.
 *
 * This event is a notification contract, not another record of course
 * progress. Consumers must reload the source status and recheck access.
 */
final readonly class CourseCompletedEvent {

  public const NAME = self::class;

  public function __construct(
    public string $sourceStatusUuid,
    public string $courseId,
    public string $accountId,
    public DateTimeImmutable $completedAt,
  ) {
    if (trim($sourceStatusUuid) === '' || trim($courseId) === '' || !ctype_digit($accountId) || (int) $accountId < 1) {
      throw new InvalidArgumentException('A course completion event requires source, course, and authenticated account identifiers.');
    }
  }

}
