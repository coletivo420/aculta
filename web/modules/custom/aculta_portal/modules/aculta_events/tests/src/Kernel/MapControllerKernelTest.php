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
      'field_precisao' => 'aproximado',
      'field_coordenadas' => [
        'lat' => -16.68123,
        'lon' => -49.26011,
        'value' => 'POINT (-49.26011 -16.68123)',
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
    $marcadores = $pagina['mapa']['#attached']['drupalSettings']['acultaMaps']['aculta-map']['markers'];
    $this->assertCount(1, $marcadores);
    $this->assertSame(-16.68, $marcadores[0]['lat']);
    $this->assertSame(-49.26, $marcadores[0]['lng']);
    $this->assertStringContainsString('Casa de cultura', (string) \Drupal::service('renderer')->renderInIsolation($pagina['lista']));
    $aviso = (string) \Drupal::service('renderer')->renderInIsolation($pagina['aviso']);
    // D-52: only the privacy-policy link remains; the provider attribution is
    // rendered by the map component, not by the inline notice.
    $this->assertStringContainsString('/sobre/politica-de-privacidade', $aviso);
    $this->assertStringNotContainsString('envia seu endereço IP', $aviso);
    $this->assertStringNotContainsString('OpenStreetMap', $aviso);
  }

  /**
   * Valid zero latitude and longitude are retained as a map marker.
   */
  public function testMapKeepsZeroCoordinates(): void {
    EventLocation::create([
      'title' => 'Local no Equador e Greenwich',
      'field_precisao' => 'exato',
      'field_coordenadas' => [
        'lat' => 0.0,
        'lon' => 0.0,
        'value' => 'POINT (0 0)',
        'geo_type' => 'Point',
      ],
      'field_endereco' => [
        'country_code' => 'BR',
        'locality' => 'Localidade de teste',
      ],
    ])->save();

    $page = MapController::create($this->container)->page();
    $markers = $page['mapa']['#attached']['drupalSettings']['acultaMaps']['aculta-map']['markers'];

    $this->assertCount(1, $markers);
    $this->assertSame(0.0, $markers[0]['lat']);
    $this->assertSame(0.0, $markers[0]['lng']);
  }

  /**
   * A Local saved without coordinates is geocoded again on a later save.
   */
  public function testLocationWithoutCoordinatesIsRetriedOnUpdate(): void {
    $stub = new class() {

      /**
       * Result returned by the next call.
       */
      public ?array $result = NULL;

      /**
       * Number of calls.
       */
      public int $calls = 0;

      /**
       * Fake geocoder without network access.
       */
      public function geocode(string $address): ?array {
        $this->calls++;
        return $this->result;
      }

    };
    $this->container->set('aculta_maps.geocoding', $stub);
    $local = EventLocation::create([
      'title' => 'Local sem coordenadas',
      'field_precisao' => 'exato',
      'field_endereco' => [
        'country_code' => 'BR',
        'locality' => 'Localidade de teste',
        'address_line1' => 'Rua de teste, 1',
      ],
    ]);
    $local->save();
    $this->assertTrue($local->get('field_coordenadas')->isEmpty());
    $page = MapController::create($this->container)->page();
    $this->assertSame([], $page['mapa']['#attached']['drupalSettings']['acultaMaps']['aculta-map']['markers']);
    $this->assertStringContainsString('Local sem coordenadas', (string) \Drupal::service('renderer')->renderInIsolation($page['lista']));

    $stub->result = ['lat' => 0.0, 'lng' => 0.0];
    $local->save();
    $this->assertSame(2, $stub->calls);
    $this->assertSame(0.0, (float) $local->get('field_coordenadas')->lat);

    // Existing coordinates are kept without a new geocoding request.
    $local = EventLocation::load($local->id());
    $local->save();
    $this->assertSame(2, $stub->calls);
  }

  /**
   * Map output bubbles cache contexts from hidden owner-only locations.
   */
  public function testMapBubblesOwnerAccessCacheContext(): void {
    $userStorage = $this->container->get('entity_type.manager')->getStorage('user');
    $owner = $userStorage->create([
      'name' => 'map-owner',
      'mail' => 'map-owner@example.test',
      'status' => 1,
    ]);
    $owner->save();
    EventLocation::create([
      'title' => 'Local restrito do mapa',
      'field_status' => 'privado',
      'field_organizador' => $owner->id(),
      'field_endereco' => [
        'country_code' => 'BR',
        'address_line1' => 'Rua privada, 123',
      ],
    ])->save();

    $page = MapController::create($this->container)->page();
    $list = (string) \Drupal::service('renderer')->renderInIsolation($page['lista']);

    $this->assertContains('user', $page['#cache']['contexts']);
    $this->assertStringNotContainsString('Local restrito do mapa', $list);
    $this->assertStringNotContainsString('Rua privada, 123', $list);
  }

}
