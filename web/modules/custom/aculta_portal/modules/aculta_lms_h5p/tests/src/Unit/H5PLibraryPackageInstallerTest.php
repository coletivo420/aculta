<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\H5PDrupal\H5PLibraryPackageInstaller;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;

/** @coversDefaultClass \Drupal\aculta_lms_h5p\H5PDrupal\H5PLibraryPackageInstaller */
final class H5PLibraryPackageInstallerTest extends UnitTestCase {

  /** @covers ::install */
  public function testRejectsNonH5pFileBeforeBootstrap(): void {
    $file = tempnam(sys_get_temp_dir(), 'aculta-h5p-invalid-');
    self::assertNotFalse($file);
    $zip = $file . '.zip';
    rename($file, $zip);
    try {
      $installer = new H5PLibraryPackageInstaller($this->createMock(FileSystemInterface::class));
      $this->expectException(\InvalidArgumentException::class);
      $installer->install($zip);
    }
    finally {
      if (is_file($zip)) {
        unlink($zip);
      }
    }
  }

}
