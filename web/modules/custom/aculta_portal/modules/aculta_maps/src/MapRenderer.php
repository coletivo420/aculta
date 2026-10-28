<?php

declare(strict_types=1);

namespace Drupal\aculta_maps;

use Drupal\Component\Utility\Html;
use Drupal\aculta_maps\Geocoding\GeocodingService;

/**
 * Produces the shared map render array.
 *
 * @todo MP3 replaces the legacy Google renderer with the OSM default.
 */
final class MapRenderer {

  public function __construct(private readonly GeocodingService $geocoding) {}

  /**
   * Builds a map for the supplied markers.
   *
   * @param array $options
   *   Map options. `markers` contains name, latitude, longitude, and address.
   *
   * @return array
   *   A render array or an empty array when no provider is available.
   */
  public function build(array $options): array {
    $key = $this->geocoding->key();
    if ($key === NULL) {
      return [];
    }

    return [
      '#markup' => '<div id="aculta-map" class="aculta-map" role="region" aria-label="'
      . Html::escape((string) ($options['label'] ?? 'Mapa')) . '"></div>',
      '#attached' => [
        'library' => ['aculta_maps/map'],
        'drupalSettings' => [
          'acultaMaps' => [
            'mapsKey' => $key,
            'markers' => $options['markers'] ?? [],
          ],
        ],
      ],
    ];
  }

}
