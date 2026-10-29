<?php

declare(strict_types=1);

namespace Drupal\aculta_maps;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Secrets\SecretsManager;

/**
 * Produces a provider-neutral map render array.
 */
final class MapRenderer {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ?SecretsManager $secrets,
    private readonly AccountProxyInterface $currentUser,
  ) {}

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
    $settings = $this->configFactory->get('aculta_maps.settings');
    $engine = $settings->get('engine') ?? 'openstreetmap';
    $googleKey = $engine === 'google' ? $this->secrets?->value('GOOGLE_MAPS_API_KEY') : NULL;
    $provider = $googleKey ? 'google' : 'openstreetmap';
    $mapId = Html::getId((string) ($options['id'] ?? 'aculta-map'));
    $markers = [];
    foreach ($options['markers'] ?? [] as $marker) {
      if (!is_array($marker) || !is_numeric($marker['lat'] ?? NULL) || !is_numeric($marker['lng'] ?? NULL)) {
        continue;
      }
      $latitude = (float) $marker['lat'];
      $longitude = (float) $marker['lng'];
      if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        continue;
      }
      if (($marker['precision'] ?? 'exact') === 'approximate') {
        $latitude = round($latitude, 2);
        $longitude = round($longitude, 2);
      }
      $markers[] = [
        'lat' => $latitude,
        'lng' => $longitude,
        'label' => (string) ($marker['label'] ?? ''),
        'description' => (string) ($marker['description'] ?? ''),
        'url' => (string) ($marker['url'] ?? ''),
        'routeUrl' => (string) ($marker['routeUrl'] ?? ''),
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $mapId,
        'class' => ['aculta-map'],
        'role' => 'region',
        'aria-label' => (string) ($options['label'] ?? 'Mapa'),
        'data-aculta-map' => 'true',
      ],
      '#cache' => ['tags' => ['config:aculta_maps.settings'], 'contexts' => ['user.permissions']],
      '#attached' => [
        'library' => ['aculta_maps/map'],
        'drupalSettings' => [
          'acultaMaps' => [
            $mapId => [
              'center' => [
                'lat' => (float) ($settings->get('center_lat') ?? -14.235),
                'lng' => (float) ($settings->get('center_lng') ?? -51.9253),
              ],
              'zoom' => (int) ($settings->get('zoom_initial') ?? 4),
              'zoomLocation' => (int) ($settings->get('zoom_location') ?? 15),
              'provider' => $provider,
              'showAdminNotice' => $this->currentUser->hasPermission('administer aculta maps'),
              'markers' => $markers,
            ],
          ],
        ],
      ],
    ];
    if ($googleKey) {
      $build['#attached']['drupalSettings']['acultaMaps'][$mapId]['googleApiKey'] = $googleKey;
    }
    if ($engine === 'google' && !$googleKey) {
      $build['provider_notice'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Google Maps não está configurado; usando OpenStreetMap.',
        '#access' => $this->currentUser->hasPermission('administer aculta maps'),
        '#attributes' => ['class' => ['messages', 'messages--warning']],
      ];
    }
    if ($provider === 'google' && $markers !== []) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-map-wrapper']],
        'map' => $build,
        'geocoding_attribution' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['aculta-map__geocoding-attribution']],
          'text' => [
            '#plain_text' => 'Dados de localização © OpenStreetMap contributors, disponíveis sob ODbL. ',
          ],
          'license' => [
            '#type' => 'link',
            '#title' => 'Licença e direitos autorais',
            '#url' => Url::fromUri('https://www.openstreetmap.org/copyright'),
            '#options' => [
              'attributes' => [
                'rel' => 'noopener noreferrer',
                'target' => '_blank',
              ],
            ],
          ],
        ],
      ];
    }
    return $build;
  }

}
