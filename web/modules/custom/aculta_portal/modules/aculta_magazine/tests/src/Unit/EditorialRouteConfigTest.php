<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies the administrative and Studio domain contracts in source config. */
final class EditorialRouteConfigTest extends UnitTestCase {

  /**
   * Confirms the MAIN and account-purpose routes remain separate.
   */
  public function testAdministrativeAndStudioRoutePurposes(): void {
    $module = dirname(__DIR__, 3);
    $routes = Yaml::decode(file_get_contents($module . '/aculta_magazine.routing.yml'));
    self::assertSame('/painel-administrativo/revista', $routes['aculta_magazine.admin']['path']);
    foreach (['admin', 'capabilities', 'admin_comments'] as $route) {
      self::assertSame('main', $routes['aculta_magazine.' . $route]['options']['_aculta_domain_purpose']);
      self::assertTrue($routes['aculta_magazine.' . $route]['options']['_admin_route']);
    }
    $studioRoutes = [
      'studio_list',
      'studio_add',
      'studio_edit',
      'studio_transition',
      'studio_comment_moderate',
      'studio_discussion',
    ];
    foreach ($studioRoutes as $route) {
      self::assertSame('account', $routes['aculta_magazine.' . $route]['options']['_aculta_domain_purpose']);
    }
    self::assertStringNotContainsString('/studio/marley', file_get_contents($module . '/aculta_magazine.routing.yml'));
  }

  /**
   * Confirms the administrative View uses the approved path and permission.
   */
  public function testAdministrativeViewNoLongerUsesGenericContentPath(): void {
    $module = dirname(__DIR__, 3);
    $repository = dirname($module, 6);
    $view = Yaml::decode(file_get_contents($repository . '/config/sync/views.view.aculta_magazine_admin_content.yml'));
    self::assertSame('painel-administrativo/revista/publicacoes', $view['display']['page_magazine']['display_options']['path']);
    self::assertSame('administer site configuration', $view['display']['default']['display_options']['access']['options']['perm']);
  }

}
