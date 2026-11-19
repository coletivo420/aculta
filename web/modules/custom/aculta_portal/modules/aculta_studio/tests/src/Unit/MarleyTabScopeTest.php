<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Attribute\MarleyTab;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Keeps Marley limited to the editorial owners approved for this workspace.
 *
 * The previous version asserted plugin file names. This reads the real
 * #[MarleyTab] attribute that MarleyTabManager discovery consumes, so a class
 * whose file name changes but keeps an out-of-scope id is still caught.
 */
#[Group('aculta_studio')]
final class MarleyTabScopeTest extends UnitTestCase {

  /**
   * Wiki and LMS are served from their own workspaces, never Marley tabs.
   */
  public function testMarleyOnlyDiscoversMagazineForumAndGalleryPlugins(): void {
    $directory = dirname(__DIR__, 3) . '/src/Plugin/MarleyTab';
    $files = glob($directory . '/*.php') ?: [];
    self::assertNotEmpty($files, 'Nenhum plugin MarleyTab encontrado.');

    $ids = [];
    foreach ($files as $file) {
      $reflection = new \ReflectionClass($this->fullyQualifiedName($file));
      $attributes = $reflection->getAttributes(MarleyTab::class);
      self::assertCount(1, $attributes, $reflection->getName() . ' precisa declarar #[MarleyTab].');
      $ids[] = $attributes[0]->newInstance()->id;
    }
    sort($ids);

    self::assertSame(['forum', 'galerias', 'revista'], $ids);
    self::assertNotContains('wiki', $ids, 'Wiki tem workspace próprio.');
    self::assertNotContains('lms', $ids, 'LMS tem workspace próprio.');
  }

  /**
   * Resolves the production class declared by a plugin file.
   */
  private function fullyQualifiedName(string $file): string {
    $source = (string) file_get_contents($file);
    preg_match('/namespace\s+([^;]+);/', $source, $namespace);
    preg_match('/(?:final\s+)?class\s+(\w+)/', $source, $class);
    self::assertArrayHasKey(1, $namespace, $file . ' não declara namespace.');
    self::assertArrayHasKey(1, $class, $file . ' não declara classe.');
    return trim($namespace[1]) . '\\' . trim($class[1]);
  }

}
