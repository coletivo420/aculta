<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

/**
 * Sanitizes external iCalendar event content before it is stored. */
final class CalendarEventNormalizer {

  public const DEFAULT_TIMEZONE = 'America/Sao_Paulo';
  public const MAX_TITLE_LENGTH = 255;
  public const MAX_DESCRIPTION_LENGTH = 8000;
  public const MAX_LOCATION_LENGTH = 255;

  /**
   * Converts parsed values to bounded, safe, timezone-explicit data.
   */
  public function normalize(ParsedCalendarEvent $event): NormalizedCalendarEvent {
    $timezoneAssumed = $event->timezone === NULL;
    $timezoneId = $timezoneAssumed ? self::DEFAULT_TIMEZONE : $event->timezone;
    if (!$this->isKnownTimezone($timezoneId)) {
      throw new \InvalidArgumentException("Fuso horário ICS desconhecido: $timezoneId.");
    }
    $timezone = new \DateTimeZone($timezoneId);

    // The parser keeps timezone-less wall times in UTC so that their original
    // clock values survive until the documented default zone is applied here.
    $start = $timezoneAssumed
      ? $this->reinterpretWallTime($event->start, $timezone)
      : $event->start->setTimezone($timezone);
    $end = $timezoneAssumed
      ? $this->reinterpretWallTime($event->end, $timezone)
      : $event->end->setTimezone($timezone);

    $status = strtoupper(trim($event->status));
    if (!in_array($status, ['CONFIRMED', 'TENTATIVE', 'CANCELLED'], TRUE)) {
      throw new \InvalidArgumentException("Estado ICS não suportado: $status.");
    }

    return new NormalizedCalendarEvent(
      $event->uid,
      $start,
      $end,
      $event->allDay,
      $timezoneId,
      $timezoneAssumed,
      $this->cleanText($event->title, self::MAX_TITLE_LENGTH),
      $this->cleanText($event->description, self::MAX_DESCRIPTION_LENGTH),
      $this->cleanText($event->location, self::MAX_LOCATION_LENGTH),
      $status,
    );
  }

  /**
   * Reinterprets a wall-clock value in a chosen timezone, without shifting it. */
  private function reinterpretWallTime(\DateTimeImmutable $date, \DateTimeZone $timezone): \DateTimeImmutable {
    $wallTime = $date->format('Y-m-d H:i:s');
    $normalized = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wallTime, $timezone);
    if ($normalized === FALSE) {
      throw new \InvalidArgumentException('Data e hora ICS inválidas.');
    }
    return $normalized;
  }

  /**
   * Checks against timezone identifiers rather than PHP's abbreviation aliases. */
  private function isKnownTimezone(string $timezone): bool {
    if (in_array($timezone, ['UTC', 'GMT'], TRUE)) {
      return TRUE;
    }
    return in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), TRUE);
  }

  /**
   * Decodes entities, removes script/style blocks and markup, then truncates.
   */
  private function cleanText(string $value, int $maximumLength): string {
    $text = $value;
    // Decode nested entities a bounded number of times so encoded markup is
    // stripped before it can be stored as ordinary text.
    for ($pass = 0; $pass < 8; $pass++) {
      $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
      if ($decoded === $text) {
        break;
      }
      $text = $decoded;
    }

    $text = preg_replace('~<\s*(script|style)\b[^>]*>.*?(?:</\s*\1\s*>|$)~is', ' ', $text) ?? $text;
    $text = strip_tags($text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    return mb_substr(trim($text), 0, $maximumLength, 'UTF-8');
  }

}
