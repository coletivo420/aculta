<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies that ACULTA LMS owns its adapted public and admin Views.
 */
final class CourseViewsConfigTest extends TestCase {

  /**
   * Tests install configuration and sync copies use supported row plugins.
   */
  public function testLmsViewsAreProvidedByTheModuleAndConfigSync(): void {
    $module_root = dirname(__DIR__, 3);
    $sync_root = dirname($module_root, 6) . '/config/sync';
    foreach ([
      'courses' => ['path' => 'courses', 'permission' => NULL],
      'courses_catalog' => ['path' => 'cursos/catalogo', 'permission' => NULL],
      'courses_admin' => ['path' => 'admin/lms/courses', 'permission' => 'create lms_course group'],
      'classes_admin' => ['path' => 'admin/lms/classes', 'permission' => 'create lms_class group'],
    ] as $view_id => $expectation) {
      $install = Yaml::parseFile($module_root . '/config/install/views.view.' . $view_id . '.yml');
      $sync = Yaml::parseFile($sync_root . '/views.view.' . $view_id . '.yml');
      self::assertSame($view_id, $install['id']);
      $page_display = array_find(
        $install['display'],
        static fn (array $display): bool => ($display['display_plugin'] ?? NULL) === 'page',
      );
      self::assertNotNull($page_display);
      self::assertSame($expectation['path'], $page_display['display_options']['path']);
      if ($expectation['permission'] === NULL) {
        self::assertSame('entity:group', $install['display']['default']['display_options']['row']['type']);
        self::assertSame('default', $install['display']['default']['display_options']['row']['options']['view_mode']);
      }
      else {
        self::assertSame(
          $expectation['permission'],
          $install['display']['default']['display_options']['access']['options']['perm'],
        );
        self::assertSame('', $install['display']['default']['display_options']['style']['options']['class']);
      }
      self::assertSame($install, $sync);
    }
  }

}
