<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Import\CalendarEventNormalizer;
use Drupal\aculta_calendar\Import\IcsCalendarFileReader;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests calendar import source and item storage without performing network I/O.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class CalendarImportEntityKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  /**
   * Modules enabled by this Kernel test.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_calendar'];

  /**
   * Configures the test environment and installs required schemas.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_calendar_imported_item');
  }

  /**
   * Verifies source and imported item are created and uid is idempotent.
   */
  public function testSourceAndImportedItemAreCreatedAndUidIsIdempotent(): void {
    $source = CalendarImportSource::create([
      'id' => 'fonte_teste',
      'label' => 'Fonte de teste',
      'sourceUrl' => 'https://example.org/calendario.ics',
      'status' => TRUE,
    ]);
    $source->save();

    $repository = $this->container->get('aculta_calendar.import_repository');
    $first = $repository->loadOrCreate($source, 'uid-externo-1@example.org');
    $duplicate = $repository->loadOrCreate($source, 'uid-externo-1@example.org');

    $this->assertSame($source->id(), $first->get('source')->target_id);
    $this->assertSame('uid-externo-1@example.org', $first->get('external_uid')->value);
    $this->assertSame('rascunho', $first->get('import_state')->value);
    $this->assertSame($first->id(), $duplicate->id());
    $items = $this->container->get('entity_type.manager')
      ->getStorage('aculta_calendar_imported_item')
      ->loadMultiple();
    $this->assertCount(1, $items);
  }

  /**
   * Verifies local reader is registered as service.
   */
  public function testLocalReaderIsRegisteredAsService(): void {
    $this->assertInstanceOf(
      IcsCalendarFileReader::class,
      $this->container->get('aculta_calendar.import_reader'),
    );
    $this->assertInstanceOf(
      CalendarEventNormalizer::class,
      $this->container->get('aculta_calendar.import_normalizer'),
    );
  }

}
