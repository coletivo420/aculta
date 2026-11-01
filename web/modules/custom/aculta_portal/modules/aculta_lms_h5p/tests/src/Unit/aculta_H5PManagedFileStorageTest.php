<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\PackagedAssetPublisher;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PManagedFileStorage;
use Drupal\aculta_lms_h5p\aculta_H5PEditor\aculta_H5PFileUsageManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileInterface;
use Drupal\aculta_image\FileRemoverInterface;
use Drupal\aculta_portal\Cleanup\FileReferenceInspectorInterface;
use Drupal\aculta_portal\Cleanup\FileReferenceStatus;
use Drupal\aculta_portal\Cleanup\UnusedFileCleaner;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the managed storage adapter that implements H5P's historical API.
 */
#[CoversClass(aculta_H5PManagedFileStorage::class)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital,Drupal.NamingConventions.ValidClassName.NoUnderscores -- Keep the test class aligned with the H5P adapter name under test.
final class aculta_H5PManagedFileStorageTest extends UnitTestCase {

  /**
   * Allows Unicode names for managed H5P files.
   */
  public function testAllowsUnicodeManagedFilename(): void {
    $root = sys_get_temp_dir() . '/aculta-h5p-private-' . bin2hex(random_bytes(4));
    mkdir($root . '/h5p/editor/images', 0770, TRUE);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(static fn (string $uri) => $uri === 'private://' ? $root : FALSE);
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects(self::once())
      ->method('loadByUri')
      ->with('private://h5p/editor/images/foto-ação.webp')
      ->willReturn(NULL);
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $storage = new aculta_H5PManagedFileStorage(
      $root . '/h5p',
      $fileSystem,
      $repository,
      $fileUsage,
      new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage),
      $this->unusedCleaner(),
      (new \ReflectionClass(PackagedAssetPublisher::class))->newInstanceWithoutConstructor(),
      $this->usageManager($fileSystem, $repository, $fileUsage),
    );

    try {
      $storage->cloneContentFile('images/foto-ação.webp', 'editor', 2);
      self::fail('Expected unmanaged upload to be rejected.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('The H5P editor file is not a managed Drupal file.', $exception->getMessage());
    }
    finally {
      rmdir($root . '/h5p/editor/images');
      rmdir($root . '/h5p/editor');
      rmdir($root . '/h5p');
      rmdir($root);
    }
  }

  /**
   * Rejects traversal paths before any managed-file lookup.
   */
  public function testRejectsTraversalBeforeLookingUpFiles(): void {
    $root = sys_get_temp_dir() . '/aculta-h5p-private-' . bin2hex(random_bytes(4));
    mkdir($root . '/h5p', 0770, TRUE);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(static fn (string $uri) => $uri === 'private://' ? $root : FALSE);
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects(self::never())->method('loadByUri');
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $storage = new aculta_H5PManagedFileStorage(
      $root . '/h5p',
      $fileSystem,
      $repository,
      $fileUsage,
      new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage),
      $this->unusedCleaner(),
      (new \ReflectionClass(PackagedAssetPublisher::class))->newInstanceWithoutConstructor(),
      $this->usageManager($fileSystem, $repository, $fileUsage),
    );

    try {
      $storage->cloneContentFile('images/../../outside.webp', 'editor', 2);
      self::fail('Expected path traversal to be rejected.');
    }
    catch (\InvalidArgumentException $exception) {
      self::assertSame('The H5P file path is invalid.', $exception->getMessage());
    }
    finally {
      rmdir($root . '/h5p');
      rmdir($root);
    }
  }

  /**
   * Rejects H5P library extraction outside the private directory.
   */
  public function testRejectsLibraryExtractionOutsidePrivateH5pStorage(): void {
    $root = sys_get_temp_dir() . '/aculta-h5p-private-' . bin2hex(random_bytes(4));
    $outside = sys_get_temp_dir() . '/aculta-h5p-package-' . bin2hex(random_bytes(4));
    mkdir($root . '/h5p', 0770, TRUE);
    mkdir($outside, 0770, TRUE);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(static fn (string $uri) => $uri === 'private://' ? $root : FALSE);
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $repository = $this->createMock(FileRepositoryInterface::class);
    $imageUsage = new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage);
    $storage = new aculta_H5PManagedFileStorage(
      $root . '/h5p',
      $fileSystem,
      $repository,
      $fileUsage,
      $imageUsage,
      $this->unusedCleaner(),
      (new \ReflectionClass(PackagedAssetPublisher::class))->newInstanceWithoutConstructor(),
      $this->usageManager($fileSystem, $repository, $fileUsage, $imageUsage),
    );
    try {
      $storage->saveLibrary([
        'machineName' => 'H5P.Test',
        'majorVersion' => 1,
        'minorVersion' => 0,
        'uploadDirectory' => $outside,
      ]);
      self::fail('Expected an extraction path outside private storage to be rejected.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('The H5P library package directory is unavailable.', $exception->getMessage());
    }
    finally {
      rmdir($outside);
      rmdir($root . '/h5p');
      rmdir($root);
    }
  }

  /**
   * Rejects raster formats outside the Image policy.
   */
  public function testRejectsLegacyRasterFormatsOutsideAcultaImagePolicy(): void {
    $root = sys_get_temp_dir() . '/aculta-h5p-private-' . bin2hex(random_bytes(4));
    $source = $root . '/h5p/import/H5P.Test-1.0';
    mkdir($source, 0770, TRUE);
    file_put_contents($source . '/cover.bmp', 'not copied');
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(static fn (string $uri) => $uri === 'private://' ? $root : FALSE);
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $repository = $this->createMock(FileRepositoryInterface::class);
    $imageUsage = new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage);
    $storage = new aculta_H5PManagedFileStorage(
      $root . '/h5p',
      $fileSystem,
      $repository,
      $fileUsage,
      $imageUsage,
      $this->unusedCleaner(),
      (new \ReflectionClass(PackagedAssetPublisher::class))->newInstanceWithoutConstructor(),
      $this->usageManager($fileSystem, $repository, $fileUsage, $imageUsage),
    );
    try {
      $storage->saveLibrary([
        'machineName' => 'H5P.Test',
        'majorVersion' => 1,
        'minorVersion' => 0,
        'uploadDirectory' => $source,
      ]);
      self::fail('Expected an unsupported image format to be rejected.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('This H5P image format is not accepted by aculta_image.', $exception->getMessage());
    }
    finally {
      unlink($source . '/cover.bmp');
      rmdir($source);
      rmdir(dirname($source));
      rmdir(dirname(dirname($source)));
      rmdir($root);
    }
  }

  /**
   * Keeps an H5P image when the global inspector finds a reference.
   */
  public function testContentImageRemovalFailsClosedOnGlobalReference(): void {
    $root = sys_get_temp_dir() . '/aculta-h5p-private-' . bin2hex(random_bytes(4));
    mkdir($root . '/h5p', 0770, TRUE);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(static fn (string $uri) => $uri === 'private://' ? $root : FALSE);
    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('asset.png');
    $file->method('getMimeType')->willReturn('image/png');
    $file->method('getFileUri')->willReturn('private://h5p/content/7/images/asset.png');
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->method('loadByUri')->with('private://h5p/content/7/images/asset.png')->willReturn($file);
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $fileUsage->expects(self::exactly(2))->method('delete');
    $fileUsage->method('listUsage')->with($file)->willReturn([]);
    $references = $this->createMock(FileReferenceInspectorInterface::class);
    $references->expects(self::once())->method('inspect')->with($file)->willReturn(FileReferenceStatus::Referenced);
    $imageRemover = $this->createMock(FileRemoverInterface::class);
    $imageRemover->expects(self::never())->method('delete');
    $cleaner = $this->cleaner($fileUsage, $references, $imageRemover);
    $imageUsage = new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage);
    $storage = new aculta_H5PManagedFileStorage(
      $root . '/h5p',
      $fileSystem,
      $repository,
      $fileUsage,
      $imageUsage,
      $cleaner,
      (new \ReflectionClass(PackagedAssetPublisher::class))->newInstanceWithoutConstructor(),
      $this->usageManager($fileSystem, $repository, $fileUsage, $imageUsage),
    );

    try {
      $storage->removeContentFile('images/asset.png', 7);
      self::fail('Expected an H5P image with a global reference to be retained.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('An H5P content image is still referenced and cannot be removed.', $exception->getMessage());
    }
    finally {
      rmdir($root . '/h5p');
      rmdir($root);
    }
  }

  /**
   * Builds the real Portal cleaner around mocked database dependencies.
   */
  private function cleaner(FileUsageInterface $fileUsage, FileReferenceInspectorInterface $references, FileRemoverInterface $remover): UnusedFileCleaner {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->with('entity_usage')->willReturn(FALSE);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    return new UnusedFileCleaner(
      $fileUsage,
      $database,
      $references,
      $remover,
      new ImageInspector($this->createMock(ImageFactory::class)),
      $this->createMock(LoggerChannelInterface::class),
    );
  }

  /**
   * Provides an unused uninitialized cleaner for tests that never delete files.
   */
  private function unusedCleaner(): UnusedFileCleaner {
    return (new \ReflectionClass(UnusedFileCleaner::class))->newInstanceWithoutConstructor();
  }

  /**
   * Builds the H5P usage manager for a storage fixture.
   */
  private function usageManager(FileSystemInterface $fileSystem, FileRepositoryInterface $repository, FileUsageInterface $fileUsage, ?ImageFileUsageManager $imageUsage = NULL): aculta_H5PFileUsageManager {
    $imageUsage ??= new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage);
    return new aculta_H5PFileUsageManager($this->createMock(ConfigFactoryInterface::class), $fileSystem, $repository, $fileUsage, $imageUsage);
  }

}
