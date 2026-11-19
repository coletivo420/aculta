<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * A confirmed course completion published by an education provider.
 *
 * This event is a notification contract, not a progress record.
 */
final readonly class CourseCompletedEvent {

  public const NAME = self::class;

  /**
   * The status entity UUID for backward compatibility.
   *
   * @var string
   */
  public string $sourceStatusUuid;

  /**
   * Constructs a CourseCompletedEvent.
   *
   * @param string $sourceUuid
   *   The unique completion identifier.
   * @param string $courseId
   *   The course identifier.
   * @param string $accountId
   *   The user account ID.
   * @param \DateTimeImmutable $completedAt
   *   The date and time of completion.
   * @param string|null $sourceStatusUuid
   *   Optional backward-compatible status UUID.
   */
  public function __construct(
    public string $sourceUuid,
    public string $courseId,
    public string $accountId,
    public \DateTimeImmutable $completedAt,
    ?string $sourceStatusUuid = NULL,
  ) {
    if (trim($sourceUuid) === '' || trim($courseId) === '' || !ctype_digit($accountId) || (int) $accountId < 1) {
      throw new \InvalidArgumentException('A course completion event requires source, course, and authenticated account identifiers.');
    }
    $this->sourceStatusUuid = $sourceStatusUuid ?? $sourceUuid;
  }

}
