<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies the administrative and Studio domain contracts in source config.
 */
final class EditorialRouteConfigTest extends UnitTestCase {

  /**
   * Confirms the MAIN and account-purpose routes remain separate.
   */
  public function testAdministrativeAndStudioRoutePurposes(): void {
    $module = dirname(__DIR__, 3);
    $routes = self::readYamlFile($module . '/aculta_magazine.routing.yml');
    self::assertSame('/painel-administrativo/revista', self::valueAt($routes, 'aculta_magazine.admin', 'path'));
    foreach (['admin', 'capabilities', 'admin_comments'] as $route) {
      self::assertSame('main', self::valueAt($routes, 'aculta_magazine.' . $route, 'options', '_aculta_site_key'));
      self::assertTrue(self::valueAt($routes, 'aculta_magazine.' . $route, 'options', '_admin_route'));
    }
    $studioRoutes = [
      'studio_list',
      'studio_add_popup',
      'studio_edit',
      'studio_transition',
      'studio_comment_moderate',
      'studio_discussion',
    ];
    foreach ($studioRoutes as $route) {
      self::assertSame('account', self::valueAt($routes, 'aculta_magazine.' . $route, 'options', '_aculta_site_key'));
    }
    self::assertStringNotContainsString('/studio/marley', self::readFile($module . '/aculta_magazine.routing.yml'));
  }

  /**
   * Confirms the administrative View uses the approved path and permission.
   */
  public function testAdministrativeViewNoLongerUsesGenericContentPath(): void {
    $module = dirname(__DIR__, 3);
    $repository = dirname($module, 6);
    $view = self::readYamlFile($repository . '/config/sync/views.view.aculta_magazine_admin_content.yml');
    self::assertSame('painel-administrativo/revista/publicacoes', self::valueAt($view, 'display', 'page_magazine', 'display_options', 'path'));
    self::assertSame('administer site configuration', self::valueAt($view, 'display', 'default', 'display_options', 'access', 'options', 'perm'));
  }

  /**
   * Reads and decodes a YAML file, rejecting unreadable or non-mapping input.
   *
   * @return array<array-key, mixed>
   *   Decoded YAML mapping.
   */
  private static function readYamlFile(string $path): array {
    $contents = self::readFile($path);
    $decoded = Yaml::decode($contents);
    if (!is_array($decoded)) {
      throw new \UnexpectedValueException(sprintf('YAML file "%s" must contain a mapping.', $path));
    }

    return $decoded;
  }

  /**
   * Reads a file and rejects I/O failures.
   */
  private static function readFile(string $path): string {
    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $path));
    }

    return $contents;
  }

  /**
   * Reads a config value while validating every parent mapping.
   */
  private static function valueAt(mixed $data, string ...$path): mixed {
    $value = $data;
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        throw new \UnexpectedValueException(sprintf('Missing config key "%s".', $key));
      }
      $value = $value[$key];
    }

    return $value;
  }

}
