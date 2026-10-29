<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_maps\Unit;

use Drupal\aculta_maps\Geocoding\GeocodingService;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests Nominatim geocoding without external network requests.
 */
#[Group('aculta_maps')]
final class GeocodingServiceTest extends UnitTestCase {

  /**
   * Creates the service with mocks.
   */
  private function service(ClientInterface $http): GeocodingService {
    $logger = $this->createMock(LoggerInterface::class);
    $logs = $this->createMock(LoggerChannelFactoryInterface::class);
    $logs->method('get')->willReturn($logger);
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn(0);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(500);
    return new GeocodingService($http, $logs, $state, $lock, $time);
  }

  /**
   * Request identity, Brazil filter, and coordinate parsing are correct.
   */
  public function testNominatimRequestPolicyAndCoordinates(): void {
    $http = $this->createMock(ClientInterface::class);
    $http->expects($this->once())
      ->method('request')
      ->with('GET', 'https://nominatim.openstreetmap.org/search', $this->callback(static function (array $options): bool {
        return ($options['headers']['User-Agent'] ?? '') === 'ACULTA Maps/1.0'
          && ($options['query']['countrycodes'] ?? '') === 'br'
          && ($options['query']['format'] ?? '') === 'jsonv2'
          && ($options['query']['limit'] ?? 0) === 1
          && ($options['timeout'] ?? 0) === 8;
      }))
      ->willReturn(new Response(200, [], '[{"lat":"-16.68","lon":"-49.26"}]'));

    $this->assertSame(['lat' => -16.68, 'lng' => -49.26], $this->service($http)->geocode('Rua 10, Goiânia, GO, Brasil'));
  }

  /**
   * Empty and out-of-range Nominatim responses return no coordinates.
   */
  public function testNoResultOrInvalidCoordinatesReturnsNull(): void {
    $service = $this->service($this->createMock(ClientInterface::class));
    $this->assertNull($service->interpret([]));
    $this->assertNull($service->interpret([['lat' => '100', 'lon' => '0']]));
    $this->assertNull($service->interpret([['lat' => '0', 'lon' => '181']]));
  }

}
