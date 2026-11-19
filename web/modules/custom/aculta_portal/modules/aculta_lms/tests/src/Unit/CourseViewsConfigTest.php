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
      $install = $this->stringKeyedMap(Yaml::parseFile($module_root . '/config/install/views.view.' . $view_id . '.yml'));
      $sync = $this->stringKeyedMap(Yaml::parseFile($sync_root . '/views.view.' . $view_id . '.yml'));
      self::assertSame($view_id, $install['id'] ?? NULL);
      $displays = $this->stringKeyedMap($install['display'] ?? NULL);
      $page_display = array_find(
        $displays,
        static fn (mixed $display, mixed $key): bool => is_array($display)
          && ($display['display_plugin'] ?? NULL) === 'page',
      );
      self::assertIsArray($page_display);
      $pageDisplayMap = $this->stringKeyedMap($page_display);
      $pageOptions = $this->stringKeyedMap($pageDisplayMap['display_options'] ?? NULL);
      self::assertSame($expectation['path'], $pageOptions['path'] ?? NULL);
      $defaultDisplay = $this->stringKeyedMap($displays['default'] ?? NULL);
      $defaultOptions = $this->stringKeyedMap($defaultDisplay['display_options'] ?? NULL);
      if ($expectation['permission'] === NULL) {
        $row = $this->stringKeyedMap($defaultOptions['row'] ?? NULL);
        $rowOptions = $this->stringKeyedMap($row['options'] ?? NULL);
        self::assertSame('entity:group', $row['type'] ?? NULL);
        self::assertSame('default', $rowOptions['view_mode'] ?? NULL);
      }
      else {
        $access = $this->stringKeyedMap($defaultOptions['access'] ?? NULL);
        $accessOptions = $this->stringKeyedMap($access['options'] ?? NULL);
        $style = $this->stringKeyedMap($defaultOptions['style'] ?? NULL);
        $styleOptions = $this->stringKeyedMap($style['options'] ?? NULL);
        self::assertSame(
          $expectation['permission'],
          $accessOptions['perm'] ?? NULL,
        );
        self::assertSame('', $styleOptions['class'] ?? NULL);
      }
      self::assertSame($install, $sync);
    }
  }

  /**
   * Validates a parsed YAML mapping before inspecting its values.
   *
   * @param mixed $value
   *   The parsed YAML value.
   *
   * @return array<string, mixed>
   *   A mapping with string keys.
   */
  private function stringKeyedMap(mixed $value): array {
    self::assertIsArray($value);
    $mapping = [];
    foreach ($value as $key => $item) {
      self::assertIsString($key);
      $mapping[$key] = $item;
    }
    return $mapping;
  }

}
