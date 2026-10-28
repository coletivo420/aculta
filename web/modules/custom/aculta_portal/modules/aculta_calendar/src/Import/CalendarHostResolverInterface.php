<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/** Resolves a remote host to the IP addresses returned by DNS. */
interface CalendarHostResolverInterface {

  /**
   * Returns all A and AAAA addresses for a hostname.
   *
   * @return string[]
   */
  public function resolve(string $hostname): array;

}
