<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/**
 * Minimal local RFC 5545 reader for the supported event fields.
 *
 * This parser intentionally has no network support and no third-party library.
 * It is not an implementation of every iCalendar component or recurrence rule.
 */
final class IcsCalendarFileReader implements CalendarFileReader {

  /** Maximum accepted file size: 2 MiB. */
  public const MAX_FILE_BYTES = 2 * 1024 * 1024;

  /** Maximum accepted number of VEVENT components. */
  public const MAX_EVENTS = 2000;

  /** {@inheritdoc} */
  public function read(string $path): array {
    if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $path) === 1 || !is_file($path) || !is_readable($path)) {
      throw new \InvalidArgumentException('A importação aceita apenas um arquivo ICS local legível.');
    }

    $size = filesize($path);
    if ($size === FALSE) {
      throw new \RuntimeException('Não foi possível determinar o tamanho do arquivo ICS.');
    }
    if ($size > self::MAX_FILE_BYTES) {
      throw new CalendarFileLimitException('Arquivo ICS excede o limite de 2 MB.');
    }

    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      throw new \RuntimeException('Não foi possível ler o arquivo ICS local.');
    }
    // Guard against a size change between filesize() and file_get_contents().
    if (strlen($contents) > self::MAX_FILE_BYTES) {
      throw new CalendarFileLimitException('Arquivo ICS excede o limite de 2 MB.');
    }

    $lines = $this->unfoldLines($contents);
    if ($lines === [] || strtoupper($lines[0]) !== 'BEGIN:VCALENDAR') {
      throw new \InvalidArgumentException('O arquivo não começa com BEGIN:VCALENDAR.');
    }

    $events = [];
    $event = NULL;
    $componentDepth = 0;
    foreach ($lines as $line) {
      if (preg_match('/^BEGIN:([A-Z0-9-]+)$/i', $line, $match) === 1) {
        $componentDepth++;
        if (strtoupper($match[1]) === 'VEVENT' && $componentDepth === 2) {
          if (count($events) >= self::MAX_EVENTS) {
            throw new CalendarFileLimitException('O arquivo ICS excede o limite de 2.000 eventos.');
          }
          $event = [];
        }
        continue;
      }

      if (preg_match('/^END:([A-Z0-9-]+)$/i', $line, $match) === 1) {
        if (strtoupper($match[1]) === 'VEVENT' && $componentDepth === 2 && $event !== NULL) {
          $events[] = $this->createEvent($event);
          $event = NULL;
        }
        $componentDepth = max(0, $componentDepth - 1);
        continue;
      }

      if ($event === NULL || $componentDepth !== 2) {
        continue;
      }
      $property = $this->parseProperty($line);
      if ($property === NULL) {
        continue;
      }
      [$name, $parameters, $value] = $property;
      // For duplicate properties, retain the first value; multiple ATTENDEE or
      // other properties are outside the fields consumed by this importer.
      $event[$name] ??= ['parameters' => $parameters, 'value' => $value];
    }

    if ($event !== NULL || $componentDepth !== 0) {
      throw new \InvalidArgumentException('O arquivo ICS terminou com um componente incompleto.');
    }
    if (count($events) > self::MAX_EVENTS) {
      throw new CalendarFileLimitException('O arquivo ICS excede o limite de 2.000 eventos.');
    }

    return $events;
  }

  /** Unfolds RFC 5545 continuation lines. */
  private function unfoldLines(string $contents): array {
    $physicalLines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
    $lines = [];
    foreach ($physicalLines as $line) {
      if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
        if ($lines === []) {
          throw new \InvalidArgumentException('O arquivo ICS começa com uma linha continuada inválida.');
        }
        $last = array_key_last($lines);
        $lines[$last] .= substr($line, 1);
      }
      else {
        $lines[] = $line;
      }
    }
    while ($lines !== [] && end($lines) === '') {
      array_pop($lines);
    }
    return $lines;
  }

  /** Parses a content line into property name, parameters, and value. */
  private function parseProperty(string $line): ?array {
    $separator = strpos($line, ':');
    if ($separator === FALSE) {
      return NULL;
    }
    $left = substr($line, 0, $separator);
    $value = substr($line, $separator + 1);
    $parts = explode(';', $left);
    $name = strtoupper(array_shift($parts) ?? '');
    if ($name === '') {
      return NULL;
    }
    $parameters = [];
    foreach ($parts as $part) {
      [$key, $parameterValue] = array_pad(explode('=', $part, 2), 2, '');
      if ($key !== '') {
        $parameters[strtoupper($key)] = trim($parameterValue, '"');
      }
    }
    return [$name, $parameters, $value];
  }

  /** Creates the normalized event DTO from its supported properties. */
  private function createEvent(array $properties): ParsedCalendarEvent {
    $uid = $properties['UID']['value'] ?? '';
    if ($uid === '') {
      throw new \InvalidArgumentException('Um evento ICS não tem UID.');
    }
    if (!isset($properties['DTSTART'])) {
      throw new \InvalidArgumentException("O evento ICS '$uid' não tem DTSTART.");
    }

    [$start, $allDay, $timezone] = $this->parseDate($properties['DTSTART']);
    if (isset($properties['DTEND'])) {
      [$end, $endAllDay] = $this->parseDate($properties['DTEND']);
      if ($endAllDay !== $allDay) {
        throw new \InvalidArgumentException("O evento ICS '$uid' mistura data e data-hora.");
      }
    }
    else {
      $end = $allDay ? $start->modify('+1 day') : $start;
    }
    if ($end < $start) {
      throw new \InvalidArgumentException("O evento ICS '$uid' termina antes de começar.");
    }

    return new ParsedCalendarEvent(
      $uid,
      $start,
      $end,
      $allDay,
      $timezone,
      $this->unescapeText($properties['SUMMARY']['value'] ?? ''),
      $this->unescapeText($properties['DESCRIPTION']['value'] ?? ''),
      $this->unescapeText($properties['LOCATION']['value'] ?? ''),
      strtoupper($properties['STATUS']['value'] ?? 'CONFIRMED'),
    );
  }

  /** Parses DTSTART/DTEND and returns whether it is an all-day date. */
  private function parseDate(array $property): array {
    $value = $property['value'];
    $timezoneId = $property['parameters']['TZID'] ?? NULL;
    if ($timezoneId !== NULL) {
      try {
        $timezone = new \DateTimeZone($timezoneId);
      }
      catch (\Exception $exception) {
        throw new \InvalidArgumentException("Fuso horário ICS desconhecido: $timezoneId.", 0, $exception);
      }
    }
    else {
      // Keep timezone-less wall times in UTC until I3 applies the documented
      // America/Sao_Paulo default and records that it was assumed.
      $timezone = new \DateTimeZone('UTC');
    }

    $allDay = ($property['parameters']['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value) === 1;
    $format = $allDay ? '!Ymd' : '!Ymd\\THis';
    $dateValue = $allDay ? substr($value, 0, 8) : $value;
    $explicitUtc = !$allDay && str_ends_with($dateValue, 'Z');
    if ($explicitUtc) {
      $dateValue = substr($dateValue, 0, -1);
      $timezone = new \DateTimeZone('UTC');
      $format = '!Ymd\\THis';
      $timezoneId = 'UTC';
    }

    $date = \DateTimeImmutable::createFromFormat($format, $dateValue, $timezone);
    $errors = \DateTimeImmutable::getLastErrors();
    if ($date === FALSE || ($errors !== FALSE && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
      throw new \InvalidArgumentException("Data ICS inválida: $value.");
    }
    return [$date, $allDay, $timezoneId];
  }

  /** Unescapes RFC 5545 TEXT values; HTML safety is applied in I3. */
  private function unescapeText(string $value): string {
    return preg_replace_callback('/\\\\([nN,;\\\\])/', static function (array $match): string {
      return match (strtolower($match[1])) {
        'n' => "\n",
        default => $match[1],
      };
    }, $value) ?? $value;
  }

}
