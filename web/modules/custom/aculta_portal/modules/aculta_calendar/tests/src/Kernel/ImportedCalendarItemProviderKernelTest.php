<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Import\NormalizedCalendarEvent;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Published imported items alone are exposed to calendar consumers. */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class ImportedCalendarItemProviderKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'aculta_calendar'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_calendar_imported_item');
  }

  public function testOnlyPublishedItemIsReturnedAndVisibleInCalendar(): void {
    $source = CalendarImportSource::create([
      'id' => 'fonte_teste',
      'label' => 'Fonte de teste',
      'sourceUrl' => '',
      'status' => TRUE,
    ]);
    $source->save();
    $timezone = new \DateTimeZone('America/Sao_Paulo');
    $event = new NormalizedCalendarEvent(
      'uid-publico@example.invalid',
      new \DateTimeImmutable('2030-05-10 10:00', $timezone),
      new \DateTimeImmutable('2030-05-10 11:00', $timezone),
      FALSE,
      'America/Sao_Paulo',
      FALSE,
      'Evento público importado',
      'Descrição',
      'Local',
      'CONFIRMED',
    );
    $repository = $this->container->get('aculta_calendar.import_repository');
    $published = $repository->createFromEvent($source, $event);
    $published->setImportState('publicado')->save();
    $draft = $repository->createFromEvent($source, new NormalizedCalendarEvent(
      'uid-rascunho@example.invalid',
      $event->start,
      $event->end,
      FALSE,
      'America/Sao_Paulo',
      FALSE,
      'Evento rascunho',
      '',
      '',
      'CONFIRMED',
    ));

    $provider = $this->container->get('aculta_calendar.imported_item_provider');
    $query = new CalendarQuery(
      new \DateTimeImmutable('2030-05-01 00:00', $timezone),
      new \DateTimeImmutable('2030-05-31 23:59:59', $timezone),
      new \DateTimeImmutable('2030-05-01 00:00', $timezone),
    );
    $items = iterator_to_array($provider->items($query));
    self::assertCount(1, $items);
    self::assertSame('Evento público importado', $items[0]->titulo);
    self::assertSame('importado:' . $published->id(), $items[0]->id);
    self::assertSame('aculta_calendar_import', $items[0]->origem);
    self::assertFalse($draft->isPublished());

    $days = $this->container->get('aculta_calendar.agenda')->dias($query);
    self::assertSame(['Evento público importado'], array_column($days['2030-05-10'], 'titulo'));
  }

}
