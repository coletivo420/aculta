<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Migração event_type → aculta_event_type: cria os termos, remove o antigo e é idempotente.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventTypeMigrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'key', 'address', 'geofield', 'field', 'link', 'text', 'filter', 'taxonomy', 'options', 'datetime', 'datetime_range', 'aculta_calendar', 'aculta_events'];

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
   * Os termos antigos saem, os novos entram com os 10 tipos, e uma segunda rodada não duplica.
   */
  public function testMigraEIdempotente(): void {
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
