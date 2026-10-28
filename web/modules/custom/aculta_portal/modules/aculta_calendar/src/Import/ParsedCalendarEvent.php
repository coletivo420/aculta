<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/** Parsed event values before the content-safety normalization in I3. */
final class ParsedCalendarEvent {

  public function __construct(
    public readonly string $uid,
    public readonly \DateTimeImmutable $start,
    public readonly \DateTimeImmutable $end,
    public readonly bool $allDay,
    public readonly ?string $timezone,
    public readonly string $title,
    public readonly string $description,
    public readonly string $location,
    public readonly string $status,
  ) {}

}
