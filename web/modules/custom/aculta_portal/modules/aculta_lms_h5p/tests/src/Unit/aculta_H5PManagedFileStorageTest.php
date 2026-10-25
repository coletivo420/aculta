<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\PackagedAssetPublisher;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PManagedFileStorage;
use Drupal\aculta_lms_h5p\aculta_H5PEditor\aculta_H5PFileUsageManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(aculta_H5PManagedFileStorage::class)]
final class aculta_H5PManagedFileStorageTest extends UnitTestCase {

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
      $this->createMock(EntityTypeManagerInterface::class),
      $fileUsage,
      new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage),
      new FileRemover($fileUsage, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(Connection::class), $fileSystem, $this->createMock(LoggerChannelFactoryInterface::class)),
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
      $this->createMock(EntityTypeManagerInterface::class),
      $fileUsage,
      new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage),
      new FileRemover($fileUsage, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(Connection::class), $fileSystem, $this->createMock(LoggerChannelFactoryInterface::class)),
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
      $this->createMock(EntityTypeManagerInterface::class),
      $fileUsage,
      $imageUsage,
      new FileRemover($fileUsage, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(Connection::class), $fileSystem, $this->createMock(LoggerChannelFactoryInterface::class)),
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
      $this->createMock(EntityTypeManagerInterface::class),
      $fileUsage,
      $imageUsage,
      new FileRemover($fileUsage, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(Connection::class), $fileSystem, $this->createMock(LoggerChannelFactoryInterface::class)),
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

  private function usageManager(FileSystemInterface $fileSystem, FileRepositoryInterface $repository, FileUsageInterface $fileUsage, ?ImageFileUsageManager $imageUsage = NULL): aculta_H5PFileUsageManager {
    $imageUsage ??= new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage);
    return new aculta_H5PFileUsageManager($this->createMock(ConfigFactoryInterface::class), $fileSystem, $repository, $fileUsage, $imageUsage);
  }

}
