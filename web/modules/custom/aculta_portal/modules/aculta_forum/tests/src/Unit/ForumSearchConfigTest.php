<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the public search configuration and its Config Sync copy.
 */
#[Group('aculta_forum')]
final class ForumSearchConfigTest extends UnitTestCase {

  /**
   * Search covers public Forum topics and posts with access checks enabled.
   */
  public function testIndexAndViewRemainPublicForumOnly(): void {
    $root = dirname(__DIR__, 3);
    $installDirectory = $root . '/config/install';
    $repositoryRoot = dirname($root, 6);
    $indexFile = 'search_api.index.aculta_forum.yml';
    $viewFile = 'views.view.aculta_forum_search.yml';
    $index = self::parseConfigFile($installDirectory . '/' . $indexFile);
    $view = self::parseConfigFile($installDirectory . '/' . $viewFile);

    self::assertSame($index, self::parseConfigFile($repositoryRoot . '/config/sync/' . $indexFile));
    self::assertSame($view, self::parseConfigFile($repositoryRoot . '/config/sync/' . $viewFile));
    self::assertSame(['aculta_forum_topic'], self::configValue($index, 'datasource_settings', 'entity:node', 'bundles', 'selected'));
    self::assertSame(['aculta_forum_post'], self::configValue($index, 'datasource_settings', 'entity:aculta_forum_post', 'bundles', 'selected'));
    self::assertSame('forum_search_category', self::configValue($index, 'field_settings', 'forum_search_category', 'property_path'));
    self::assertSame('integer', self::configValue($index, 'field_settings', 'forum_search_category', 'type'));
    self::assertSame(['aculta_forum_topic', 'aculta_forum_post'], self::configValue($view, 'display', 'default', 'display_options', 'filters', 'type', 'value'));
    self::assertSame('forum_search_type', self::configValue($view, 'display', 'default', 'display_options', 'filters', 'type', 'field'));
    self::assertSame('search_api_numeric', self::configValue($view, 'display', 'default', 'display_options', 'filters', 'forum_category', 'plugin_id'));
    self::assertSame('categoria', self::configValue($view, 'display', 'default', 'display_options', 'filters', 'forum_category', 'expose', 'identifier'));
    self::assertSame('text_custom', self::configValue($view, 'display', 'default', 'display_options', 'empty', 'area_text_custom', 'plugin_id'));
    self::assertStringContainsString('Nenhuma discussão corresponde', self::configString($view, 'display', 'default', 'display_options', 'empty', 'area_text_custom', 'content'));
    self::assertStringContainsString('href="/categorias"', self::configString($view, 'display', 'default', 'display_options', 'empty', 'area_text_custom', 'content'));
    self::assertArrayHasKey('content_access', self::configArray($index, 'processor_settings'));
    self::assertArrayHasKey('forum_post_search_access', self::configArray($index, 'processor_settings'));
    self::assertArrayHasKey('entity_status', self::configArray($index, 'processor_settings'));
    self::assertTrue(self::configValue($index, 'options', 'index_directly'));
    self::assertSame('access content', self::configValue($view, 'display', 'default', 'display_options', 'access', 'options', 'perm'));
    self::assertSame('search_api_none', self::configValue($view, 'display', 'default', 'display_options', 'cache', 'type'));
    self::assertArrayNotHasKey('skip_access', self::configArray($view, 'display', 'default', 'display_options', 'query', 'options'));
    self::assertSame('pesquisar', self::configValue($view, 'display', 'page_search', 'display_options', 'path'));
    self::assertSame('forum_search_result', self::configValue($view, 'display', 'default', 'display_options', 'row', 'options', 'view_modes', 'entity:aculta_forum_post', ':default'));
  }

  /**
   * Parses one YAML configuration file and verifies its root type.
   *
   * @return array<array-key, mixed>
   *   Parsed configuration values.
   */
  private static function parseConfigFile(string $path): array {
    $config = Yaml::parseFile($path);
    if (!is_array($config)) {
      throw new \UnexpectedValueException('Expected a YAML configuration map.');
    }
    return $config;
  }

  /**
   * Reads a value after validating each level in its configuration path.
   *
   * @param mixed $value
   *   Configuration map or nested value.
   * @param array-key ...$path
   *   Keys identifying the requested value.
   */
  private static function configValue(mixed $value, int|string ...$path): mixed {
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        throw new \UnexpectedValueException('A required configuration path is missing.');
      }
      $value = $value[$key];
    }
    return $value;
  }

  /**
   * Reads a configuration value that must be an array.
   *
   * @param mixed $value
   *   Configuration map or nested value.
   * @param array-key ...$path
   *   Keys identifying the requested array.
   *
   * @return array<array-key, mixed>
   *   The validated array value.
   */
  private static function configArray(mixed $value, int|string ...$path): array {
    $value = self::configValue($value, ...$path);
    if (!is_array($value)) {
      throw new \UnexpectedValueException('Expected an array in configuration.');
    }
    return $value;
  }

  /**
   * Reads a configuration value that must be a string.
   *
   * @param mixed $value
   *   Configuration map or nested value.
   * @param array-key ...$path
   *   Keys identifying the requested string.
   *
   * @return string
   *   The validated string value.
   */
  private static function configString(mixed $value, int|string ...$path): string {
    $value = self::configValue($value, ...$path);
    if (!is_string($value)) {
      throw new \UnexpectedValueException('Expected a string in configuration.');
    }
    return $value;
  }

}
