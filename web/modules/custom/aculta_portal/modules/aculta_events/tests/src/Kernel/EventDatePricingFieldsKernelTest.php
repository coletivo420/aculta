<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\KernelTests\KernelTestBase;
use Drupal\link\LinkItemInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies E1 pricing fields remain informational and outside payment flows.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDatePricingFieldsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'options', 'text', 'link',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar', 'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'filter', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
  }

  /**
   * Verifies price and ticketing fields persist without starting payment.
   */
  public function testInformationalPricingFieldsPersistWithoutPaymentBehavior(): void {
    $event = Event::create(['title' => 'Oficina', 'field_status' => 'publicado']);
    $event->save();
    $date = EventDate::create([
      'title' => 'Oficina',
      'field_evento' => $event->id(),
      'field_quando' => [
        'value' => '2030-05-10T13:00:00',
        'end_value' => '2030-05-10T15:00:00',
      ],
      'field_gratuito' => FALSE,
      'field_pricing' => 'R$ 20,00',
      'field_pricing_info' => ['value' => 'Meia entrada: R$ 10,00.', 'format' => 'plain_text'],
      'field_ticketing' => ['uri' => 'https://tickets.example.test/oficina', 'title' => 'Reservar'],
    ]);
    $date->save();

    $loaded = EventDate::load($date->id());
    self::assertSame('R$ 20,00', $loaded->get('field_pricing')->value);
    self::assertSame('Meia entrada: R$ 10,00.', $loaded->get('field_pricing_info')->value);
    self::assertSame('https://tickets.example.test/oficina', $loaded->get('field_ticketing')->uri);
    self::assertSame(
      LinkItemInterface::LINK_EXTERNAL,
      $loaded->getFieldDefinition('field_ticketing')->getSetting('link_type'),
    );
    self::assertFalse((bool) $loaded->get('field_gratuito')->value);
  }

  /**
   * Verifies the update hook safely skips fields already installed.
   */
  public function testUpdateHookIsIdempotentWhenFieldsExist(): void {
    include_once DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_events/aculta_events.install';
    self::assertSame(
      'Campos informativos de preço e compra externa já existiam.',
      aculta_events_update_10004(),
    );
  }

}
