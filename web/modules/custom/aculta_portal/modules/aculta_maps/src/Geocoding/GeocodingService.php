<?php

declare(strict_types=1);

namespace Drupal\aculta_maps\Geocoding;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;

/**
 * Geocodes a location once when an administrator saves its address.
 */
final class GeocodingService {

  private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

  private const RATE_LIMIT_LOCK = 'aculta_maps.nominatim.rate_limit';

  private const LAST_REQUEST_KEY = 'aculta_maps.nominatim.last_request';

  public function __construct(
    private readonly ClientInterface $http,
    private readonly LoggerChannelFactoryInterface $logs,
    private readonly StateInterface $state,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Returns coordinates from Nominatim or NULL when no result is available.
   *
   * The persistent lock and timestamp enforce no more than one request per
   * second across concurrent web workers. Successful coordinates are stored
   * on the source location entity by its presave hook.
   *
   * @return array{lat: float, lng: float}|null
   *   Coordinates or NULL.
   */
  public function geocode(string $address): ?array {
    $address = trim($address);
    if ($address === '' || !$this->lock->acquire(self::RATE_LIMIT_LOCK)) {
      return NULL;
    }

    try {
      $this->waitForRateLimit();
      $response = $this->http->request('GET', self::ENDPOINT, [
        'query' => [
          'q' => $address,
          'format' => 'jsonv2',
          'limit' => 1,
          'countrycodes' => 'br',
        ],
        'headers' => [
          'Accept' => 'application/json',
          'User-Agent' => 'ACULTA Maps/1.0',
        ],
        'connect_timeout' => 3,
        'timeout' => 8,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      $this->state->set(self::LAST_REQUEST_KEY, $this->time->getCurrentTime());
      return $this->interpret(is_array($data) ? $data : []);
    }
    catch (\Throwable $exception) {
      $this->state->set(self::LAST_REQUEST_KEY, $this->time->getCurrentTime());
      $this->logs->get('aculta_maps')->warning('Geocoding failed: @class.', ['@class' => $exception::class]);
      return NULL;
    }
    finally {
      $this->lock->release(self::RATE_LIMIT_LOCK);
    }
  }

  /**
   * Interprets a Nominatim JSON response without making a network request.
   *
   * @return array{lat: float, lng: float}|null
   *   Parsed coordinates or NULL.
   */
  public function interpret(array $data): ?array {
    $result = $data[0] ?? NULL;
    if (!is_array($result) || !is_numeric($result['lat'] ?? NULL) || !is_numeric($result['lon'] ?? NULL)) {
      return NULL;
    }
    $latitude = (float) $result['lat'];
    $longitude = (float) $result['lon'];
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
      return NULL;
    }
    return ['lat' => $latitude, 'lng' => $longitude];
  }

  /**
   * Waits for the public Nominatim one-request-per-second limit.
   */
  private function waitForRateLimit(): void {
    $lastRequest = (int) $this->state->get(self::LAST_REQUEST_KEY, 0);
    $waitSeconds = 1 - ($this->time->getCurrentTime() - $lastRequest);
    if ($lastRequest > 0 && $waitSeconds > 0) {
      usleep((int) ($waitSeconds * 1_000_000));
    }
  }

}
