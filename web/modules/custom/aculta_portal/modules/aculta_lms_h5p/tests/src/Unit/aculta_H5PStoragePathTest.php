<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PStoragePath;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests H5P storage path preparation without changing existing permissions.
 */
#[CoversClass(aculta_H5PStoragePath::class)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital,Drupal.NamingConventions.ValidClassName.NoUnderscores -- Keep the test class aligned with the H5P discovery identifier under test.
final class aculta_H5PStoragePathTest extends UnitTestCase {

  /**
   * Reuses a group-writable path without asking Drupal to chmod it.
   */
  public function testPreparesDirectoryWithoutChangingPermissions(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('aculta_lms_h5p_default_path')
      ->willReturn('h5p');
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects(self::once())
      ->method('get')
      ->with('aculta_lms_h5p.settings')
      ->willReturn($config);

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects(self::once())
      ->method('prepareDirectory')
      ->with('private://h5p', FileSystemInterface::CREATE_DIRECTORY)
      ->willReturn(TRUE);
    $fileSystem->expects(self::once())
      ->method('realpath')
      ->with('private://h5p')
      ->willReturn('/private/aculta/h5p');

    $storagePath = new aculta_H5PStoragePath($configFactory, $fileSystem);

    self::assertSame('/private/aculta/h5p', $storagePath->getPath());
  }

}
