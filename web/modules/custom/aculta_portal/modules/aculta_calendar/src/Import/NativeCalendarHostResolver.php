<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/**
 * Uses the system resolver to look up A and AAAA records. */
final class NativeCalendarHostResolver implements CalendarHostResolverInterface {

  /**
   * Resolves an approved calendar hostname to its IP addresses.
   */
  public function resolve(string $hostname): array {
    $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);
    if (!is_array($records)) {
      throw new \RuntimeException('Não foi possível resolver o host da fonte iCalendar.');
    }
    $addresses = [];
    foreach ($records as $record) {
      if (isset($record['ip'])) {
        $addresses[] = $record['ip'];
      }
      if (isset($record['ipv6'])) {
        $addresses[] = $record['ipv6'];
      }
    }
    return array_values(array_unique($addresses));
  }

}
