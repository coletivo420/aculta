<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events_openstreetmap\Kernel;

use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthClient;
use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthException;
use Drupal\aculta_events_openstreetmap\Osm\OpenStreetMapApiClient;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapCredentialProviderInterface;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapEnvironment;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapTokenCipher;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapTokenStore;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * OSM OAuth environment and encrypted token safety without external requests.
 */
#[Group('aculta_events_openstreetmap')]
#[RunTestsInSeparateProcesses]
final class OpenStreetMapOAuthSafetyKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'taxonomy', 'link', 'text', 'filter', 'options',
    'datetime', 'datetime_range', 'aculta_calendar', 'aculta_events', 'aculta_events_openstreetmap',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);
  }

  /**
   * Common accounts cannot access the integration or its import wizard.
   */
  public function testIntegrationRoutesRequireEventAdministration(): void {
    $provider = $this->container->get('router.route_provider');
    $access = $this->container->get('access_manager');
    $account = $this->container->get('current_user')->getAccount();
    foreach (['aculta_events_openstreetmap.status', 'aculta_events_openstreetmap.import'] as $route_name) {
      $route = $provider->getRouteByName($route_name);
      $this->assertSame('administer aculta events', $route->getRequirement('_permission'));
      $this->assertFalse($access->checkNamedRoute($route_name, [], $account));
    }
  }

  /**
   * Missing credentials fail closed; test defaults to the development API.
   */
  public function testMissingCredentialsAreRefusedAndTestDefaultsToDevelopment(): void {
    $missing = new class() implements OpenStreetMapCredentialProviderInterface {

      /** Returns the test site environment. */
      public function siteEnvironment(): string {
        return 'test';
      }

      /** Returns no client ID when credentials are absent. */
      public function clientId(string $environment): ?string {
        return NULL;
      }

      /** Returns no client secret when credentials are absent. */
      public function clientSecret(string $environment): ?string {
        return NULL;
      }

      /** Returns no encryption key when credentials are absent. */
      public function tokenEncryptionKey(): ?string {
        return NULL;
      }

    };
    $defaultEnvironment = new OpenStreetMapEnvironment($missing, new Settings([]));
    $this->assertSame('test', $defaultEnvironment->environment());
    $this->assertSame('https://master.apis.dev.openstreetmap.org/api/0.6', $defaultEnvironment->apiBaseUrl());

    $tokenStore = new OpenStreetMapTokenStore(
      $this->container->get('state'),
      new OpenStreetMapTokenCipher($missing),
      $defaultEnvironment,
    );
    $api = new OpenStreetMapApiClient(
      new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
      $defaultEnvironment,
      $missing,
      $tokenStore,
      $this->container->get('logger.factory'),
    );
    try {
      $api->update(['type' => 'node', 'id' => '1', 'version' => '1'], ['name' => 'Local fictício'], 'Teste fictício');
      $this->fail('An OSM edit must be refused without credentials.');
    }
    catch (OpenStreetMapOAuthException) {
      $this->assertTrue(TRUE);
    }
    $http = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
    $client = new OpenStreetMapOAuthClient(
      $http,
      $missing,
      $defaultEnvironment,
      $tokenStore,
      $this->container->get('logger.factory'),
    );
    $this->assertFalse($client->isConfigured());
    $this->expectException(OpenStreetMapOAuthException::class);
    $client->authorizationUrl('state', 'https://site.example.invalid/integracoes/openstreetmap/retorno');
  }

  /**
   * A test site can select the live API only through an explicit setting.
   */
  public function testExplicitProductionTargetOnTestSiteSelectsLiveApiButCannotWriteWithoutToken(): void {
    $this->assertSame('test', OpenStreetMapEnvironment::resolve('test', NULL));
    $this->assertSame('production', OpenStreetMapEnvironment::resolve('test', 'production'));
    $this->assertNull(OpenStreetMapEnvironment::resolve('production', NULL));
    $this->assertNull(OpenStreetMapEnvironment::resolve('production', 'test'));

    $credentials = new class() implements OpenStreetMapCredentialProviderInterface {

      /** Returns the test site environment. */
      public function siteEnvironment(): string {
        return 'test';
      }

      /** Returns no client ID when credentials are absent. */
      public function clientId(string $environment): ?string {
        return NULL;
      }

      /** Returns no client secret when credentials are absent. */
      public function clientSecret(string $environment): ?string {
        return NULL;
      }

      /** Returns no encryption key when credentials are absent. */
      public function tokenEncryptionKey(): ?string {
        return NULL;
      }

    };
    $environment = new OpenStreetMapEnvironment(
      $credentials,
      new Settings(['aculta_openstreetmap_environment' => 'production']),
    );
    $this->assertSame('production', $environment->environment());
    $this->assertSame('https://api.openstreetmap.org/api/0.6', $environment->apiBaseUrl());
    $this->assertSame('https://www.openstreetmap.org', $environment->oauthBaseUrl());
    $api = new OpenStreetMapApiClient(
      new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
      $environment,
      $credentials,
      new OpenStreetMapTokenStore(
        $this->container->get('state'),
        new OpenStreetMapTokenCipher($credentials),
        $environment,
      ),
      $this->container->get('logger.factory'),
    );
    try {
      $api->update(['type' => 'node', 'id' => '1', 'version' => '1'], ['name' => 'Local fictício'], 'Teste fictício');
      $this->fail('An OSM edit must be refused without an authenticated token.');
    }
    catch (OpenStreetMapOAuthException) {
      $this->assertTrue(TRUE);
    }
  }

  /**
   * OAuth exchange stores a token encrypted and scoped to the test environment.
   */
  public function testOauthTokenIsEncryptedInStateAndScopedToTest(): void {
    $credentials = new class() implements OpenStreetMapCredentialProviderInterface {

      /** Test client ID. */
      private string $id;

      /** Test client secret. */
      private string $secret;

      /** Token encryption key. */
      private string $key;

      public function __construct() {
        $this->id = bin2hex(random_bytes(8));
        $this->secret = bin2hex(random_bytes(16));
        $this->key = bin2hex(random_bytes(32));
      }

      /** Returns the test site environment. */
      public function siteEnvironment(): string {
        return 'test';
      }

      /** Returns the configured test client ID. */
      public function clientId(string $environment): ?string {
        return $environment === 'test' ? $this->id : NULL;
      }

      /** Returns the configured test client secret. */
      public function clientSecret(string $environment): ?string {
        return $environment === 'test' ? $this->secret : NULL;
      }

      /** Returns the configured token encryption key. */
      public function tokenEncryptionKey(): ?string {
        return $this->key;
      }

    };
    $environment = new OpenStreetMapEnvironment($credentials, new Settings([]));
    $cipher = new OpenStreetMapTokenCipher($credentials);
    $store = new OpenStreetMapTokenStore($this->container->get('state'), $cipher, $environment);
    $token = bin2hex(random_bytes(24));
    $http = new Client([
      'handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
          'access_token' => $token,
          'token_type' => 'Bearer',
          'scope' => 'write_api',
          'created_at' => time(),
        ], JSON_THROW_ON_ERROR)),
      ])),
    ]);
    $client = new OpenStreetMapOAuthClient(
      $http,
      $credentials,
      $environment,
      $store,
      $this->container->get('logger.factory'),
    );

    $this->assertTrue($client->isConfigured());
    $client->exchangeAuthorizationCode('test-code', 'https://site.example.invalid/integracoes/openstreetmap/retorno');
    $this->assertSame($token, $store->load());
    $stored = $this->container->get('state')->get('aculta_events_openstreetmap.oauth_token.test');
    $this->assertIsArray($stored);
    $this->assertStringNotContainsString($token, $stored['payload']);
    $this->assertNull($this->container->get('state')->get('aculta_events_openstreetmap.oauth_token.production'));

    $responses = [
      new Response(200, ['Content-Type' => 'application/xml'], '<osm version="0.6"><node id="100" lat="-23.5" lon="-46.6" version="1"><tag k="name" v="Local fictício"/><tag k="contact:email" v="nao-importar@example.invalid"/></node></osm>'),
      new Response(200, ['Content-Type' => 'application/xml'], '<osm version="0.6"><node id="100" lat="-23.5" lon="-46.6" version="1"><tag k="name" v="Local fictício"/><tag k="contact:email" v="nao-importar@example.invalid"/></node></osm>'),
      new Response(200, [], '42'),
      new Response(200, ['Content-Type' => 'application/xml'], '<osm version="0.6"><node id="100" lat="-23.5" lon="-46.6" version="1"><tag k="name" v="Local fictício"/><tag k="contact:email" v="nao-importar@example.invalid"/></node></osm>'),
      new Response(200, [], '2'),
      new Response(200),
    ];
    $history = [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $api = new OpenStreetMapApiClient(new Client(['handler' => $stack]), $environment, $credentials, $store, $this->container->get('logger.factory'));
    $found = $api->find('node', '100');
    $this->assertSame('Local fictício', $found['tags']['name']);
    $this->assertArrayNotHasKey('contact:email', $found['tags']);
    $result = $api->update($found, ['name' => 'Local de teste fictício'], 'Atualização de teste fictícia');
    $this->assertSame(['changeset' => '42', 'version' => '2'], $result);
    $this->assertCount(6, $history);
    foreach ($history as $transaction) {
      $this->assertStringStartsWith('https://master.apis.dev.openstreetmap.org/api/0.6/', (string) $transaction['request']->getUri());
      $this->assertStringNotContainsString('api.openstreetmap.org/api', (string) $transaction['request']->getUri());
      if ($transaction['request']->getMethod() !== 'GET') {
        $this->assertSame('Bearer ' . $token, $transaction['request']->getHeaderLine('Authorization'));
      }
    }
  }

}
