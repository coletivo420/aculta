<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_maps\Unit;

use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\aculta_maps\MapRenderer;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\key\KeyInterface;
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

    /** @var array<string, mixed> $attributes */
    $attributes = $build['#attributes'] ?? [];
    $this->assertSame('event-map', $attributes['id'] ?? NULL);
    /** @var array<string, mixed> $attached */
    $attached = $build['#attached'] ?? [];
    /** @var list<string> $library */
    $library = $attached['library'] ?? [];
    $this->assertSame('aculta_maps/map', $library[0] ?? NULL);
    /** @var array<string, mixed> $drupalSettings */
    $drupalSettings = $attached['drupalSettings'] ?? [];
    /** @var array<string, mixed> $acultaMaps */
    $acultaMaps = $drupalSettings['acultaMaps'] ?? [];
    /** @var array<string, mixed> $eventMap */
    $eventMap = $acultaMaps['event-map'] ?? [];
    /** @var list<array<string, mixed>> $markers */
    $markers = $eventMap['markers'] ?? [];
    $this->assertCount(1, $markers);
    $marker = $markers[0];
    $this->assertSame(-16.68, $marker['lat']);
    $this->assertSame(-49.26, $marker['lng']);
    $this->assertSame('https://www.google.com/maps/dir/?destination=Goi%C3%A2nia', $marker['routeUrl']);
    $this->assertSame('event-map-popup-0', $marker['popupId']);
    $this->assertSame('event-map-icon-0', $marker['iconTemplateId']);
    $this->assertSame('primary', $marker['categoryColorClass']);
    /** @var array<string, mixed> $popupTemplates */
    $popupTemplates = $build['popup_templates'] ?? [];
    /** @var array<string, mixed> $popupItem */
    $popupItem = isset($popupTemplates['event-map-popup-0']) && is_array($popupTemplates['event-map-popup-0'])
      ? $popupTemplates['event-map-popup-0']
      : [];
    $this->assertSame('aculta_maps_popup_template', $popupItem['#theme'] ?? NULL);
    /** @var array<string, mixed> $popupContent */
    $popupContent = $popupItem['#popup'] ?? [];
    $this->assertSame('Local aproximado', $popupContent['#title'] ?? NULL);
    /** @var array<string, mixed> $iconTemplates */
    $iconTemplates = $build['icon_templates'] ?? [];
    /** @var array<string, mixed> $iconTemplate */
    $iconTemplate = isset($iconTemplates['event-map-icon-0']) && is_array($iconTemplates['event-map-icon-0'])
      ? $iconTemplates['event-map-icon-0']
      : [];
    $this->assertSame('inline_template', $iconTemplate['#type'] ?? NULL);
    $this->assertStringContainsString('data-aculta-map-marker-icon-template', is_scalar($iconTemplate['#template'] ?? NULL) ? (string) $iconTemplate['#template'] : '');
    /** @var array<string, mixed> $iconContext */
    $iconContext = $iconTemplate['#context'] ?? [];
    /** @var array<string, mixed> $iconContextIcon */
    $iconContextIcon = $iconContext['icon'] ?? [];
    $this->assertSame('icon', $iconContextIcon['#name'] ?? NULL);
    /** @var array<string, mixed> $iconProps */
    $iconProps = $iconContextIcon['#props'] ?? [];
    $this->assertSame('map-pin', $iconProps['name'] ?? NULL);
  }

  /**
   * Rejects unsafe schemes before URLs reach theme-rendered popup links.
   */
  public function testPopupUrlsRejectUnsafeSchemes(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['center_lat', -14.235],
      ['center_lng', -51.9253],
      ['zoom_initial', 4],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_maps.settings')->willReturn($settings);
    $renderer = new MapRenderer($configFactory, NULL, $this->createMock(AccountProxyInterface::class));
    $build = $renderer->build([
      'markers' => [[
        'lat' => 0,
        'lng' => 0,
        'label' => 'Unsafe URL',
        'url' => 'javascript:alert(1)',
        'routeUrl' => '//attacker.example/path',
      ]],
    ]);
    /** @var array<string, mixed> $attached */
    $attached = $build['#attached'] ?? [];
    /** @var array<string, mixed> $drupalSettings */
    $drupalSettings = $attached['drupalSettings'] ?? [];
    /** @var array<string, mixed> $acultaMaps */
    $acultaMaps = $drupalSettings['acultaMaps'] ?? [];
    /** @var array<string, mixed> $acultaMap */
    $acultaMap = $acultaMaps['aculta-map'] ?? [];
    /** @var list<array<string, mixed>> $markers */
    $markers = $acultaMap['markers'] ?? [];
    $marker = $markers[0] ?? [];
    $this->assertSame('', $marker['url'] ?? NULL);
    $this->assertSame('', $marker['routeUrl'] ?? NULL);
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
    /** @var array<string, mixed> $attached */
    $attached = $build['#attached'] ?? [];
    /** @var array<string, mixed> $drupalSettings */
    $drupalSettings = $attached['drupalSettings'] ?? [];
    /** @var array<string, mixed> $acultaMaps */
    $acultaMaps = $drupalSettings['acultaMaps'] ?? [];
    /** @var array<string, mixed> $mapSettings */
    $mapSettings = $acultaMaps['fallback-map'] ?? [];

    $this->assertSame('openstreetmap', $mapSettings['provider'] ?? NULL);
    $this->assertArrayNotHasKey('googleApiKey', $mapSettings);
    $providerNotice = is_array($build['provider_notice'] ?? NULL) ? $build['provider_notice'] : [];
    $this->assertTrue(!empty($providerNotice['#access']));
  }

  /**
   * Google maps show attribution for Nominatim coordinates and their ODbL data.
   */
  public function testGoogleMapCreditsNominatimCoordinates(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['engine', 'google'],
      ['center_lat', -14.235],
      ['center_lng', -51.9253],
      ['zoom_initial', 4],
      ['zoom_location', 15],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_maps.settings')->willReturn($settings);
    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);
    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn('test-map-key');
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->with('google_maps_api_key')->willReturn($key);
    $secrets = new SecretsManager($keys, new Settings(['aculta_secrets_environment' => 'test']), $loggerFactory, dirname(__DIR__, 8));
    $renderer = new MapRenderer($configFactory, $secrets, $this->createMock(AccountProxyInterface::class));

    $build = $renderer->build([
      'markers' => [['lat' => -16.68, 'lng' => -49.26, 'label' => 'Local']],
    ]);

    /** @var array<string, mixed> $map */
    $map = $build['map'] ?? [];
    /** @var array<string, mixed> $mapAttached */
    $mapAttached = $map['#attached'] ?? [];
    /** @var array<string, mixed> $mapDrupalSettings */
    $mapDrupalSettings = $mapAttached['drupalSettings'] ?? [];
    /** @var array<string, mixed> $mapAcultaMaps */
    $mapAcultaMaps = $mapDrupalSettings['acultaMaps'] ?? [];
    /** @var array<string, mixed> $mapAcultaMap */
    $mapAcultaMap = $mapAcultaMaps['aculta-map'] ?? [];

    $this->assertSame('google', $mapAcultaMap['provider'] ?? NULL);
    /** @var array<string, mixed> $attribution */
    $attribution = $build['geocoding_attribution'] ?? [];
    /** @var array<string, mixed> $textElem */
    $textElem = $attribution['text'] ?? [];
    $plainText = is_scalar($textElem['#plain_text'] ?? NULL) ? (string) $textElem['#plain_text'] : '';
    $this->assertStringContainsString('OpenStreetMap contributors', $plainText);
    /** @var array<string, mixed> $licenseElem */
    $licenseElem = $attribution['license'] ?? [];
    $urlObj = $licenseElem['#url'] ?? NULL;
    $this->assertInstanceOf(Url::class, $urlObj);
    $this->assertSame('https://www.openstreetmap.org/copyright', $urlObj->getUri());
  }

}
