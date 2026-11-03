<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers D-60 RSVP settings as inert, persisted Event policy.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventRsvpSettingsKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->container->get('module_handler')->loadInclude('aculta_events', 'install');
  }

  /**
   * RSVP defaults closed while D-60 policy values can be persisted.
   */
  public function testPolicyDefaultsAndEventDateCapacity(): void {
    $updateManager = $this->container->get('entity.definition_update_manager');
    foreach ([
      'aculta_event' => [
        'field_rsvp_ativo', 'field_rsvp_escopo_capacidade', 'field_rsvp_capacidade',
        'field_rsvp_limite_pessoa', 'field_rsvp_duplicidade', 'field_rsvp_reserva_minutos',
        'field_rsvp_lista_espera', 'field_rsvp_cancelamento', 'field_rsvp_serie',
      ],
      'aculta_event_date' => ['field_rsvp_capacidade'],
    ] as $entityTypeId => $fieldNames) {
      foreach ($fieldNames as $fieldName) {
        $definition = $updateManager->getFieldStorageDefinition($fieldName, $entityTypeId);
        if ($definition !== NULL) {
          $updateManager->uninstallFieldStorageDefinition($definition);
        }
      }
    }
    self::assertStringContainsString('instalados:', aculta_events_update_10015());
    self::assertStringContainsString('já estavam instalados', aculta_events_update_10015());

    $event = Event::create(['title' => 'Evento sem RSVP', 'field_status' => 'rascunho']);
    $event->save();
    self::assertFalse((bool) $event->get('field_rsvp_ativo')->value);
    self::assertSame('evento', $event->get('field_rsvp_escopo_capacidade')->value);
    self::assertSame(1, (int) $event->get('field_rsvp_limite_pessoa')->value);
    self::assertSame('impedir', $event->get('field_rsvp_duplicidade')->value);
    self::assertFalse((bool) $event->get('field_rsvp_lista_espera')->value);
    self::assertSame('ocorrencia', $event->get('field_rsvp_serie')->value);
    self::assertFalse($event->getFieldDefinition('field_rsvp_ativo')->isDisplayConfigurable('form'));

    $date = EventDate::create([
      'title' => 'Sessão limitada',
      'field_evento' => $event->id(),
      'field_quando' => [
        'value' => '2026-12-05T18:00:00',
        'end_value' => '2026-12-05T20:00:00',
      ],
      'field_rsvp_capacidade' => 40,
    ]);
    $date->save();

    $stored = Event::load($event->id());
    $storedDate = EventDate::load($date->id());
    self::assertFalse((bool) $stored->get('field_rsvp_ativo')->value);
    self::assertSame(40, (int) $storedDate->get('field_rsvp_capacidade')->value);
  }

  /**
   * Invalid capacity and reservation durations fail field validation.
   */
  public function testRejectsNonPositiveCapacityAndReservationDuration(): void {
    $event = Event::create([
      'title' => 'Evento sintético',
      'field_status' => 'rascunho',
      'field_rsvp_capacidade' => 0,
      'field_rsvp_reserva_minutos' => 0,
    ]);
    self::assertGreaterThanOrEqual(2, $event->validate()->count());
  }

}
