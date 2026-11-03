<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies that public course Views are owned and adapted by ACULTA LMS.
 */
final class CourseViewsConfigTest extends TestCase {

  /**
   * Tests install configuration and sync copies use supported row plugins.
   */
  public function testCourseViewsUseGroupEntityRows(): void {
    $module_root = dirname(__DIR__, 3);
    $sync_root = dirname($module_root, 6) . '/config/sync';
    foreach (['courses' => 'courses', 'courses_catalog' => 'cursos/catalogo'] as $view_id => $path) {
      $install = Yaml::parseFile($module_root . '/config/install/views.view.' . $view_id . '.yml');
      $sync = Yaml::parseFile($sync_root . '/views.view.' . $view_id . '.yml');
      self::assertSame($view_id, $install['id']);
      self::assertSame('entity:group', $install['display']['default']['display_options']['row']['type']);
      self::assertSame('default', $install['display']['default']['display_options']['row']['options']['view_mode']);
      self::assertSame($path, $install['display']['page_1']['display_options']['path']);
      self::assertSame($install, $sync);
    }
  }

}
