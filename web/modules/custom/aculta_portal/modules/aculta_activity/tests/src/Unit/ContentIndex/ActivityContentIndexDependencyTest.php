<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Unit\ContentIndex;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;
use PHPUnit\Framework\Attributes\Group;

/**
 * Keeps the Activity adapter optional when the Content Index is disabled.
 */
#[Group('aculta_activity')]
final class ActivityContentIndexDependencyTest extends UnitTestCase {

  /**
   * Tests that Activity owns a tagged adapter without requiring the Index.
   */
  public function testIndexAdapterIsOptionalAtModuleBoundary(): void {
    $moduleDirectory = dirname(__DIR__, 4);
    $info = Yaml::parseFile($moduleDirectory . '/aculta_activity.info.yml');
    self::assertIsArray($info);
    $dependencies = is_array($info['dependencies'] ?? NULL) ? $info['dependencies'] : [];

    self::assertContains('aculta_portal:aculta_portal', $dependencies);
    self::assertNotContains('aculta_portal:aculta_index', $dependencies);
    self::assertNotContains('aculta_index', $dependencies);

    $parsedServices = Yaml::parseFile($moduleDirectory . '/aculta_activity.services.yml');
    self::assertIsArray($parsedServices);
    $services = is_array($parsedServices['services'] ?? NULL) ? $parsedServices['services'] : [];
    $providerService = is_array($services['aculta_activity.content_index_provider'] ?? NULL) ? $services['aculta_activity.content_index_provider'] : [];

    self::assertSame(
      'Drupal\\aculta_activity\\ContentIndex\\ActivityContentIndexProvider',
      $providerService['class'] ?? NULL,
    );
    $tags = is_array($providerService['tags'] ?? NULL) ? $providerService['tags'] : [];
    self::assertContains(
      ['name' => 'aculta_index.provider'],
      $tags,
    );
  }

}
