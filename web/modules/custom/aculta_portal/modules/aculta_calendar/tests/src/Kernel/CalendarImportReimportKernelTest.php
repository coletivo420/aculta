<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Import\NormalizedCalendarEvent;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/** Tests import idempotency, editorial revisions, and absent source UIDs. */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class CalendarImportReimportKernelTest extends KernelTestBase {

  /** {@inheritdoc} */
  protected static $modules = ['system', 'user', 'aculta_calendar'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_calendar_imported_item');
  }

  public function testEqualReimportDoesNotCreateAnotherRevision(): void {
    $source = $this->source();
    $processor = $this->container->get('aculta_calendar.import_processor');
    $event = $this->event();
    self::assertSame(1, $processor->process($source, [$event])['created']);
    $item = $this->container->get('aculta_calendar.import_repository')
      ->findCurrent($source, $event->uid);
    $revisionId = $item->getRevisionId();

    $result = $processor->process($source, [$event]);
    $current = $this->container->get('aculta_calendar.import_repository')
      ->findCurrent($source, $event->uid);

    self::assertSame(1, $result['unchanged']);
    self::assertSame($revisionId, $current->getRevisionId());
    self::assertSame('presente', $current->get('source_presence')->value);
  }

  public function testChangedPublishedItemBecomesReviewRevisionWhilePublicVersionRemains(): void {
    $source = $this->source();
    $processor = $this->container->get('aculta_calendar.import_processor');
    $processor->process($source, [$this->event('Título publicado')]);
    $repository = $this->container->get('aculta_calendar.import_repository');
    $published = $repository->findCurrent($source, 'uid@example.invalid');
    $published->setImportState('publicado')->save();
    $entityId = $published->id();

    $result = $processor->process($source, [$this->event('Título atualizado')]);
    $current = $this->container->get('entity_type.manager')
      ->getStorage('aculta_calendar_imported_item')
      ->load($entityId);
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_calendar_imported_item');
    $latest = $storage->loadRevision($storage->getLatestRevisionId($entityId));

    self::assertSame(1, $result['updated']);
    self::assertSame('Título publicado', $current->get('title')->value);
    self::assertTrue($current->isPublished());
    self::assertSame('Título atualizado', $latest->get('title')->value);
    self::assertSame('rascunho', $latest->get('import_state')->value);
    self::assertFalse($latest->isPublished());
    self::assertFalse($latest->isDefaultRevision());
  }

  public function testMissingUidIsMarkedAbsentWithoutDeletingOrUnpublishing(): void {
    $source = $this->source();
    $processor = $this->container->get('aculta_calendar.import_processor');
    $processor->process($source, [$this->event()]);
    $repository = $this->container->get('aculta_calendar.import_repository');
    $published = $repository->findCurrent($source, 'uid@example.invalid');
    $published->setImportState('publicado')->save();
    $entityId = $published->id();

    $result = $processor->process($source, []);
    $current = $this->container->get('entity_type.manager')
      ->getStorage('aculta_calendar_imported_item')
      ->load($entityId);

    self::assertSame(1, $result['absent']);
    self::assertSame('ausente', $current->get('source_presence')->value);
    self::assertTrue($current->isPublished());
    self::assertCount(1, $this->container->get('entity_type.manager')
      ->getStorage('aculta_calendar_imported_item')->loadMultiple());

    $again = $processor->process($source, [$this->event()]);
    $restored = $this->container->get('entity_type.manager')
      ->getStorage('aculta_calendar_imported_item')->load($entityId);
    self::assertSame(1, $again['unchanged']);
    self::assertSame('presente', $restored->get('source_presence')->value);
    self::assertTrue($restored->isPublished());
  }

  private function source(): CalendarImportSource {
    $source = CalendarImportSource::create([
      'id' => 'fonte_teste',
      'label' => 'Fonte de teste',
      'sourceUrl' => 'https://example.invalid/calendar.ics',
      'status' => TRUE,
    ]);
    $source->save();
    return $source;
  }

  private function event(string $title = 'Evento de teste'): NormalizedCalendarEvent {
    $timezone = new \DateTimeZone('America/Sao_Paulo');
    return new NormalizedCalendarEvent(
      'uid@example.invalid',
      new \DateTimeImmutable('2026-10-28 14:00:00', $timezone),
      new \DateTimeImmutable('2026-10-28 15:00:00', $timezone),
      FALSE,
      'America/Sao_Paulo',
      FALSE,
      $title,
      'Descrição sintética',
      'Local sintético',
      'CONFIRMED',
    );
  }

}
