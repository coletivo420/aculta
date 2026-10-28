<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\MapController;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the map page without a configured provider.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class MapControllerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'address', 'geofield', 'key', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar',
    'aculta_maps', 'aculta_events',
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
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * OSM renders without credentials and keeps the list and privacy notice.
   */
  public function testOsmMapRendersWithoutCredentials(): void {
    EventLocation::create([
      'title' => 'Casa de cultura',
      'field_precisao' => 'exato',
      'field_coordenadas' => [
        'lat' => -16.68,
        'lon' => -49.26,
        'value' => 'POINT (-49.26 -16.68)',
        'geo_type' => 'Point',
      ],
      'field_endereco' => [
        'country_code' => 'BR',
        'locality' => 'Goiânia',
        'administrative_area' => 'GO',
        'address_line1' => 'Rua 10, 123',
        'postal_code' => '74000000',
      ],
    ])->save();

    $pagina = MapController::create($this->container)->page();
    $this->assertArrayHasKey('mapa', $pagina);
    $this->assertContains('aculta_maps/map', $pagina['mapa']['#attached']['library']);
    $this->assertStringContainsString('Casa de cultura', (string) \Drupal::service('renderer')->renderInIsolation($pagina['lista']));
    $aviso = (string) \Drupal::service('renderer')->renderInIsolation($pagina['aviso']);
    $this->assertStringContainsString('OpenStreetMap', $aviso);
    $this->assertStringContainsString('/sobre/politica-de-privacidade', $aviso);
  }

}
