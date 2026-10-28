<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_calendar\Agenda\CalendarOccurrences;
use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Controller\AdministrativeEventExportController;
use Drupal\Component\Datetime\TimeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the safe, provider-backed administrative event export.
 */
#[Group('aculta_events')]
final class AdministrativeEventExportControllerTest extends TestCase {

  /**
   * The CSV exports only event provider items and its approved fields.
   */
  public function testCsvUsesMinimalFieldsAndNeutralizesSpreadsheetFormula(): void {
    $event = new CalendarItem(
      id: 'eventdate:1',
      titulo: '=Festival',
      inicio: new \DateTimeImmutable('2026-01-02 10:00:00', new \DateTimeZone('America/Sao_Paulo')),
      fim: new \DateTimeImmutable('2026-01-02 11:00:00', new \DateTimeZone('America/Sao_Paulo')),
      local: 'Centro Cultural',
      link: '/eventos/festival',
      origem: 'aculta_events',
    );
    $otherProviderItem = new CalendarItem(
      id: 'imported:2',
      titulo: 'Evento importado',
      inicio: new \DateTimeImmutable('2026-01-03 10:00:00', new \DateTimeZone('America/Sao_Paulo')),
      fim: new \DateTimeImmutable('2026-01-03 11:00:00', new \DateTimeZone('America/Sao_Paulo')),
      origem: 'aculta_calendar_import',
    );
    $provider = new class([$event, $otherProviderItem]) implements CalendarItemProviderInterface {

      /**
       * Constructs the fake provider.
       */
      public function __construct(private readonly array $items) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return 'test';
      }

      /**
       * {@inheritdoc}
       */
      public function items(CalendarQuery $query): iterable {
        return $this->items;
      }

      /**
       * {@inheritdoc}
       */
      public function filtros(): array {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function cacheTags(): array {
        return [];
      }

      /** {@inheritdoc} */
      public function cacheContexts(): array {
        return [];
      }

      /** {@inheritdoc} */
      public function cacheMaxAge(): int {
        return 900;
      }

    };
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(strtotime('2026-01-01 12:00:00 UTC'));
    $requests = new RequestStack();
    $requests->push(Request::create('/painel-administrativo/eventos/exportar?de=01%2F01%2F2026&ate=31%2F01%2F2026'));
    $controller = new AdministrativeEventExportController(
      new CalendarItemSource([$provider]),
      new CalendarOccurrences(),
      $time,
      $requests,
    );

    $response = $controller->export();
    $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    $content = substr($response->getContent(), 3);
    $lines = array_values(array_filter(explode("\r\n", $content)));
    $this->assertSame(['Evento', 'Início', 'Fim', 'Dia inteiro', 'Situação', 'Local', 'Link'], str_getcsv($lines[0], ';', '"', '\\'));
    $row = str_getcsv($lines[1], ';', '"', '\\');
    $this->assertCount(7, $row);
    $this->assertSame("'=Festival", $row[0]);
    $this->assertSame('Centro Cultural', $row[5]);
    $this->assertSame('/eventos/festival', $row[6]);
    $this->assertStringNotContainsString('Evento importado', $content);
    foreach (['email', 'telefone', 'endereço', 'field_contact_data'] as $sensitive) {
      $this->assertStringNotContainsString($sensitive, mb_strtolower($content));
    }
  }

}
