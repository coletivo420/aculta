<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Status;

use Drupal\aculta_portal\Status\PhpExtensionRequirements;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the PHP extension inventory derived from Composer package metadata.
 *
 * @group aculta_portal
 */
final class PhpExtensionRequirementsTest extends UnitTestCase {

  /** Checks transitive requirements, loaded status, and source attribution. */
  public function testBuildsRuntimeExtensionRows(): void {
    $requirements = [
      [
        'name' => 'Integração de exemplo',
        'modules' => ['example', 'example_ui'],
        'package' => 'vendor/root',
      ],
    ];
    $packages = [
      ['name' => 'vendor/root', 'require' => ['vendor/child' => '^1.0', 'ext-json' => '*']],
      ['name' => 'vendor/child', 'require' => ['ext-curl' => '*']],
      ['name' => 'vendor/unused', 'require' => ['ext-zip' => '*']],
    ];

    $rows = PhpExtensionRequirements::build($requirements, $packages, ['JSON']);

    self::assertSame(['ext-curl', 'ext-json'], array_column($rows, 'extension'));
    self::assertFalse($rows[0]['loaded']);
    self::assertSame(['vendor/child'], $rows[0]['packages']);
    self::assertTrue($rows[1]['loaded']);
    self::assertSame(['example', 'example_ui'], $rows[1]['modules']);
    self::assertSame(['Integração de exemplo'], $rows[1]['integrations']);
  }

}
