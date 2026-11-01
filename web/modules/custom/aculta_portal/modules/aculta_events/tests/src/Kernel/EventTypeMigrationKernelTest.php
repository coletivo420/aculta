<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Migra o vocabulário event_type e remove o legado de forma idempotente.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventTypeMigrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'field', 'link', 'text',
    'filter', 'taxonomy', 'options', 'datetime', 'datetime_range',
    'aculta_calendar', 'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'taxonomy']);
    require_once dirname(__DIR__, 3) . '/aculta_events.install';
  }

  /**
   * A migração instala dez termos e a segunda execução não duplica registros.
   */
  public function testMigraIdempotentemente(): void {
    Vocabulary::create(['vid' => 'event_type', 'name' => 'Tipo de atividade'])->save();
    Term::create(['vid' => 'event_type', 'name' => 'Oficina'])->save();

    aculta_events_update_10001();
    aculta_events_update_10001();

    $novos = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'aculta_event_type']);
    $this->assertCount(10, $novos);
    $this->assertNull(Vocabulary::load('event_type'));
    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'event_type']));
  }

}
