<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_maps\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\aculta_maps\MapRenderer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the provider-neutral map render contract.
 */
#[Group('aculta_maps')]
final class MapRendererTest extends UnitTestCase {

  /**
   * Renders valid markers with configured defaults and rounds approximate ones.
   */
  public function testBuildSanitizesMarkersAndRoundsApproximateCoordinates(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['center_lat', -14.235],
      ['center_lng', -51.9253],
      ['zoom_initial', 4],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_maps.settings')->willReturn($settings);
    $renderer = new MapRenderer($configFactory);

    $build = $renderer->build([
      'id' => 'event map',
      'label' => 'Eventos',
      'markers' => [
        ['lat' => -16.68123, 'lng' => -49.26011, 'label' => 'Local aproximado', 'precision' => 'approximate'],
        ['lat' => 91, 'lng' => 0, 'label' => 'Coordenada inválida'],
      ],
    ]);

    $this->assertSame('event-map', $build['#attributes']['id']);
    $this->assertSame('aculta_maps/map', $build['#attached']['library'][0]);
    $this->assertSame(-16.68, $build['#attached']['drupalSettings']['acultaMaps']['event-map']['markers'][0]['lat']);
    $this->assertSame(-49.26, $build['#attached']['drupalSettings']['acultaMaps']['event-map']['markers'][0]['lng']);
    $this->assertCount(1, $build['#attached']['drupalSettings']['acultaMaps']['event-map']['markers']);
  }

}
