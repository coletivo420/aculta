<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Unit\ContentIndex;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps the Activity adapter optional when the Content Index is disabled.
 *
 * @group aculta_activity
 */
final class ActivityContentIndexDependencyTest extends UnitTestCase {

  /**
   * Tests that Activity owns a tagged adapter without requiring the Index.
   */
  public function testIndexAdapterIsOptionalAtModuleBoundary(): void {
    $moduleDirectory = dirname(__DIR__, 4);
    $info = Yaml::parseFile($moduleDirectory . '/aculta_activity.info.yml');
    $dependencies = $info['dependencies'] ?? [];

    self::assertContains('aculta_portal:aculta_portal', $dependencies);
    self::assertNotContains('aculta_portal:aculta_index', $dependencies);
    self::assertNotContains('aculta_index', $dependencies);

    $services = Yaml::parseFile($moduleDirectory . '/aculta_activity.services.yml')['services'];
    self::assertSame(
      'Drupal\\aculta_activity\\ContentIndex\\ActivityContentIndexProvider',
      $services['aculta_activity.content_index_provider']['class'],
    );
    self::assertContains(
      ['name' => 'aculta_index.provider'],
      $services['aculta_activity.content_index_provider']['tags'],
    );
  }

}
