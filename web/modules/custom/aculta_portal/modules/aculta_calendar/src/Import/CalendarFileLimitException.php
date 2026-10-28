<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/** Raised when a local calendar file exceeds a configured processing limit. */
final class CalendarFileLimitException extends \RuntimeException {}
