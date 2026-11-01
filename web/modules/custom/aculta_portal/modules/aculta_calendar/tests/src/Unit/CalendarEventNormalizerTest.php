<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Import\CalendarEventNormalizer;
use Drupal\aculta_calendar\Import\ParsedCalendarEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests sanitization, timezone handling, and cancellation normalization. */
#[Group('aculta_calendar')]
final class CalendarEventNormalizerTest extends TestCase {

  /**
   * Verifies removes script markup decodes entities and truncates.
   */
  public function testRemovesScriptMarkupDecodesEntitiesAndTruncates(): void {
    $event = $this->event(
      '<b>Festa &amp; feira</b><script>alert(1)</script>',
      '&lt;script&gt;alert(2)&lt;/script&gt;texto &amp; mais',
      '<img src=x onerror=alert(3)>Praça',
    );

    $normalized = (new CalendarEventNormalizer())->normalize($event);

    self::assertSame('Festa & feira', $normalized->title);
    self::assertSame('texto & mais', $normalized->description);
    self::assertSame('Praça', $normalized->location);

    $long = $this->event(str_repeat('á', 300), str_repeat('b', 8500), str_repeat('c', 300));
    $bounded = (new CalendarEventNormalizer())->normalize($long);
    self::assertSame(255, mb_strlen($bounded->title, 'UTF-8'));
    self::assertSame(8000, mb_strlen($bounded->description, 'UTF-8'));
    self::assertSame(255, mb_strlen($bounded->location, 'UTF-8'));
  }

  /**
   * Verifies timezone less wall time uses sao paulo and records assumption.
   */
  public function testTimezoneLessWallTimeUsesSaoPauloAndRecordsAssumption(): void {
    $event = $this->event(timezone: NULL);
    $normalized = (new CalendarEventNormalizer())->normalize($event);

    self::assertSame('America/Sao_Paulo', $normalized->timezone);
    self::assertTrue($normalized->timezoneAssumed);
    self::assertSame('2026-10-28 14:00:00 -03:00', $normalized->start->format('Y-m-d H:i:s P'));
    self::assertSame('2026-10-28 15:00:00 -03:00', $normalized->end->format('Y-m-d H:i:s P'));
  }

  /**
   * Verifies known timezone is preserved and not marked assumed.
   */
  public function testKnownTimezoneIsPreservedAndNotMarkedAssumed(): void {
    $event = $this->event(timezone: 'Europe/Lisbon');
    $normalized = (new CalendarEventNormalizer())->normalize($event);

    self::assertSame('Europe/Lisbon', $normalized->timezone);
    self::assertFalse($normalized->timezoneAssumed);
    self::assertSame('2026-10-28 14:00:00', $normalized->start->format('Y-m-d H:i:s'));
  }

  /**
   * Verifies cancelled event maps to cancelled import state without deletion.
   */
  public function testCancelledEventMapsToCancelledImportStateWithoutDeletion(): void {
    $normalized = (new CalendarEventNormalizer())->normalize($this->event(status: 'CANCELLED'));

    self::assertSame('CANCELLED', $normalized->status);
    self::assertSame('cancelado', $normalized->importState());
    self::assertSame('uid@example.invalid', $normalized->uid);
  }

  /**
   * Verifies rejects unknown timezone and unsupported status.
   */
  public function testRejectsUnknownTimezoneAndUnsupportedStatus(): void {
    $normalizer = new CalendarEventNormalizer();
    try {
      $normalizer->normalize($this->event(timezone: 'Mars/Olympus'));
      self::fail('Unknown timezone must be rejected.');
    }
    catch (\InvalidArgumentException $exception) {
      self::assertStringContainsString('Fuso horário ICS desconhecido', $exception->getMessage());
    }

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Estado ICS não suportado');
    $normalizer->normalize($this->event(status: 'MAYBE'));
  }

  /**
   * Describes the event value.
   */
  private function event(
    string $title = 'Evento de teste',
    string $description = 'Descrição de teste',
    string $location = 'Local de teste',
    ?string $timezone = 'America/Sao_Paulo',
    string $status = 'CONFIRMED',
  ): ParsedCalendarEvent {
    try {
      $parseTimezone = new \DateTimeZone($timezone ?? 'UTC');
    }
    catch (\Exception) {
      $parseTimezone = new \DateTimeZone('UTC');
    }
    return new ParsedCalendarEvent(
      'uid@example.invalid',
      new \DateTimeImmutable('2026-10-28 14:00:00', $parseTimezone),
      new \DateTimeImmutable('2026-10-28 15:00:00', $parseTimezone),
      FALSE,
      $timezone,
      $title,
      $description,
      $location,
      $status,
    );
  }

}
