<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the Portuguese location vocabulary migration and idempotence.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class LocationTypeMigrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar',
    'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('aculta_event_location');
    $this->installConfig(['system', 'taxonomy']);
    require_once dirname(__DIR__, 3) . '/aculta_events.install';
  }

  /**
   * Update 10003 creates 17 Portuguese terms once and retains their list.
   */
  public function testMigrationCreatesSeventeenPortugueseTypesIdempotently(): void {
    $first = aculta_events_update_10003();
    $second = aculta_events_update_10003();
    $this->assertStringContainsString('vocabulário aculta_location_type', $first);
    $this->assertSame('Tipos de local e campos já existiam.', $second);

    $vocabulary = Vocabulary::load('aculta_location_type');
    $this->assertNotNull($vocabulary);
    $terms = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->loadTree('aculta_location_type');
    $names = array_map(static fn (object $term): string => (string) $term->name, $terms);
    $this->assertCount(17, $names);
    $this->assertContains('Gastronomia', $names);
    $this->assertNotContains('Gastronmy', $names);
    $this->assertContains('Atração turística', $names);
    $this->assertContains('Teatro', $names);
  }

}
