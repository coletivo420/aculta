<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks the single administrative surface for Magazine content and comments.
 */
#[Group('aculta_magazine')]
final class MagazineAdminViewsConfigTest extends UnitTestCase {

  /**
   * Editorial content and comments share one path and remain bundle-scoped.
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
    self::assertSame($page, $page_sync);
    self::assertSame($comments, $comments_sync);

    self::assertSame('admin/content/revista', $page['display']['page_magazine']['display_options']['path']);
    self::assertSame(
      ['article' => 'article', 'revista' => 'revista', 'blog' => 'blog'],
      $page['display']['default']['display_options']['filters']['type']['value'],
    );
    self::assertSame('access content overview', $page['display']['default']['display_options']['access']['options']['perm']);

    $options = $comments['display']['default']['display_options'];
    self::assertSame('administer comments', $options['access']['options']['perm']);
    self::assertSame('field_aculta_discussion', $options['filters']['field_name']['value']);
    self::assertSame(['article' => 'article', 'revista' => 'revista', 'blog' => 'blog'], $options['filters']['type']['value']);
    self::assertSame('block', $comments['display']['block_comments']['display_plugin']);

    $block = Yaml::parseFile($repository_path . '/config/sync/block.block.aculta_magazine_admin_comments.yml');
    self::assertSame('views_block:aculta_magazine_admin_comments-block_comments', $block['plugin']);
    self::assertStringContainsString('/painel-administrativo/conteudo/revista', $block['visibility']['request_path']['pages']);
    self::assertSame('main', $block['visibility']['aculta_domain_purpose']['purpose']);
  }

  /**
   * The unified Magazine page has an explicit entry in admin Content.
   */
  public function testAdministrativeMenuLinkTargetsUnifiedMagazinePage(): void {
    $menu = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_magazine.links.menu.yml');

    self::assertSame('view.aculta_magazine_admin_content.page_magazine', $menu['aculta_magazine.admin_content']['route_name']);
    self::assertSame('system.admin_content', $menu['aculta_magazine.admin_content']['parent']);
    self::assertSame('admin', $menu['aculta_magazine.admin_content']['menu_name']);
  }

}
