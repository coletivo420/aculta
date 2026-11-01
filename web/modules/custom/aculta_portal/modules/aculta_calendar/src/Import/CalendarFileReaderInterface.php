<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/**
 * Reads local iCalendar files into normalized event records.
 */
interface CalendarFileReaderInterface {

  /**
   * Reads events from a local .ics file.
   *
   * @return \Drupal\aculta_calendar\Import\ParsedCalendarEvent[]
   *   Events in file order.
   */
  public function read(string $path): array;

}
