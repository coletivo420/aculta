<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_maps\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\aculta_maps\MapRenderer;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\key\KeyRepositoryInterface;
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
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $secrets = new SecretsManager($this->createMock(KeyRepositoryInterface::class), new Settings(['aculta_secrets_environment' => 'test']), $loggerFactory, '/app');
    $renderer = new MapRenderer($configFactory, $secrets, $this->createMock(AccountProxyInterface::class));

    $build = $renderer->build([
      'id' => 'event map',
      'label' => 'Eventos',
      'markers' => [
        [
          'lat' => -16.68123,
          'lng' => -49.26011,
          'label' => 'Local aproximado',
          'precision' => 'approximate',
          'routeUrl' => 'https://www.google.com/maps/dir/?destination=Goi%C3%A2nia',
        ],
        ['lat' => 91, 'lng' => 0, 'label' => 'Coordenada inválida'],
      ],
    ]);

    $this->assertSame('event-map', $build['#attributes']['id']);
    $this->assertSame('aculta_maps/map', $build['#attached']['library'][0]);
    $this->assertSame(-16.68, $build['#attached']['drupalSettings']['acultaMaps']['event-map']['markers'][0]['lat']);
    $this->assertSame(-49.26, $build['#attached']['drupalSettings']['acultaMaps']['event-map']['markers'][0]['lng']);
    $this->assertCount(1, $build['#attached']['drupalSettings']['acultaMaps']['event-map']['markers']);
    $this->assertSame('https://www.google.com/maps/dir/?destination=Goi%C3%A2nia', $build['#attached']['drupalSettings']['acultaMaps']['event-map']['markers'][0]['routeUrl']);
  }

  /**
   * Missing Google credentials select OSM and only inform map administrators.
   */
  public function testMissingGoogleCredentialFallsBackToOpenStreetMap(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['engine', 'google'],
      ['center_lat', -14.235],
      ['center_lng', -51.9253],
      ['zoom_initial', 4],
      ['zoom_location', 15],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($settings);
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $secrets = new SecretsManager($this->createMock(KeyRepositoryInterface::class), new Settings(['aculta_secrets_environment' => 'test']), $loggerFactory, '/app');
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')->with('administer aculta maps')->willReturn(TRUE);
    $renderer = new MapRenderer($configFactory, $secrets, $user);

    $build = $renderer->build(['id' => 'fallback-map']);
    $mapSettings = $build['#attached']['drupalSettings']['acultaMaps']['fallback-map'];

    $this->assertSame('openstreetmap', $mapSettings['provider']);
    $this->assertArrayNotHasKey('googleApiKey', $mapSettings);
    $this->assertTrue($build['provider_notice']['#access']);
  }

}
