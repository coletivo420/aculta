<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Import\CalendarHostResolverInterface;
use Drupal\aculta_calendar\Import\CalendarIcsRemoteFetcher;
use Drupal\aculta_calendar\Import\CalendarRemoteImportService;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Remote fetch feeds the same reviewable import pipeline without network I/O. */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class CalendarRemoteImportKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'aculta_calendar'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_calendar_imported_item');
  }

  public function testRemoteImportServiceIsRegistered(): void {
    self::assertInstanceOf(CalendarRemoteImportService::class, $this->container->get('aculta_calendar.remote_import'));
  }

  public function testInactiveSourceIsRejectedBeforeAnyNetworkAccess(): void {
    $source = CalendarImportSource::create([
      'id' => 'fonte_inativa',
      'label' => 'Fonte desativada',
      'sourceUrl' => 'https://calendar.example.invalid/events.ics',
      'allowedHosts' => ['calendar.example.invalid'],
      'status' => FALSE,
    ]);
    $source->save();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('fonte iCalendar está desativada');
    $this->container->get('aculta_calendar.remote_import')->import($source);
  }

  public function testRemoteImportCreatesDraftItemsThroughTheExistingProcessor(): void {
    $source = CalendarImportSource::create([
      'id' => 'fonte_remota_teste',
      'label' => 'Fonte remota sintética',
      'sourceUrl' => 'https://calendar.example.invalid/events.ics',
      'allowedHosts' => ['calendar.example.invalid'],
      'status' => TRUE,
    ]);
    $source->save();
    $calendar = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:remote@example.invalid\r\nDTSTART;TZID=America/Sao_Paulo:20300510T100000\r\nDTEND;TZID=America/Sao_Paulo:20300510T110000\r\nSUMMARY:Evento remoto sintético\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    $handler = HandlerStack::create(new MockHandler([new Response(200, ['Content-Length' => (string) strlen($calendar)], $calendar)]));
    $resolver = new class implements CalendarHostResolverInterface {
      public function resolve(string $hostname): array {
        return ['8.8.8.8'];
      }
    };
    $fetcher = new CalendarIcsRemoteFetcher(new Client(['handler' => $handler]), $resolver);
    $service = new CalendarRemoteImportService(
      $fetcher,
      $this->container->get('aculta_calendar.import_reader'),
      $this->container->get('aculta_calendar.import_normalizer'),
      $this->container->get('aculta_calendar.import_processor'),
    );

    self::assertSame(['created' => 1, 'updated' => 0, 'unchanged' => 0, 'absent' => 0], $service->import($source));
    $items = $this->container->get('entity_type.manager')
      ->getStorage('aculta_calendar_imported_item')->loadMultiple();
    self::assertCount(1, $items);
    $item = reset($items);
    self::assertSame('Evento remoto sintético', $item->get('title')->value);
    self::assertFalse($item->isPublished());
    self::assertSame('rascunho', $item->get('import_state')->value);
    self::assertSame('success', $source->lastResult());
    self::assertGreaterThan(0, $source->lastSync());
  }

}
