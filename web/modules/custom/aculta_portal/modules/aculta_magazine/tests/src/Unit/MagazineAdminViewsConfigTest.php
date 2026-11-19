<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks the administrator-owned Magazine views and their canonical path.
 */
#[Group('aculta_magazine')]
final class MagazineAdminViewsConfigTest extends UnitTestCase {

  /**
   * Content view stays under the Magazine administrative center.
   */
  public function testMagazineAdministrationIsUnifiedAndScoped(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    $page_filename = 'views.view.aculta_magazine_admin_content.yml';
    $comments_filename = 'views.view.aculta_magazine_admin_comments.yml';
    $page = Yaml::parseFile($module_path . '/config/install/' . $page_filename);
    $page_sync = Yaml::parseFile($repository_path . '/config/sync/' . $page_filename);
    $comments = Yaml::parseFile($module_path . '/config/install/' . $comments_filename);
    $comments_sync = Yaml::parseFile($repository_path . '/config/sync/' . $comments_filename);
    // Config Sync may contain runtime-only Views state. Assert the shared
    // contract explicitly instead of comparing unrelated serialization data.
    self::assertSame(self::value($page, 'id'), self::value($page_sync, 'id'));
    self::assertSame(self::value($page, 'display', 'page_magazine', 'display_options', 'path'), self::value($page_sync, 'display', 'page_magazine', 'display_options', 'path'));
    self::assertSame(self::value($page, 'display', 'default', 'display_options', 'filters', 'type', 'value'), self::value($page_sync, 'display', 'default', 'display_options', 'filters', 'type', 'value'));
    self::assertSame($comments, $comments_sync);

    self::assertSame('painel-administrativo/revista/publicacoes', self::value($page, 'display', 'page_magazine', 'display_options', 'path'));
    self::assertSame(
      ['revista' => 'revista'],
      self::value($page, 'display', 'default', 'display_options', 'filters', 'type', 'value'),
    );
    self::assertSame('administer site configuration', self::value($page, 'display', 'default', 'display_options', 'access', 'options', 'perm'));

    $options = self::arrayValue($comments, 'display', 'default', 'display_options');
    self::assertSame('administer comments', self::value($options, 'access', 'options', 'perm'));
    self::assertSame('field_aculta_discussion', self::value($options, 'filters', 'field_name', 'value'));
    self::assertSame(['revista' => 'revista'], self::value($options, 'filters', 'type', 'value'));
    self::assertSame('block', self::value($comments, 'display', 'block_comments', 'display_plugin'));

    $block = Yaml::parseFile($repository_path . '/config/sync/block.block.aculta_magazine_admin_comments.yml');
    self::assertSame('views_block:aculta_magazine_admin_comments-block_comments', self::value($block, 'plugin'));
    self::assertStringContainsString('/painel-administrativo/revista/comentarios', self::stringValue($block, 'visibility', 'request_path', 'pages'));
    self::assertSame('main', self::value($block, 'visibility', 'aculta_domain_site', 'site_key'));
  }

  /**
   * The Magazine center has links in the administrative menu.
   */
  public function testAdministrativeMenuLinkTargetsUnifiedMagazinePage(): void {
    $menu = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_magazine.links.menu.yml');

    self::assertSame('aculta_magazine.admin', self::value($menu, 'aculta_magazine.admin', 'route_name'));
    self::assertSame('system.admin', self::value($menu, 'aculta_magazine.admin', 'parent'));
    self::assertSame('admin', self::value($menu, 'aculta_magazine.admin', 'menu_name'));
    self::assertSame('view.aculta_magazine_admin_content.page_magazine', self::value($menu, 'aculta_magazine.admin_publications', 'route_name'));
  }

  /**
   * Config Sync exposes only the canonical bundle to editorial services.
   */
  public function testLegacyBundlesAreAbsentFromEditorialConfiguration(): void {
    $module_path = dirname(__DIR__, 3);
    $repository_path = dirname($module_path, 6);
    foreach (['article', 'blog'] as $bundle) {
      self::assertFileDoesNotExist($repository_path . '/config/sync/node.type.' . $bundle . '.yml');
      self::assertFileDoesNotExist($repository_path . '/config/sync/metatag.metatag_defaults.node__' . $bundle . '.yml');
    }

    $pathauto = Yaml::parseFile($repository_path . '/config/sync/pathauto.pattern.aculta_article.yml');
    $pathauto_criteria = array_values(self::arrayValue($pathauto, 'selection_criteria'));
    self::assertSame(['revista' => 'revista'], self::value($pathauto_criteria[0] ?? NULL, 'bundles'));

    $search = Yaml::parseFile($repository_path . '/config/sync/search_api.index.aculta_conteudo.yml');
    $selected_bundles = self::arrayValue($search, 'datasource_settings', 'entity:node', 'bundles', 'selected');
    self::assertContains('revista', $selected_bundles);
    self::assertNotContains('article', $selected_bundles);
    self::assertNotContains('blog', $selected_bundles);
  }

  /**
   * Reads a YAML value while validating every parent mapping.
   */
  private static function value(mixed $data, string ...$path): mixed {
    $value = $data;
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        throw new \UnexpectedValueException(sprintf('Missing YAML mapping key "%s".', $key));
      }
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Reads a YAML mapping while validating every parent mapping.
   *
   * @return array<array-key, mixed>
   *   YAML mapping.
   */
  private static function arrayValue(mixed $data, string ...$path): array {
    $value = self::value($data, ...$path);
    if (!is_array($value)) {
      throw new \UnexpectedValueException(sprintf('YAML path "%s" must contain a mapping.', implode('.', $path)));
    }

    return $value;
  }

  /**
   * Reads a YAML string while validating every parent mapping.
   */
  private static function stringValue(mixed $data, string ...$path): string {
    $value = self::value($data, ...$path);
    if (!is_string($value)) {
      throw new \UnexpectedValueException(sprintf('YAML path "%s" must contain a string.', implode('.', $path)));
    }

    return $value;
  }

}
