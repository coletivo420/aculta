<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_secrets_manager\Unit;

use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests environment isolation and access requirements for panel routes.
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_secrets_manager')]
final class SecretsFallbackAndRoutesTest extends UnitTestCase {

  /**
   * Temporary directory for credential files.
   */
  private string $base;

  /**
   * Creates a temporary credential directory.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->base = sys_get_temp_dir() . '/aculta-fallback-' . bin2hex(random_bytes(6));
    mkdir($this->base . '/web', 0700, TRUE);
    mkdir($this->base . '/config', 0700, TRUE);
    mkdir($this->base . '/secrets', 0700, TRUE);
    file_put_contents($this->base . '/config/secrets-contract.json', json_encode([
      'variables' => [
        [
          'name' => 'GOOGLE_OAUTH_CLIENT_ID',
          'key_id' => 'g_id',
          'environments' => ['production', 'test'],
          'required_in' => ['production', 'test'],
        ],
      ],
    ]));
  }

  /**
   * Removes the temporary credential directory.
   */
  protected function tearDown(): void {
    foreach (glob($this->base . '/secrets/*') ?: [] as $f) {
      @unlink($f);
    }
    @rmdir($this->base . '/secrets');
    @unlink($this->base . '/config/secrets-contract.json');
    @rmdir($this->base . '/config');
    @rmdir($this->base . '/web');
    @rmdir($this->base);
    parent::tearDown();
  }

  /**
   * Keeps test credentials isolated from production credentials.
   */
  public function testTestEnvironmentDoesNotFallBackToProductionValue(): void {
    $production = $this->base . '/secrets/producao.env';
    file_put_contents($production, "GOOGLE_OAUTH_CLIENT_ID=valor-producao\n");
    file_put_contents($this->base . '/secrets/aculta.secrets.env', "");
    $this->assertNull($this->manager('test', $this->base . '/secrets/aculta.secrets.env')->value('GOOGLE_OAUTH_CLIENT_ID'));
    @unlink($production);
  }

  /**
   * Keeps production credentials isolated from test credentials.
   */
  public function testProductionEnvironmentDoesNotFallBackToTestValue(): void {
    $test = $this->base . '/secrets/teste.env';
    file_put_contents($test, "GOOGLE_OAUTH_CLIENT_ID=valor-teste\n");
    file_put_contents($this->base . '/secrets/aculta.secrets.env', "");
    $this->assertNull($this->manager('production', $this->base . '/secrets/aculta.secrets.env')->value('GOOGLE_OAUTH_CLIENT_ID'));
    @unlink($test);
  }

  /**
   * Requires permission on every panel route.
   */
  public function testEveryPanelRouteRequiresPermissionAndIsNotAnonymous(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_secrets_manager.routing.yml');
    $this->assertCount(4, $routes);
    foreach ($routes as $name => $route) {
      $this->assertSame('administer aculta secrets', $route['requirements']['_permission'] ?? NULL, $name);
      $this->assertArrayNotHasKey('_access', $route['requirements'] ?? [], $name);
    }
  }

  /**
   * Requires CSRF tokens for reveal and write routes.
   */
  public function testRevealAndWriteRoutesRequireCsrfToken(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_secrets_manager.routing.yml');
    foreach (['aculta_portal.secrets_show', 'aculta_portal.secrets_edit', 'aculta_portal.secrets_clear'] as $name) {
      $this->assertSame('TRUE', $routes[$name]['requirements']['_csrf_token'] ?? NULL, $name);
    }
  }

  /**
   * Creates a manager scoped to one environment.
   */
  private function manager(string $environment, string $store): SecretsManager {
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $settings = new Settings([
      'aculta_secrets_file' => $store,
      'aculta_secrets_environment' => $environment,
      'aculta_secrets_storage' => 'file',
    ]);
    return new SecretsManager($keys, $settings, $factory, $this->base . '/web');
  }

}
