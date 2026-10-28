<?php

declare(strict_types=1);

namespace Drupal\aculta_maps\Geocoding;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Geocodes addresses on the server. Replaced by provider selection in MP3–MP4.
 */
final class GeocodingService {

  private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

  public function __construct(
    private readonly ClientInterface $http,
    private readonly KeyRepositoryInterface $keys,
    private readonly LoggerChannelFactoryInterface $logs,
  ) {}

  /**
   * Geocodes an address, or returns NULL if no key/result is available.
   *
   * @return array{lat: float, lng: float}|null
   *   Geocoded coordinates or NULL.
   */
  public function geocode(string $address): ?array {
    $key = $this->key();
    if ($key === NULL || trim($address) === '') {
      return NULL;
    }
    try {
      $response = $this->http->request('GET', self::ENDPOINT, [
        'query' => ['address' => $address, 'region' => 'br', 'key' => $key],
        'timeout' => 10,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Throwable $exception) {
      $this->logs->get('aculta_maps')->warning('Geocoding failed: @class.', ['@class' => $exception::class]);
      return NULL;
    }
    return $this->interpret(is_array($data) ? $data : []);
  }

  /**
   * Interprets provider response data without making a network request.
   *
   * @return array{lat: float, lng: float}|null
   *   Parsed coordinates or NULL.
   */
  public function interpret(array $data): ?array {
    if (($data['status'] ?? '') !== 'OK') {
      $this->logs->get('aculta_maps')->notice('Geocoding returned no result. Status: @status.', ['@status' => (string) ($data['status'] ?? 'unknown')]);
      return NULL;
    }
    $location = $data['results'][0]['geometry']['location'] ?? NULL;
    if (!is_array($location) || !isset($location['lat'], $location['lng'])) {
      return NULL;
    }
    return ['lat' => (float) $location['lat'], 'lng' => (float) $location['lng']];
  }

  /**
   * Returns the configured Google key, or NULL.
   */
  public function key(): ?string {
    $key = $this->keys->getKey('google_maps_api_key');
    $value = $key?->getKeyValue();
    return is_string($value) && $value !== '' ? $value : NULL;
  }

}
