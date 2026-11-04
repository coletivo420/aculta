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
    $popupTemplates = [];
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
      $label = trim((string) ($marker['label'] ?? '')) ?: 'Local';
      $popupId = Html::getId($mapId . '-popup-' . count($markers));
      $markerData = [
        'lat' => $latitude,
        'lng' => $longitude,
        'label' => $label,
        'description' => (string) ($marker['description'] ?? ''),
        'url' => $this->safePopupUrl($marker['url'] ?? NULL),
        'routeUrl' => $this->safePopupUrl($marker['routeUrl'] ?? NULL),
        'popupId' => $popupId,
      ];
      $markers[] = $markerData;
      $popupTemplates[$popupId] = [
        '#theme' => 'aculta_maps_popup_template',
        '#popup_id' => $popupId,
        '#popup' => [
          '#theme' => 'aculta_maps_popup_content',
          '#title' => $markerData['label'],
          '#url' => $markerData['url'],
          '#category' => (string) ($marker['category'] ?? ''),
          '#address' => $markerData['description'],
          '#route_url' => $markerData['routeUrl'],
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $mapId,
        'data-portal-ui' => 'map-widget',
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
    if ($popupTemplates !== []) {
      $build['popup_templates'] = $popupTemplates;
    }
    if ($googleKey) {
      $build['#attached']['drupalSettings']['acultaMaps'][$mapId]['googleApiKey'] = $googleKey;
    }
    if ($engine === 'google' && !$googleKey) {
      $build['provider_notice'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Google Maps não está configurado; usando OpenStreetMap.',
        '#access' => $this->currentUser->hasPermission('administer aculta maps'),
        '#attributes' => ['data-portal-ui' => 'notice-warning'],
      ];
    }
    if ($provider === 'google' && $markers !== []) {
      return [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'map-wrapper'],
        'map' => $build,
        'geocoding_attribution' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'map-geocoding-attribution'],
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

  /**
   * Keeps only same-site relative URLs and HTTPS links in popup render data.
   */
  private function safePopupUrl(mixed $value): string {
    if (!is_string($value) || $value === '' || preg_match('/[\x00-\x20]/', $value) || str_starts_with($value, '//')) {
      return '';
    }
    $scheme = parse_url($value, PHP_URL_SCHEME);
    if ($scheme === NULL) {
      return parse_url($value) !== FALSE ? $value : '';
    }
    return is_string($scheme) && strtolower($scheme) === 'https' ? $value : '';
  }

}
