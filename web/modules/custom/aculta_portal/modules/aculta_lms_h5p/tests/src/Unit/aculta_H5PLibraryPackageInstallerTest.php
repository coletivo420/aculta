<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PLibraryPackageInstaller;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PLibraryCacheInvalidator;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PStoragePathInterface;
use Drupal\Core\Asset\LibraryDiscoveryCollector;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 */
#[CoversClass(aculta_H5PLibraryPackageInstaller::class)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
final class aculta_H5PLibraryPackageInstallerTest extends UnitTestCase {

  /**
   * Rejects files without the H5P package extension before framework boot. */
  public function testRejectsNonH5pFileBeforeBootstrap(): void {
    $file = tempnam(sys_get_temp_dir(), 'aculta-h5p-invalid-');
    self::assertNotFalse($file);
    $zip = $file . '.zip';
    rename($file, $zip);
    try {
      $installer = new aculta_H5PLibraryPackageInstaller(
        $this->createMock(FileSystemInterface::class),
        $this->createMock(aculta_H5PStoragePathInterface::class),
        $this->createCacheInvalidator(),
        $this->createMock(aculta_H5PDrupal::class),
      );
      $this->expectException(\InvalidArgumentException::class);
      $installer->install($zip);
    }
    finally {
      if (is_file($zip)) {
        unlink($zip);
      }
    }
  }

  /**
   * Rejects archive entries that attempt to escape the extraction directory. */
  public function testRejectsZipPathTraversalBeforeH5pExtraction(): void {
    $path = tempnam(sys_get_temp_dir(), 'aculta-h5p-traversal-');
    self::assertNotFalse($path);
    $zip = new \ZipArchive();
    self::assertSame(TRUE, $zip->open($path, \ZipArchive::OVERWRITE));
    self::assertTrue($zip->addFromString('../../escape.js', 'malicious'));
    self::assertTrue($zip->close());
    $package = $path . '.h5p';
    rename($path, $package);
    try {
      $installer = new aculta_H5PLibraryPackageInstaller(
        $this->createMock(FileSystemInterface::class),
        $this->createMock(aculta_H5PStoragePathInterface::class),
        $this->createCacheInvalidator(),
        $this->createMock(aculta_H5PDrupal::class),
      );
      $this->expectException(\UnexpectedValueException::class);
      $this->expectExceptionMessage('O pacote H5P contém um caminho inválido.');
      $installer->install($package);
    }
    finally {
      unlink($package);
    }
  }

  /**
   * Creates the cache invalidator double used by installer tests. */
  private function createCacheInvalidator(): aculta_H5PLibraryCacheInvalidator {
    return new aculta_H5PLibraryCacheInvalidator(
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(LibraryDiscoveryCollector::class),
    );
  }

}
