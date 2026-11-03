<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Keeps Marley limited to the editorial owners approved for this workspace.
 */
#[Group('aculta_studio')]
final class MarleyTabScopeTest extends UnitTestCase {

  /**
   * Wiki and LMS are served from their own workspaces, never Marley tabs.
   */
  public function testMarleyOnlyDiscoversMagazineForumAndGalleryPlugins(): void {
    $directory = dirname(__DIR__, 3) . '/src/Plugin/MarleyTab';
    $files = glob($directory . '/*.php') ?: [];
    $ids = array_map(static fn (string $file): string => basename($file, '.php'), $files);
    sort($ids);

    self::assertSame(['ForumTab', 'GaleriasTab', 'RevistaTab'], $ids);
  }

}
