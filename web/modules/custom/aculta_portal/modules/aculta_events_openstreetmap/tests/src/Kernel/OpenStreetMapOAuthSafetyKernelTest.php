<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events_openstreetmap\Kernel;

use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthClient;
use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthException;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapCredentialProviderInterface;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapEnvironment;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapTokenCipher;
use Drupal\aculta_events_openstreetmap\Security\OpenStreetMapTokenStore;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
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
   * Missing credentials and a production target on a test site fail closed.
   */
  public function testMissingCredentialsAndProductionTargetOnTestAreRefused(): void {
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
    $client->authorizationUrl('state', 'https://aculta.toca.net.br/integracoes/openstreetmap/retorno');
  }

  /**
   * A test site cannot select the production OpenStreetMap API.
   */
  public function testProductionTargetOnTestSiteIsRefused(): void {
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
    $this->assertNull($environment->environment());
    $this->assertNull($environment->apiBaseUrl());
    $this->assertNull($environment->oauthBaseUrl());
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
    $client->exchangeAuthorizationCode('test-code', 'https://aculta.toca.net.br/integracoes/openstreetmap/retorno');
    $this->assertSame($token, $store->load());
    $stored = $this->container->get('state')->get('aculta_events_openstreetmap.oauth_token.test');
    $this->assertIsArray($stored);
    $this->assertStringNotContainsString($token, $stored['payload']);
    $this->assertNull($this->container->get('state')->get('aculta_events_openstreetmap.oauth_token.production'));
  }

}
