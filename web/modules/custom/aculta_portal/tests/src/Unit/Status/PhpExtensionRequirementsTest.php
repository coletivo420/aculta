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

  /** A new runtime package declaration is included by the global scan. */
  public function testGlobalScanFindsExtensionsFromEveryRuntimePackage(): void {
    $requirements = [
      ['name' => 'Varredura global', 'modules' => [], 'scan_all' => TRUE],
    ];
    $packages = [
      ['name' => 'vendor/first', 'require' => ['ext-json' => '*']],
      ['name' => 'vendor/new-package', 'require' => ['ext-sodium' => '*']],
    ];

    $rows = PhpExtensionRequirements::build($requirements, $packages, ['sodium']);

    self::assertSame(['ext-json', 'ext-sodium'], array_column($rows, 'extension'));
    self::assertTrue($rows[1]['loaded']);
    self::assertSame(['vendor/new-package'], $rows[1]['packages']);
    self::assertSame(['Varredura global'], $rows[1]['integrations']);
  }

  /** Explicit roots retain module attribution for image and PDF integrations. */
  public function testMapsImageAndPdfPackageRequirements(): void {
    $requirements = [
      ['name' => 'ACULTA Image', 'modules' => ['aculta_image'], 'packages' => ['drupal/imageapi_optimize']],
      ['name' => 'ACULTA PDF Viewer', 'modules' => ['aculta_pdf_viewer'], 'packages' => ['drupal/pdfpreview']],
    ];
    $packages = [
      ['name' => 'drupal/imageapi_optimize', 'require' => ['vendor/image-lib' => '^1']],
      ['name' => 'vendor/image-lib', 'require' => ['ext-gd' => '*']],
      ['name' => 'drupal/pdfpreview', 'require' => ['ext-fileinfo' => '*']],
    ];

    $rows = PhpExtensionRequirements::build($requirements, $packages, ['gd', 'fileinfo']);

    self::assertSame(['ext-fileinfo', 'ext-gd'], array_column($rows, 'extension'));
    self::assertSame(['aculta_pdf_viewer'], $rows[0]['modules']);
    self::assertSame(['aculta_image'], $rows[1]['modules']);
  }

}
