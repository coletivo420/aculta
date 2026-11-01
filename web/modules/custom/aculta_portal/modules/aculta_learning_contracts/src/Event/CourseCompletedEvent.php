<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Event;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A confirmed course completion published by an education provider.
 *
 * This event is a notification contract, not a progress record. The source UUID
 * is opaque to consumers and exists for traceability and idempotent delivery.
 */
final readonly class CourseCompletedEvent {

  public const NAME = self::class;

  public function __construct(
    public string $sourceUuid,
    public string $courseId,
    public string $accountId,
    public DateTimeImmutable $completedAt,
  ) {
    if (trim($sourceUuid) === '' || trim($courseId) === '' || !ctype_digit($accountId) || (int) $accountId < 1) {
      throw new InvalidArgumentException('A course completion event requires source, course, and authenticated account identifiers.');
    }
  }

}
