<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageFileUsageManager;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PManagedFileStorage;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Tests\UnitTestCase;

/** @coversDefaultClass \Drupal\aculta_lms_h5p\H5PDrupal\H5PManagedFileStorage */
final class H5PManagedFileStorageTest extends UnitTestCase {

  /** @covers ::cloneContentFile */
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
    $storage = new H5PManagedFileStorage(
      $root . '/h5p',
      $fileSystem,
      $repository,
      $fileUsage,
      new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage),
      new FileRemover($fileUsage, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(Connection::class), $fileSystem, $this->createMock(LoggerChannelFactoryInterface::class)),
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

  /** @covers ::cloneContentFile */
  public function testRejectsTraversalBeforeLookingUpFiles(): void {
    $root = sys_get_temp_dir() . '/aculta-h5p-private-' . bin2hex(random_bytes(4));
    mkdir($root . '/h5p', 0770, TRUE);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('realpath')->willReturnCallback(static fn (string $uri) => $uri === 'private://' ? $root : FALSE);
    $repository = $this->createMock(FileRepositoryInterface::class);
    $repository->expects(self::never())->method('loadByUri');
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $storage = new H5PManagedFileStorage(
      $root . '/h5p',
      $fileSystem,
      $repository,
      $fileUsage,
      new ImageFileUsageManager(new ImageInspector($this->createMock(ImageFactory::class)), $fileUsage),
      new FileRemover($fileUsage, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(Connection::class), $fileSystem, $this->createMock(LoggerChannelFactoryInterface::class)),
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

}
