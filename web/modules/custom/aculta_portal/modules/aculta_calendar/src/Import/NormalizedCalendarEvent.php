<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/**
 * Sanitized event data ready for persistence as an imported item. */
final class NormalizedCalendarEvent {

  public function __construct(
    public readonly string $uid,
    public readonly \DateTimeImmutable $start,
    public readonly \DateTimeImmutable $end,
    public readonly bool $allDay,
    public readonly string $timezone,
    public readonly bool $timezoneAssumed,
    public readonly string $title,
    public readonly string $description,
    public readonly string $location,
    public readonly string $status,
  ) {}

  /**
   * Maps iCalendar status to the local import lifecycle. */
  public function importState(): string {
    return $this->status === 'CANCELLED' ? 'cancelado' : 'rascunho';
  }

}
