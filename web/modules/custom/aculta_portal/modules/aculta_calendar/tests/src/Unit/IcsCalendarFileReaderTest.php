<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Import\CalendarFileLimitException;
use Drupal\aculta_calendar\Import\IcsCalendarFileReader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests bounded local iCalendar reading without network access or dependencies.
 */
#[Group('aculta_calendar')]
final class IcsCalendarFileReaderTest extends TestCase {

  public function testReadsHandwrittenFixture(): void {
    $reader = new IcsCalendarFileReader();
    $events = $reader->read(__DIR__ . '/../../fixtures/importacao-basica.ics');

    self::assertCount(1, $events);
    self::assertSame('importacao-basica@example.invalid', $events[0]->uid);
    self::assertSame('Oficina de teste', $events[0]->title);
    self::assertSame('Exemplo, sem dados pessoais', $events[0]->description);
    self::assertSame('America/Sao_Paulo', $events[0]->timezone);
    self::assertSame('2026-10-28 14:00:00', $events[0]->start->format('Y-m-d H:i:s'));
    self::assertFalse($events[0]->allDay);
    self::assertSame('CONFIRMED', $events[0]->status);
  }

  public function testRejectsFileLargerThanTwoMiBBeforeParsing(): void {
    $path = tempnam(sys_get_temp_dir(), 'aculta-ics-size-');
    self::assertNotFalse($path);
    try {
      file_put_contents($path, str_repeat('x', IcsCalendarFileReader::MAX_FILE_BYTES + 1));
      $this->expectException(CalendarFileLimitException::class);
      $this->expectExceptionMessage('Arquivo ICS excede o limite de 2 MB.');
      (new IcsCalendarFileReader())->read($path);
    }
    finally {
      unlink($path);
    }
  }

  public function testRejectsMoreThanTwoThousandEvents(): void {
    $path = tempnam(sys_get_temp_dir(), 'aculta-ics-count-');
    self::assertNotFalse($path);
    $event = "BEGIN:VEVENT\nUID:overflow@example.invalid\nDTSTART:20261028T140000Z\nEND:VEVENT\n";
    $contents = "BEGIN:VCALENDAR\n" . str_repeat($event, IcsCalendarFileReader::MAX_EVENTS + 1) . "END:VCALENDAR\n";
    try {
      file_put_contents($path, $contents);
      $this->expectException(CalendarFileLimitException::class);
      $this->expectExceptionMessage('O arquivo ICS excede o limite de 2.000 eventos.');
      (new IcsCalendarFileReader())->read($path);
    }
    finally {
      unlink($path);
    }
  }

  public function testRejectsUrlInsteadOfFetchingIt(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A importação aceita apenas um arquivo ICS local legível.');
    (new IcsCalendarFileReader())->read('https://example.invalid/calendar.ics');
  }

}
